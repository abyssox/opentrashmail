#!/usr/bin/env bash
set -euo pipefail

echo 'Starting OpenTrashmail'

: "${TZ:=UTC}"
PUID="${PUID:-1000}"
PGID="${PGID:-1000}"
APP_DIR=/var/www/opentrashmail
LOG_DIR="$APP_DIR/logs"
CONFIG_FILE="$APP_DIR/config.ini"

cd "$APP_DIR"

if [ -n "$TZ" ] && [ -f "/usr/share/zoneinfo/$TZ" ]; then
    echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/99-timezone.ini
    ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
    echo " [+] Timezone set to '$TZ'" >&2
else
    echo " [+] WARNING: Timezone '$TZ' not found in /usr/share/zoneinfo, falling back to UTC" >&2
    ln -snf "/usr/share/zoneinfo/UTC" /etc/localtime
    echo "UTC" > /etc/timezone
fi

echo ' [+] Setting up config.ini'

cat > "$CONFIG_FILE" <<EOF
[GENERAL]
DOMAINS=${DOMAINS:-localhost}
URL=${URL:-http://localhost}
PASSWORD=${PASSWORD:-}
ALLOWED_IPS=${ALLOWED_IPS:-}

[MAILSERVER]
MAILPORT=${MAILPORT:-25}
DISCARD_UNKNOWN=${DISCARD_UNKNOWN:-true}
ATTACHMENTS_MAX_SIZE=${ATTACHMENTS_MAX_SIZE:-0}
MAILPORT_TLS=${MAILPORT_TLS:-0}
TLS_CERTIFICATE=${TLS_CERTIFICATE:-}
TLS_PRIVATE_KEY=${TLS_PRIVATE_KEY:-0}

[DATETIME]
DATEFORMAT=${DATEFORMAT:-D.M.YYYY HH:mm}

[WEBHOOK]
WEBHOOK_URL=${WEBHOOK_URL:-}

[ADMIN]
ADMIN_ENABLED=${ADMIN_ENABLED:-}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-}
SHOW_ACCOUNT_LIST=${SHOW_ACCOUNT_LIST:-false}
ADMIN=${ADMIN:-}
SHOW_LOGS=${SHOW_LOGS:-false}
EOF

if [[ "${SKIP_FILEPERMISSIONS:-false}" != "true" ]]; then
  echo ' [+] Fixing file/folder permissions'

  chown "$PUID":www-data "$LOG_DIR" || true
  chmod 0750 "$LOG_DIR" || true

  for f in cleanup_maildir.log mailserver.log php.error.log web.access.log web.error.log; do
    touch "$LOG_DIR/$f"
    chown www-data:"$PGID" "$LOG_DIR/$f" || true
    chmod 0640 "$LOG_DIR/$f" || true
  done

  chown -R www-data:"$PGID" "$APP_DIR/data" || true
  chmod 2770 "$APP_DIR/data" || true
  chmod -R ug+rwX "$APP_DIR/data" || true
  umask 007

  chown "$PUID:$PGID" "$CONFIG_FILE" 2>/dev/null || true
fi

echo ' [+] Starting crond'
crond -f -l 2 &

echo ' [+] Starting php-fpm'
php-fpm -F &

echo ' [+] Starting Mailserver'

su - www-data -s /bin/ash -c '
  cd /var/www/opentrashmail/python
  /opt/pyenv/bin/python -u mailserver3.py 2>&1 \
    | tee -a /var/www/opentrashmail/logs/mailserver.log
' &

echo ' [+] Starting webserver'
nginx -g 'daemon off;'