import aiohttp
import asyncio
import ssl
from aiosmtpd.controller import Controller
from email.parser import BytesParser
from email.header import decode_header, make_header
from email import policy
import os
import re
import time
import json
import hashlib
import hmac
import configparser
import logging

logger = logging.getLogger(__name__)

# Base paths
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
DATA_DIR = os.path.join(BASE_DIR, "data")
CONFIG_PATH = os.path.join(BASE_DIR, "config.ini")

# globals for settings
DISCARD_UNKNOWN: bool = False
ATTACHMENTS_MAX_SIZE: int = 0
DOMAINS: list[str] = []
URL: str = ""
MAILPORT_TLS: int = 0
TLS_CERTIFICATE: str = ""
TLS_PRIVATE_KEY: str = ""
WEBHOOK_URL: str = ""


def ensure_dir(path: str, mode: int = 0o755) -> None:
    """Create a directory if it doesn't exist."""
    os.makedirs(path, mode=mode, exist_ok=True)


def safe_email_dir(email: str) -> str:
    """
    Convert an email address into a safe directory path under DATA_DIR.
    Prevents path traversal by sanitising and enforcing base prefix.
    """
    email = email.lower()
    safe = re.sub(r"[^a-z0-9@\._\-]+", "_", email)
    path = os.path.abspath(os.path.join(DATA_DIR, safe))
    if not path.startswith(DATA_DIR + os.sep) and path != DATA_DIR:
        raise ValueError(f"Unsafe email path derived from: {email}")
    return path


def safe_attachment_id(filename: str) -> str:
    """
    Generate a safe file ID for an attachment, based on a sanitised basename.
    """
    basename = os.path.basename(filename) or "file"
    basename = re.sub(r"[^a-zA-Z0-9\.\-_]+", "_", basename)
    return hashlib.md5(basename.encode("utf-8")).hexdigest() + "_" + basename


class CustomHandler:
    connection_type = ""

    def __init__(self, conntype: str = "Plaintext") -> None:
        self.connection_type = conntype

    async def handle_DATA(self, server, session, envelope):
        peer = session.peer
        rcpts = list(envelope.rcpt_tos)

        logger.debug("Receiving message from: %s (%s)", peer, self.connection_type)
        logger.debug("Message addressed from: %s", envelope.mail_from)
        logger.debug("Message addressed to: %s", rcpts)

        filenamebase = str(int(round(time.time() * 1000)))
        raw_email = envelope.content.decode("utf-8", errors="replace")
        message = BytesParser(policy=policy.default).parsebytes(envelope.content)
        subject = (
            str(make_header(decode_header(message["subject"])))
            if message["subject"]
            else "(No Subject)"
        )
        plaintext = ""
        html = ""
        attachments: dict[str, tuple[str, bytes, str | None, str]] = {}

        for part in message.walk():
            if part.get_content_maintype() == "multipart":
                continue

            content_type = part.get_content_type()
            is_attachment = part.get_filename() is not None
            payload_bytes = part.get_payload(decode=True)

            if content_type == "text/plain" and not is_attachment:
                try:
                    plaintext += payload_bytes.decode("utf-8")
                    logger.debug("Plaintext (UTF-8) received")
                except UnicodeDecodeError:
                    plaintext += payload_bytes.decode("latin1", errors="replace")
                    logger.debug("Plaintext (latin1) received")
                except Exception as e:
                    logger.error("Error decoding plaintext payload: %s", e)
            elif content_type == "text/html" and not is_attachment:
                try:
                    html += payload_bytes.decode("utf-8")
                    logger.debug("HTML (UTF-8) received")
                except UnicodeDecodeError:
                    html += payload_bytes.decode("latin1", errors="replace")
                    logger.debug("HTML (latin1) received")
                except Exception as e:
                    logger.error("Error decoding HTML payload: %s", e)
            else:
                att = self.handleAttachment(part, payload_bytes)
                if att is False:
                    return (
                        "500 Attachment too large. Max size: "
                        + f"{ATTACHMENTS_MAX_SIZE / 1000000:.2f}MB"
                    )
                attachments[f"file{len(attachments)}"] = att

        for em in rcpts:
            em = em.lower()

            if not re.match(r"^[^@\s]+@[^@\s]+\.[a-zA-Z0-9]+$", em):
                logger.warning("Invalid recipient: %s", em)
                continue

            domain = em.split("@", 1)[1]

            found = False
            for x in DOMAINS:
                x = x.strip()
                if not x:
                    continue
                if "*" in x and domain.endswith(x.replace("*", "")):
                    found = True
                    break
                if domain == x:
                    found = True
                    break

            if DISCARD_UNKNOWN and not found:
                logger.info("Discarding email for unknown domain: %s", domain)
                continue

            try:
                email_dir = safe_email_dir(em)
            except ValueError as e:
                logger.error("Skipping email due to unsafe path for %s: %s", em, e)
                continue

            ensure_dir(email_dir, 0o755)

            edata = {
                "subject": subject,
                "body": plaintext,
                "htmlbody": self.replace_cid_with_attachment_id(html, attachments, em),
                "from": message["from"],
                "attachments": [],
                "attachments_details": [],
            }

            savedata = {
                "sender_ip": peer[0],
                "from": message["from"],
                "rcpts": rcpts,
                "raw": raw_email,
                "parsed": edata,
            }

            if attachments:
                attachments_dir = os.path.join(email_dir, "attachments")
                ensure_dir(attachments_dir, 0o755)

                for att in attachments.values():
                    filename, payload, cid, file_id = att
                    file_path = os.path.abspath(
                        os.path.join(attachments_dir, file_id)
                    )

                    if not file_path.startswith(attachments_dir + os.sep):
                        logger.error("Unsafe attachment path blocked: %s", file_path)
                        continue

                    with open(file_path, "wb") as f:
                        f.write(payload)

                    edata["attachments"].append(file_id)
                    edata["attachments_details"].append(
                        {
                            "filename": filename,
                            "cid": cid,
                            "id": file_id,
                            "download_url": f"{URL}/api/attachment/{em}/{file_id}",
                            "size": len(payload),
                        }
                    )

            json_path = os.path.join(email_dir, f"{filenamebase}.json")
            with open(json_path, "w", encoding="utf-8") as outfile:
                json.dump(savedata, outfile, ensure_ascii=False)

            await self.send_to_webhook(em, savedata)

        return "250 OK"

    async def send_to_webhook(self, email, data):
        webhook_config = self.load_webhook_config(email)

        if webhook_config and webhook_config.get("enabled"):
            await self.send_configured_webhook(email, data, webhook_config)
        elif WEBHOOK_URL:
            await self.send_global_webhook(data)

    def load_webhook_config(self, email):
        try:
            email_dir = safe_email_dir(email)
        except ValueError as e:
            logger.error("Cannot load webhook config for %s: %s", email, e)
            return None

        webhook_file = os.path.join(email_dir, "webhook.json")

        if os.path.exists(webhook_file):
            try:
                with open(webhook_file, "r", encoding="utf-8") as f:
                    config = json.load(f)
                    if not isinstance(config, dict):
                        logger.error(
                            "Invalid webhook config format for %s: not a dict", email
                        )
                        return None
                    return config
            except json.JSONDecodeError as e:
                logger.error(
                    "Invalid JSON in webhook config for %s: %s", email, str(e)
                )
            except Exception as e:
                logger.error("Error loading webhook config for %s: %s", email, str(e))
        return None

    def replace_template_variables(self, template, data):
        """Replace {{variable}} placeholders in template with actual data"""

        def json_escape(value):
            if value is None:
                return ""
            s = str(value)
            return (
                s.replace("\\", "\\\\")
                .replace('"', '\\"')
                .replace("\n", "\\n")
                .replace("\r", "\\r")
                .replace("\t", "\\t")
            )

        try:
            parsed = data.get("parsed", {})
            replacements = {
                "{{to}}": json_escape(data["rcpts"][0] if data.get("rcpts") else ""),
                "{{from}}": json_escape(parsed.get("from", "")),
                "{{subject}}": json_escape(parsed.get("subject", "")),
                "{{body}}": json_escape(parsed.get("body", "")),
                "{{htmlbody}}": json_escape(parsed.get("htmlbody", "")),
                "{{sender_ip}}": json_escape(data.get("sender_ip", "")),
                "{{attachments}}": json.dumps(
                    parsed.get("attachments_details", []), ensure_ascii=False
                ),
            }

            result = template
            for key, value in replacements.items():
                result = result.replace(key, value)

            return result
        except Exception as e:
            logger.error("Error replacing template variables: %s", str(e))
            return template

    def sign_payload(self, payload, secret_key):
        """Generate HMAC signature for webhook payload"""
        if not secret_key:
            return None

        return hmac.new(
            secret_key.encode("utf-8"),
            payload.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

    async def send_configured_webhook(self, email, data, config):
        """Send webhook with custom configuration and retry logic"""
        webhook_url = config.get("webhook_url")
        if not webhook_url:
            logger.error("No webhook URL configured for %s", email)
            return

        template = config.get("payload_template", "{}")
        payload_str = self.replace_template_variables(template, data)

        try:
            payload = json.loads(payload_str)
        except json.JSONDecodeError as e:
            logger.error(
                "Invalid JSON in webhook payload template for %s: %s",
                email,
                str(e),
            )
            logger.error("Template: %s", template)
            logger.error("Payload string: %s", payload_str)
            return

        retry_config = config.get("retry_config", {})
        max_attempts = int(retry_config.get("max_attempts", 3))
        backoff_multiplier = float(retry_config.get("backoff_multiplier", 2))

        headers = {"Content-Type": "application/json"}

        secret_key = config.get("secret_key")
        if secret_key:
            signature = self.sign_payload(json.dumps(payload, ensure_ascii=False), secret_key)
            if signature:
                headers["X-Webhook-Signature"] = signature

        timeout = aiohttp.ClientTimeout(total=30)

        for attempt in range(max_attempts):
            try:
                async with aiohttp.ClientSession(timeout=timeout) as session:
                    async with session.post(
                        webhook_url, json=payload, headers=headers
                    ) as response:
                        if 200 <= response.status < 300:
                            logger.info(
                                "Webhook sent successfully to %s for %s (attempt %d)",
                                webhook_url,
                                email,
                                attempt + 1,
                            )
                            return
                        else:
                            logger.warning(
                                "Webhook failed with status %d for %s (attempt %d)",
                                response.status,
                                email,
                                attempt + 1,
                            )
            except Exception as e:
                logger.error(
                    "Error sending webhook for %s (attempt %d): %s",
                    email,
                    attempt + 1,
                    str(e),
                )

            if attempt < max_attempts - 1:
                wait_time = (backoff_multiplier**attempt) * 1
                logger.info(
                    "Retrying webhook for %s in %d seconds...", email, wait_time
                )
                await asyncio.sleep(wait_time)

        logger.error(
            "Failed to send webhook for %s after %d attempts", email, max_attempts
        )

    async def send_global_webhook(self, data):
        """Send to global webhook URL (backward compatibility)"""
        timeout = aiohttp.ClientTimeout(total=30)
        try:
            async with aiohttp.ClientSession(timeout=timeout) as session:
                async with session.post(WEBHOOK_URL, json=data) as response:
                    if 200 <= response.status < 300:
                        logger.info("Global webhook sent successfully.")
                    else:
                        logger.warning(
                            "Global webhook failed with status %d", response.status
                        )
        except Exception as e:
            logger.error("Error sending global webhook: %s", str(e))

    def handleAttachment(self, part, payload_bytes: bytes):
        filename = part.get_filename() or "untitled"

        cid = part.get("Content-ID")
        if cid is not None:
            cid = cid.strip("<>")
        elif part.get("X-Attachment-Id") is not None:
            cid = part.get("X-Attachment-Id")
        else:
            cid = hashlib.md5(payload_bytes).hexdigest()

        fid = safe_attachment_id(filename)

        logger.debug(
            'Handling attachment: "%s" (ID: "%s") of type "%s" with CID "%s"',
            filename,
            fid,
            part.get_content_type(),
            cid,
        )

        if ATTACHMENTS_MAX_SIZE > 0 and len(payload_bytes) > ATTACHMENTS_MAX_SIZE:
            logger.info("Attachment too large: %s", filename)
            return False

        return (filename, payload_bytes, cid, fid)

    def replace_cid_with_attachment_id(self, html_content, attachments, email):
        """Replace cid: references in HTML with /api/attachment/<email>/<id>"""
        if not html_content:
            return html_content

        for (_, _, cid, fid) in attachments.values():
            if not cid:
                continue
            html_content = html_content.replace(
                f"cid:{cid}", f"/api/attachment/{email}/{fid}"
            )
        return html_content


async def run(port: int):
    if TLS_CERTIFICATE and TLS_PRIVATE_KEY:
        context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
        context.load_cert_chain(TLS_CERTIFICATE, TLS_PRIVATE_KEY)

        controller_plaintext = Controller(
            CustomHandler("Plaintext or STARTTLS"),
            hostname="0.0.0.0",
            port=port,
            tls_context=context,
        )

        controller_plaintext.start()

        if MAILPORT_TLS > 0:
            controller_tls = Controller(
                CustomHandler("TLS"),
                hostname="0.0.0.0",
                port=MAILPORT_TLS,
                ssl_context=context,
            )
            controller_tls.start()
            logger.info(
                "[i] Starting TLS only Mailserver on port %d",
                MAILPORT_TLS,
            )

        logger.info(
            "[i] Starting plaintext Mailserver (with STARTTLS support) on port %d",
            port,
        )
    else:
        controller_plaintext = Controller(
            CustomHandler("Plaintext"),
            hostname="0.0.0.0",
            port=port,
        )
        controller_plaintext.start()

        logger.info("[i] Starting plaintext Mailserver on port %d", port)

        controller_tls = None

    logger.info("[i] Ready to receive Emails")
    logger.info("")

    try:
        while True:
            await asyncio.sleep(1)
    except KeyboardInterrupt:
        controller_plaintext.stop()
        if MAILPORT_TLS > 0 and TLS_CERTIFICATE and TLS_PRIVATE_KEY and controller_tls:
            controller_tls.stop()


if __name__ == "__main__":
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    formatter = logging.Formatter(
        "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
        )
    ch.setFormatter(formatter)
    logger.setLevel(logging.DEBUG)
    logger.addHandler(ch)

    if not os.path.isfile(CONFIG_PATH):
        logger.info(
            "[ERR] Config.ini not found. "
            "Rename example.config.ini to config.ini. Defaulting to port 25"
        )
        port = 25
    else:
        Config = configparser.ConfigParser(allow_no_value=True)
        Config.read(CONFIG_PATH)

        port = int(Config.get("MAILSERVER", "MAILPORT", fallback="25"))

        DISCARD_UNKNOWN = Config.getboolean(
            "MAILSERVER", "DISCARD_UNKNOWN", fallback=False
        )

        domains_raw = Config.get("GENERAL", "DOMAINS", fallback="").lower()
        DOMAINS = [d.strip() for d in domains_raw.split(",") if d.strip()]

        URL = Config.get("GENERAL", "URL", fallback="")

        ATTACHMENTS_MAX_SIZE = int(
            Config.get("MAILSERVER", "ATTACHMENTS_MAX_SIZE", fallback="0")
        )

        MAILPORT_TLS = int(
            Config.get("MAILSERVER", "MAILPORT_TLS", fallback="0")
        )
        TLS_CERTIFICATE = Config.get("MAILSERVER", "TLS_CERTIFICATE", fallback="")
        TLS_PRIVATE_KEY = Config.get("MAILSERVER", "TLS_PRIVATE_KEY", fallback="")

        if Config.has_section("WEBHOOK") and Config.has_option(
            "WEBHOOK", "WEBHOOK_URL"
        ):
            WEBHOOK_URL = Config.get("WEBHOOK", "WEBHOOK_URL")
        else:
            WEBHOOK_URL = ""

    logger.info("[i] Discard unknown domains: %s", DISCARD_UNKNOWN)
    logger.info("[i] Max size of attachments: %d", ATTACHMENTS_MAX_SIZE)
    logger.info("[i] Listening for domains: %s", DOMAINS)

    asyncio.run(run(port))
