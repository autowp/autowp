#!/bin/bash

set -e

rm -rf dist/browser/*

npx ng build --base-href=/ --configuration=development
