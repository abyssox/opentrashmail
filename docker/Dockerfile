FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash python3 py3-pip socat wget curl git nginx

RUN python3 -m venv /opt/pyenv \
 && /opt/pyenv/bin/pip install --no-cache-dir aiosmtpd aiohttp

ENV PATH="/opt/pyenv/bin:${PATH}"

RUN mkdir -p /var/www/opentrashmail
WORKDIR /var/www/opentrashmail
COPY . .

COPY docker/rootfs/start.sh /etc/start.sh
RUN chmod +x /etc/start.sh

COPY docker/rootfs/nginx.conf /etc/nginx/http.d/default.conf
RUN mkdir -p /run/nginx /var/log/nginx

RUN sed -i 's/;catch_workers_output = yes/catch_workers_output = yes/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 80 25 465
ENTRYPOINT ["/etc/start.sh"]
