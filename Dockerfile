FROM alpine

LABEL maintainer "dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 80

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV WAITFORIT_VERSION="v2.1.0"

RUN apk update && apk upgrade && \
    apk add \
        autoconf \
        automake \
        bash \
        build-base \
        ca-certificates \
        curl \
        git \
        go \
        imagemagick \
        libpng-dev \
        libtool \
        libxml2 \
        logrotate \
        nasm \
        nginx \
        openssh \
        php7 \
        php7-ctype \
        php7-curl \
        php7-dom \
        php7-exif \
        php7-fileinfo \
        php7-fpm \
        php7-ftp \
        php7-iconv \
        php7-imagick \
        php7-intl \
        php7-json \
        php7-gd \
        php7-mbstring \
        php7-memcached \
        php7-opcache \
        php7-openssl \
        php7-pcntl \
        php7-pdo \
        php7-pdo_mysql \
        php7-phar \
        php7-simplexml \
        php7-tokenizer \
        php7-xml \
        php7-xmlwriter \
        php7-zip \
        php7-zlib \
        rsyslog \
        ssmtp \
        supervisor \
        tzdata \
    && \
    apk add nodejs 'nodejs-npm<8' --update-cache --repository http://dl-3.alpinelinux.org/alpine/v3.6/main/ \
    && \
    apk add optipng --update-cache --repository http://dl-3.alpinelinux.org/alpine/edge/community/ \
    && \
    apk add pngquant --update-cache --repository http://dl-3.alpinelinux.org/alpine/edge/community/ \
    && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --quiet && \
    rm composer-setup.php \
    && \
    mkdir -p node_modules/pngquant-bin/vendor/ && \
    mkdir -p node_modules/optipng-bin/vendor/ && \
    ln -s /usr/bin/pngquant node_modules/pngquant-bin/vendor/pngquant && \
    ln -s /usr/bin/optipng node_modules/optipng-bin/vendor/optipng && \
    \
    curl -Lk -o phantomjs.tar.gz https://github.com/fgrehm/docker-phantomjs2/releases/download/v2.0.0-20150722/dockerized-phantomjs.tar.gz \
    && tar -xf phantomjs.tar.gz -C /tmp/ \
    && cp -R /tmp/etc/fonts /etc/ \
    && cp -R /tmp/lib/* /lib/ \
    && cp -R /tmp/lib64 / \
    && cp -R /tmp/usr/lib/* /usr/lib/ \
    && cp -R /tmp/usr/lib/x86_64-linux-gnu /usr/ \
    && cp -R /tmp/usr/share/* /usr/share/ \
    && cp /tmp/usr/local/bin/phantomjs /usr/bin/ \
    && rm -fr phantomjs.tar.gz  /tmp/* \
    && mkdir -p /app/node_modules/phantomjs-prebuilt/lib/phantom/bin/ \
    && ln -s /usr/bin/phantomjs /app/node_modules/phantomjs-prebuilt/lib/phantom/bin/phantomjs \
    && \
    curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/$WAITFORIT_VERSION/waitforit-linux_amd64 && \
    chmod +x /usr/local/bin/waitforit

RUN go get \
        github.com/gin-gonic/gin \
        github.com/go-sql-driver/mysql \
        github.com/Masterminds/squirrel \
    && echo $GOROOT \
    && echo $GOPATH

COPY ./etc/ /etc/

COPY composer.json /app/composer.json
RUN php ./composer.phar install --no-dev --no-progress --no-interaction --no-suggest --optimize-autoloader && \
    php ./composer.phar clearcache

COPY package.json /app/package.json

RUN npm install -y --production && \
    npm cache clean --force

COPY . /app

RUN chmod +x zf && \
    chmod +x start.sh && \
    crontab ./crontab && \
    go build -o ./goautowp/goautowp ./goautowp/

RUN ./node_modules/.bin/webpack -p

RUN rm -rf ./node_modules/ \
    && apk del \
        autoconf \
        automake \
        build-base \
        libtool \
        nasm \
        nodejs \
        nodejs-npm \
        optipng \
        pngquant

CMD ["./start.sh"]
