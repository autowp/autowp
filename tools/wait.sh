#!/bin/sh

set -e

waitforit -host=mysql -port=3306 -timeout=60
waitforit -host=rabbitmq -port=5672 -timeout=60
waitforit -host=goautowp-serve -port=8080 -timeout=60

waitforit -host=keycloak -port=8080 -timeout 30

echo "waiting for keycloak"
while ! curl -s http://keycloak:8080/auth/realms/autowp/protocol/openid-connect/certs;
do
  sleep 1
  echo "."
done
