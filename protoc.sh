#!/bin/bash

set -e

GOPATH=~/go

protoc --proto_path=node_modules/google-proto-files/ \
  --proto_path=$GOPATH/pkg/mod/github.com/grpc-ecosystem/grpc-gateway/v2@v2.27.1 \
  --proto_path=. \
  --plugin=protoc-gen-ng=./node_modules/.bin/protoc-gen-ng \
  --ng_out=src/grpc \
  --openapiv2_out=. \
  --openapiv2_opt allow_merge=true \
  --openapiv2_opt use_proto3_field_semantics=true \
  -I ../goautowp spec.proto

rm -rf src/rest/*
./node_modules/.bin/openapi-generator-cli generate
