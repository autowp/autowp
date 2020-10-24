#!/bin/bash

set -e

mysql -u$AUTOWP_DB_USERNAME --host=mysql --port=3306 -p$AUTOWP_DB_PASSWORD $AUTOWP_DB_DBNAME < module/Application/test/_files/dump.sql
