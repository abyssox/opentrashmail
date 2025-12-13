FROM php:8.4-fpm-alpine

LABEL org.opencontainers.image.source="https://github.com/abyssox/opentrashmail"

ENV TZ=${TZ} \
    PYTHONUNBUFFERED=1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PATH="${PATH}:/opt/pyenv/bin"

RUN set -eux; \
    \
    apk add --no-cache \
        bash \
        curl \
        ca-certificates \
        python3 \
        py3-pip \
        nginx \
        findutils \
        logrotate \
        tzdata \
    ; \
    \
    python3 -m venv /opt/pyenv; \
    /opt/pyenv/bin/pip install --no-cache-dir \
        aiosmtpd \
        aiohttp \
    ; \
    \
    mkdir -p \
        /var/www/opentrashmail/data \
        /var/www/opentrashmail/logs \
        /run/nginx \
        /var/log/nginx \
        /var/lib/logrotate \
    ; \
    touch /var/lib/logrotate/status; \
    \
    sed -i 's/;catch_workers_output = yes/catch_workers_output = yes/' /usr/local/etc/php-fpm.d/www.conf; \
    \
    { \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
        echo 'max_input_vars=2000'; \
        echo 'log_errors=On'; \
        echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_WARNING'; \
        echo 'display_errors=Off'; \
        echo 'error_log=/var/www/opentrashmail/logs/php.error.log'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/opentrashmail.ini; \
    \
    { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 20'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 6'; \
        echo 'pm.max_requests = 500'; \
        echo 'request_terminate_timeout = 60'; \
        echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-opentrashmail.conf; \
    \
    echo '* * * * * /usr/local/bin/cleanup_maildir.sh' >> /etc/crontabs/root; \
    \
    curl -sS https://getcomposer.org/installer \
        | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/opentrashmail

COPY --chown=www-data:www-data . .

COPY docker/rootfs/ /

RUN set -eux; \
    \
    if [ -f /usr/local/bin/cleanup_mail/dir.sh ]; then chmod 0755 /usr/local/bin/cleanup_maildir.sh; fi; \
    \
    if [ -f /etc/periodic/daily/logrotate ]; then chmod 0755 /etc/periodic/daily/logrotate; fi; \
    \
    if [ -f /etc/logrotate.conf ]; then chmod 0644 /etc/logrotate.conf; fi; \
    \
    if [ -f /etc/logrotate.d/opentrashmail ]; then chmod 0644 /etc/logrotate.d/opentrashmail; fi; \
    \
    if [ -f /var/lib/logrotate/status ]; then chmod 0700 /var/lib/logrotate/status; fi; \
    \
    if [ -f /etc/start.sh ]; then chmod 0755 /etc/start.sh; fi; \
    \
    mkdir -p /etc/nginx/http.d

USER www-data
RUN set -eux; \
    composer install --no-dev --prefer-dist --optimize-autoloader
USER root

EXPOSE 80 25 465

ENTRYPOINT ["/etc/start.sh"]
