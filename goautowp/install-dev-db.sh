#!/bin/sh

set -e

mysql -uroot --host=127.0.0.1 --port=3306 -ppassword autowp < test/dump.sql
