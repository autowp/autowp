#!/bin/sh

set -e

mkdir -p /var/run/php
mkdir -p /run/php
mkdir -p /var/log/php7
mkdir -p /app/public_html/img

echo "Create cache dir"

# shellcheck disable=SC2174
mkdir -p --mode=0777 /app/cache/modulecache
ls -lah /app/cache/modulecache

echo "Waiting for mysql"

waitforit -host=mysql -port=3306 -timeout=60
waitforit -address=http://traffic -timeout=60

php-fpm7.4 --nodaemonize --allow-to-run-as-root
