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

waitforit -host=$AUTOWP_DB_HOST -port=$AUTOWP_DB_PORT -timeout=60
waitforit -address=$TRAFFIC_URL -timeout=60

maxcounter=90

counter=1
while ! mysql --protocol=tcp --host=$AUTOWP_DB_HOST --port=$AUTOWP_DB_PORT --user=$AUTOWP_DB_USERNAME -p$AUTOWP_DB_PASSWORD -e "show databases;"; do
    printf "."
    sleep 1
    counter=`expr $counter + 1`
    if [ $counter -gt $maxcounter ]; then
        >&2 echo "We have been waiting for MySQL too long already; failing."
        exit 1
    fi;
done

echo -e "\nmysql ready"

echo "Starting supervisor"

/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
