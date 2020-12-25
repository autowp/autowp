FROM php:8-fpm-alpine

LABEL maintainer="dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 9000

ENV COMPOSER_ALLOW_SUPERUSER="1" \
    WAITFORIT_VERSION="v2.4.1"

CMD ["./start.sh"]

RUN apk update && apk add \
        alpine-sdk \
        autoconf \
        ca-certificates \
        curl \
        git \
        imagemagick-dev \
        libtool \
        libxml2 \
        openssh-client \
        tzdata \
        unzip \
        xmlstarlet

RUN pecl install imagick-3.4.4 && \
    docker-php-source extract && \
    docker-php-ext-enable opcache && \
    docker-php-ext-install bcmath curl imagick intl gd mbstring memcached pdo_mysql sockets tokenizer xml zip && \
    docker-php-source delete

RUN cat /etc/ImageMagick-6/policy.xml | \
        xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='memory']/@value" -v "2GiB" | \
        xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='disk']/@value" -v "10GiB" > /etc/ImageMagick-6/policy2.xml && \
    cat /etc/ImageMagick-6/policy2.xml > /etc/ImageMagick-6/policy.xml && \
    \
    curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/$WAITFORIT_VERSION/waitforit-linux_amd64 && \
    chmod +x /usr/local/bin/waitforit && \
    \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

COPY ./etc/ /etc/

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-progress --no-interaction --optimize-autoloader && \
    composer clearcache

COPY . /app

RUN mkdir -p --mode=0777 /app/cache/modulecache && \
    chmod +x zf && \
    chmod +x start.sh

ARG COMMIT
