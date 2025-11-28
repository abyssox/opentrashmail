#!/usr/bin/env bash
set -euo pipefail

echo 'Starting OpenTrashmail'

APP_DIR=/var/www/opentrashmail
LOG_DIR="$APP_DIR/logs"
DATA_DIR="$APP_DIR/data"
NGINX_LOG_DIR=/var/log/nginx/opentrashmail
CONFIG_FILE="$APP_DIR/config.ini"

cd "$APP_DIR"

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

[CLEANUP]
DELETE_OLDER_THAN_DAYS=${DELETE_OLDER_THAN_DAYS:-false}

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
  echo ' [+] Fixing file permissions'
  mkdir -p "$DATA_DIR" "$LOG_DIR"
  chown -R nginx:nginx "$DATA_DIR" "$LOG_DIR" "$CONFIG_FILE"
fi

echo ' [+] Starting php-fpm'
php-fpm -D

echo ' [+] Starting webserver'
mkdir -p "$NGINX_LOG_DIR"
touch "$NGINX_LOG_DIR/web.access.log" "$NGINX_LOG_DIR/web.error.log"

mkdir -p /run/nginx
nginx

echo ' [+] Starting Mailserver'

su - nginx -s /bin/ash -c '
  cd /var/www/opentrashmail/python
  /opt/pyenv/bin/python -u mailserver3.py >> /var/www/opentrashmail/logs/mailserver.log 2>&1
'
