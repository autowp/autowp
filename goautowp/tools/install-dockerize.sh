#!/bin/sh

set -e

GOBIN=/usr/local/bin go install github.com/jwilder/dockerize@latest
