#!/bin/bash

set -e

DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-ast php-xdebug mysql-client
composer install --no-progress --no-interaction --no-suggest --optimize-autoloader

curl -Ls https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter

curl -Ls https://binaries.sonarsource.com/Distribution/sonar-scanner-cli/sonar-scanner-cli-4.2.0.1873-linux.zip > ./sonar-scanner.zip
unzip sonar-scanner.zip
rm sonar-scanner.zip
mv sonar-scanner-* sonar-scanner
