#!/bin/bash

set -e

mysql -u$AUTOWP_DB_USERNAME --host=$AUTOWP_DB_HOST:$AUTOWP_DB_PORT -p$AUTOWP_DB_PASSWORD $AUTOWP_DB_DBNAME < module/Application/test/_files/dump.sql
