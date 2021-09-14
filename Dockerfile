FROM ubuntu:focal

LABEL maintainer="dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 9000

ENV COMPOSER_ALLOW_SUPERUSER="1" \
    WAITFORIT_VERSION="v2.4.1" \
    DEBIAN_FRONTEND=noninteractive \
    SONAR_SCANNER_VERSION="4.6.2.2472"

CMD ["./start.sh"]

COPY sonar-scanner.zip /opt/sonar-scanner.zip
COPY waitforit /usr/local/bin/waitforit

RUN apt-get autoremove -qq -y && \
    apt-get update -qq -y && \
    apt-get dist-upgrade -qq -y && \
    apt-get install -qq -y \
        bash \
        ca-certificates \
        curl \
        git \
        imagemagick \
        libtool \
        libxml2 \
        mysql-client \
        openjdk-11-jre \
        openssh-client \
        php-dev \
        php-pear \
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
    apt-get autoclean -qq -y && \
    pecl install ast && \
    \
    cat /etc/ImageMagick-6/policy.xml | \
        xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='memory']/@value" -v "2GiB" | \
        xmlstarlet ed -u "/policymap/policy[@domain='resource'][@name='disk']/@value" -v "10GiB" > /etc/ImageMagick-6/policy2.xml && \
    cat /etc/ImageMagick-6/policy2.xml > /etc/ImageMagick-6/policy.xml && \
    \
    # curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/$WAITFORIT_VERSION/waitforit-linux_amd64 && \
    # chmod +x /usr/local/bin/waitforit && \
    \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');" && \
    \
    # mkdir -p /opt && \
    # curl -fSL https://binaries.sonarsource.com/Distribution/sonar-scanner-cli/sonar-scanner-cli-4.6.2.2472.zip -o /opt/sonar-scanner.zip && \
    unzip /opt/sonar-scanner.zip -d /opt && \
    rm /opt/sonar-scanner.zip && \
    ln -s /opt/sonar-scanner-${SONAR_SCANNER_VERSION}/bin/sonar-scanner /usr/bin/sonar-scanner
    # && \
    #\
    #curl -Ls https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter && \
    #chmod +x ./cc-test-reporter

COPY ./etc/ /etc/

COPY composer.json composer.lock phpcs.xml ./
COPY module/Commons/src/functions.php module/Commons/src/functions.php
RUN composer install --no-progress --no-interaction --optimize-autoloader && \
    composer clearcache

COPY . /app

RUN mkdir -p --mode=0777 /app/cache/modulecache && \
    chmod +x zf && \
    chmod +x start.sh

ARG COMMIT
