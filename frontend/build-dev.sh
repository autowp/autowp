#!/bin/bash

set -e

rm -rf dist/browser/*/*
rm -rf dist/server/*/*
rm -rf dist/server/*.mjs
rm -rf dist/server/*.json

npx ng build --base-href=/ --configuration=development
