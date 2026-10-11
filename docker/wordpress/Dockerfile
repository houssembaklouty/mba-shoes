FROM wordpress:latest

ENV TZ=Africa/Tunis

RUN apt-get update && apt-get install -y \
    curl \
    less \
    mariadb-client \
    && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp \
    && rm -rf /var/lib/apt/lists/*

COPY auto-setup.sh /usr/local/bin/auto-setup.sh
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
RUN chmod +x /usr/local/bin/auto-setup.sh

CMD bash -c "docker-entrypoint.sh apache2-foreground & sleep 30 && auto-setup.sh && wait"
