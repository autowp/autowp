FROM ubuntu:bionic

LABEL maintainer "dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 80

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV WAITFORIT_VERSION="v2.4.1"

HEALTHCHECK --interval=5m --timeout=3s \
  CMD curl -f http://localhost/ || exit 1

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
        php \
        php-bcmath \
        php-ctype \
        php-curl \
        php-dom \
        php-exif \
        php-fileinfo \
        php-fpm \
        php-ftp \
        php-iconv \
        php-imagick \
        php-intl \
        php-json \
        php-gd \
        php-mbstring \
        php-memcached \
        php-mysql \
        php-opcache \
        php-pdo \
        php-phar \
        php-simplexml \
        php-tokenizer \
        php-xml \
        php-xmlwriter \
        php-zip \
        pngquant \
        rsyslog \
        ssmtp \
        supervisor \
        tzdata \
        xmlstarlet

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash - && \
    apt-get install -qq -y nodejs && \
    DEBIAN_FRONTEND=noninteractive apt-get autoclean -qq -y && \
    \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --quiet && \
    rm composer-setup.php && \
    \
    cat /etc/ImageMagick-6/policy.xml | xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='memory']/@value" -v '2GiB' > /etc/ImageMagick-6/policy.xml && \
    cat /etc/ImageMagick-6/policy.xml | xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='disk']/@value" -v '10GiB' > /etc/ImageMagick-6/policy.xml

RUN curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/$WAITFORIT_VERSION/waitforit-linux_amd64 && \
    chmod +x /usr/local/bin/waitforit

COPY ./etc/ /etc/

COPY composer.json /app/composer.json
RUN php ./composer.phar install --no-dev --no-progress --no-interaction --no-suggest --optimize-autoloader && \
    php ./composer.phar clearcache

COPY package.json /app/package.json

RUN npm install -y -qq --production && \
    npm cache clean --force

COPY . /app

RUN chmod +x zf && \
    chmod +x start.sh && \
    crontab ./crontab && \
    ./node_modules/.bin/webpack -p && \
    rm -rf ./node_modules/

CMD ["./start.sh"]
