FROM ubuntu:focal

LABEL maintainer="dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 80

ENV COMPOSER_ALLOW_SUPERUSER="1" \
    WAITFORIT_VERSION="v2.4.1"

CMD ["./start.sh"]

HEALTHCHECK --interval=5m --timeout=3s \
  CMD curl -f http://localhost/api/brands --silent --output /dev/null --show-error --fail || exit 1

RUN DEBIAN_FRONTEND=noninteractive apt-get autoremove -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get update -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get install -qq -y \
        autoconf \
        automake \
        bash \
        build-essential \
        ca-certificates \
        curl \
        git \
        imagemagick \
        libpng-dev \
        libtool \
        libxml2 \
        logrotate \
        mysql-client \
        nasm \
        nginx \
        openssh-client \
        optipng \
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
        pngquant \
        rsyslog \
        ssmtp \
        supervisor \
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
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'c31c1e292ad7be5f49291169c0ac8f683499edddcfd4e42232982d0fd193004208a58ff6f353fde0012d35fdd72bc394') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

COPY ./etc/ /etc/

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-progress --no-interaction --no-suggest --optimize-autoloader && \
    composer clearcache

COPY . /app

RUN chmod +x zf && \
    chmod +x start.sh

ARG COMMIT
ENV SENTRY_RELEASE=$COMMIT
