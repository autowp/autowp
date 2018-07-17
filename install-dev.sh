#!/bin/bash

set -e

DEBIAN_FRONTEND=noninteractive apt-get install php-xdebug
php ./composer.phar install --no-progress --no-interaction --no-suggest --optimize-autoloader
