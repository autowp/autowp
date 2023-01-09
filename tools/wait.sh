#!/bin/sh

set -e

waitforit -host=mysql -port=3306 -timeout=60
waitforit -host=goautowp-serve-private -port=8080 -timeout=60
waitforit -host=goautowp-serve-public -port=8080 -timeout=60

waitforit -address tcp://keycloak -timeout 30

echo "waiting for keycloak"
while ! curl -s http://keycloak/auth/realms/autowp/protocol/openid-connect/certs;
do
  sleep 1
  echo "."
done
