#!/bin/bash

set -e

mysql -uautowp_test --host=mysql --port=3306 -ptest autowp_test < module/Application/test/_files/dump.sql
