#!/bin/bash

set -e

GOPATH=~/go

protoc --proto_path=node_modules/google-proto-files/ \
  --proto_path=$GOPATH/pkg/mod/github.com/grpc-ecosystem/grpc-gateway/v2@v2.27.1 \
  --proto_path=. \
  --plugin=protoc-gen-ng=./node_modules/.bin/protoc-gen-ng \
  --plugin=protoc-gen-openapi=$GOPATH/bin/protoc-gen-openapi \
  --ng_out=src/grpc \
  --openapi_out=. \
  -I ../goautowp spec.proto

./node_modules/.bin/openapi-generator-cli generate -i openapi.yaml -g typescript-angular -o src/rest --type-mappings=DateTime=Date
