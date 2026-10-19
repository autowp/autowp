#!/bin/sh

set -e

dockerize -wait tcp://localhost:5672 -timeout 30s
dockerize -wait tcp://localhost:5432 -timeout 30s
dockerize -wait tcp://localhost:8081 -timeout 30s
dockerize -wait tcp://localhost:6379 -timeout 30s

echo "waiting for keycloak"
while ! curl -s http://localhost:8081/auth/realms/autowp/protocol/openid-connect/certs;
do
  sleep 1
  echo "."
done
