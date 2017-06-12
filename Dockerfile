FROM ubuntu

LABEL maintainer "dmitry@pereslegin.ru"

WORKDIR /app

RUN apt-get update && apt-get dist-upgrade -y && apt-get install --no-install-recommends --no-install-suggests  -y \
    anacron \
    apt-utils \
    bash \
    build-essential \
    ca-certificates \
    cron \
    curl \
    dh-autoreconf \
    git \
    imagemagick \
    nginx \
    php7.0-cli \
    php7.0-curl \
    php7.0-fpm \
    php7.0-intl \
    php7.0-json \
    php7.0-gd \
    php7.0-mbstring \
    php7.0-mysql \
    php7.0-xml \
    php7.0-zip \
    php-imagick \
    php-memcache \
    php-memcached \
    supervisor

RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --quiet && \
    rm composer-setup.php

RUN curl -sL https://deb.nodesource.com/setup_6.x | bash - && \
    apt-get install --no-install-recommends --no-install-suggests -y nodejs

ENV COMPOSER_ALLOW_SUPERUSER 1
ADD composer.json /app/composer.json
RUN php ./composer.phar install --no-dev

ADD package.json /app/package.json
RUN cd /app && npm install --production

ADD . /app

ADD ./crontab.txt /etc/cron.d/crontab

RUN rm /etc/nginx/sites-enabled/default
ADD ./nginx.conf /etc/nginx/conf.d/default.conf

ADD ./php.ini /etc/php/7.0/fpm/php.ini
ADD ./php.ini /etc/php/7.0/cli/php.ini

RUN mkdir -p /var/run/php/ && \
    rm /etc/php/7.0/fpm/pool.d/www.conf
ADD fpm-pool.conf /etc/php/7.0/fpm/pool.d/autowp.conf

RUN ./node_modules/.bin/webpack -p

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
