#!/bin/bash

set -e

kubectl -n autowp apply -f volumes.yaml

helm diff -n autowp upgrade autowp . -f values.production.yaml
