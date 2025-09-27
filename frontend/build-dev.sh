#!/bin/bash

set -e

rm -rf dist/browser/*

./node_modules/.bin/ng build --base-href=/ --configuration=development

cp -R ./dist/* ../autowp/frontend/
