#!/bin/bash

set -e

DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-ast php-xdebug mysql-client
composer install --no-progress --no-interaction --optimize-autoloader

curl -Ls https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter

export SONAR_SCANNER_VERSION="4.6.2.2472"

mkdir -p /opt
curl -fSL https://binaries.sonarsource.com/Distribution/sonar-scanner-cli/sonar-scanner-cli-${SONAR_SCANNER_VERSION}.zip -o /opt/sonar-scanner.zip
unzip /opt/sonar-scanner.zip -d /opt
rm /opt/sonar-scanner.zip
ln -s /opt/sonar-scanner-${SONAR_SCANNER_VERSION}/bin/sonar-scanner /usr/bin/sonar-scanner
