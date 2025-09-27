#!/bin/sh

set -e

curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/v2.4.1/waitforit-linux_amd64
chmod +x /usr/local/bin/waitforit
