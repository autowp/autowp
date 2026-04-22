#!/bin/sh

set -e

waitforit -address tcp://localhost:5672 -timeout 30
waitforit -address tcp://localhost:5432 -timeout 30
waitforit -address tcp://localhost:8081 -timeout 30
waitforit -address tcp://localhost:6379 -timeout 30

echo "waiting for keycloak"
while ! curl -s http://localhost:8081/auth/realms/autowp/protocol/openid-connect/certs;
do
  sleep 1
  echo "."
done
