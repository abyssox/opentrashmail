#!/usr/bin/env bash
set -euo pipefail

echo 'Starting OpenTrashmail'

cd /var/www/opentrashmail

echo ' [+] Starting php-fpm'

php-fpm -D

if [[ "${SKIP_FILEPERMISSIONS:-false}" != "true" ]]; then
  echo ' [+] Fixing file permissions'
  mkdir -p /var/www/opentrashmail/data /var/www/opentrashmail/logs
  chown -R nginx:nginx /var/www
  chown -R nginx:nginx /var/www/opentrashmail/data
fi

echo ' [+] Starting webserver'

mkdir -p /var/log/nginx/opentrashmail
touch /var/log/nginx/opentrashmail/web.access.log
touch /var/log/nginx/opentrashmail/web.error.log

mkdir -p /run/nginx
nginx

echo ' [+] Setting up config.ini'

_buildConfig() {
    echo "[GENERAL]"
    echo "DOMAINS=${DOMAINS:-localhost}"
    echo "URL=${URL:-http://localhost}"
    echo "PASSWORD=${PASSWORD:-}"
    echo "ALLOWED_IPS=${ALLOWED_IPS:-}"
    echo ""
    echo "[MAILSERVER]"
    echo "MAILPORT=${MAILPORT:-25}"
    echo "DISCARD_UNKNOWN=${DISCARD_UNKNOWN:-true}"
    echo "ATTACHMENTS_MAX_SIZE=${ATTACHMENTS_MAX_SIZE:-0}"
    echo "MAILPORT_TLS=${MAILPORT_TLS:-0}"
    echo "TLS_CERTIFICATE=${TLS_CERTIFICATE:-}"
    echo "TLS_PRIVATE_KEY=${TLS_PRIVATE_KEY:-0}"
    echo ""
    echo "[DATETIME]"
    echo "DATEFORMAT=${DATEFORMAT:-D.M.YYYY HH:mm}"
    echo ""
    echo "[CLEANUP]"
    echo "DELETE_OLDER_THAN_DAYS=${DELETE_OLDER_THAN_DAYS:-false}"
    echo ""
    echo "[WEBHOOK]"
    echo "WEBHOOK_URL=${WEBHOOK_URL:-}"
    echo ""
    echo "[ADMIN]"
    echo "ADMIN_ENABLED=${ADMIN_ENABLED:-}"
    echo "ADMIN_PASSWORD=${ADMIN_PASSWORD:-}"
    echo "SHOW_ACCOUNT_LIST=${SHOW_ACCOUNT_LIST:-false}"
    echo "ADMIN=${ADMIN:-}"
    echo "SHOW_LOGS=${SHOW_LOGS:-false}"
}

_buildConfig > /var/www/opentrashmail/config.ini
chown nginx:nginx /var/www/opentrashmail/config.ini || true

echo ' [+] Starting Mailserver'

mkdir -p /var/www/opentrashmail/logs
chown -R nginx:nginx /var/www/opentrashmail/logs

su - nginx -s /bin/ash -c '
  cd /var/www/opentrashmail/python
  /opt/pyenv/bin/python -u mailserver3.py >> /var/www/opentrashmail/logs/mailserver.log 2>&1
'
