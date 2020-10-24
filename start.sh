#!/bin/sh

set -e

echo "Create dirs"

mkdir -p /var/run/php
mkdir -p /run/php
mkdir -p /run/nginx

mkdir -p /var/log/nginx
mkdir -p /var/log/php7
mkdir -p /var/log/supervisor
mkdir -p /app/logs && chmod 0777 /app/logs

mkdir -p /app/public_html/img

echo "Waiting for mysql"

waitforit -host=mysql -port=3306 -timeout=60
waitforit -address=http://traffic -timeout=60

echo "Starting supervisor"

/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
