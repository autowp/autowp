#!/bin/bash

set -e

DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-ast php-xdebug mysql-client
composer install --no-progress --no-interaction --no-suggest --optimize-autoloader
