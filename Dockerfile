FROM ubuntu:focal

LABEL maintainer="dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 9000

ENV COMPOSER_ALLOW_SUPERUSER="1" \
    WAITFORIT_VERSION="v2.4.1"

CMD ["./start.sh"]

RUN DEBIAN_FRONTEND=noninteractive apt-get autoremove -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get update -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get install -qq -y \
        bash \
        ca-certificates \
        curl \
        git \
        imagemagick \
        libtool \
        libxml2 \
        mysql-client \
        openssh-client \
        php7.4 \
        php7.4-bcmath \
        php7.4-common \
        php7.4-curl \
        php7.4-fpm \
        php7.4-imagick \
        php7.4-intl \
        php7.4-json \
        php7.4-gd \
        php7.4-mbstring \
        php7.4-memcached \
        php7.4-mysql \
        php7.4-opcache \
        php7.4-tokenizer \
        php7.4-xml \
        php7.4-zip \
        ssmtp \
        tzdata \
        unzip \
        xmlstarlet \
    && \
    DEBIAN_FRONTEND=noninteractive apt-get autoclean -qq -y && \
    \
    cat /etc/ImageMagick-6/policy.xml | \
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

RUN mkdir cache/modulecache && \
    chmod 0777 cache/modulecache && \
    chmod +x zf && \
    chmod +x start.sh

ARG COMMIT
