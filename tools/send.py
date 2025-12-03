#!/usr/bin/env python3
from smtplib import SMTP
from email.message import EmailMessage
from email.utils import make_msgid
import argparse


FROM_ADDR = "Anne Person <anne@example.com>"
TO_ADDR = "Bart Person <b@domain.tld>"


def build_plain_message() -> EmailMessage:
    msg = EmailMessage()
    msg["From"] = FROM_ADDR
    msg["To"] = TO_ADDR
    msg["Subject"] = "Plain text test"
    msg["Message-ID"] = make_msgid("plain-test")

    msg.set_content(
        "Hi Bart,\n"
        "this is Anne.\n\n"
        "This is a plain text email.\n"
    )
    return msg


def build_html_message() -> EmailMessage:
    msg = EmailMessage()
    msg["From"] = FROM_ADDR
    msg["To"] = TO_ADDR
    msg["Subject"] = "HTML test"
    msg["Message-ID"] = make_msgid("html-test")

    html_body = """\
<!DOCTYPE html>
<html>
  <body>
    <p>Hi Bart, this is <strong>Anne</strong>.</p>
    <p>This is a pure <b>HTML</b> email.</p>
  </body>
</html>
"""
    msg.set_content(html_body, subtype="html")
    return msg


def build_multipart_message() -> EmailMessage:
    msg = EmailMessage()
    msg["From"] = FROM_ADDR
    msg["To"] = TO_ADDR
    msg["Subject"] = "Multipart (text + HTML) test"
    msg["Message-ID"] = make_msgid("multipart-test")

    msg.set_content(
        "Hi Bart,\n"
        "this is Anne.\n\n"
        "This is the plain text part of a multipart email.\n"
    )

    html_body = """\
<!DOCTYPE html>
<html>
  <body>
    <p>Hi Bart, this is <strong>Anne</strong>.</p>
    <p>This is the <b>HTML</b> part of a multipart email.</p>
  </body>
</html>
"""
    msg.add_alternative(html_body, subtype="html")
    return msg


def build_attachment_message() -> EmailMessage:
    msg = EmailMessage()
    msg["From"] = FROM_ADDR
    msg["To"] = TO_ADDR
    msg["Subject"] = "Attachment test email"
    msg["Message-ID"] = make_msgid("attachment-test")

    msg.set_content(
        "Hi Bart,\n"
        "this is Anne.\n\n"
        "This email contains a small test attachment called "
        "'test-attachment.txt'.\n"
    )

    content = """\
# opentrashmail test attachment
#
# This file is intentionally filled with multiple lines of text
# so that it looks like a "real" file in mail clients.
#
# The End
"""

    msg.add_attachment(
        content.encode("utf-8"),
        maintype="text",
        subtype="plain",
        filename="test-attachment.txt",
    )

    return msg


def main():
    parser = argparse.ArgumentParser(
        description="Send a test email (plain, HTML, multipart, or attachment) via localhost:2525"
    )

    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-plain", "--plain", action="store_true",
                       help="send plain text email")
    group.add_argument("-html", "--html", action="store_true",
                       help="send HTML-only email")
    group.add_argument("-multipart", "--multipart", action="store_true",
                       help="send multipart (text + HTML) email")
    group.add_argument("-attachment", "--attachment", action="store_true",
                       help="send email with test attachment")

    args = parser.parse_args()

    if args.plain:
        msg = build_plain_message()
    elif args.html:
        msg = build_html_message()
    elif args.multipart:
        msg = build_multipart_message()
    elif args.attachment:
        msg = build_attachment_message()
    else:
        parser.error("Parameter missing. Use either -plain / -html / -multipart / -attachment")

    with SMTP("localhost", 2525) as client:
        client.send_message(msg)
        print("Mail sent!")


if __name__ == "__main__":
    main()
