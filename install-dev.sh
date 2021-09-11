#!/bin/bash

set -e

DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-ast php-xdebug mysql-client
composer install --no-progress --no-interaction --optimize-autoloader

curl -Ls https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter
