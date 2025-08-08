#!/bin/bash

set -e

GOPATH=~/go

protoc --proto_path=node_modules/google-proto-files/ \
  --proto_path=$GOPATH/pkg/mod/github.com/grpc-ecosystem/grpc-gateway/v2@v2.27.1 \
  --proto_path=. \
  --plugin=protoc-gen-ng=./node_modules/.bin/protoc-gen-ng \
  --plugin=protoc-gen-openapiv3=$GOPATH/bin/protoc-gen-openapiv3 \
  --ng_out=src/grpc \
  --openapiv3_out . \
  -I ../goautowp spec.proto

#docker run --rm --net=host -u="$(id -u)" -v ${PWD}:/local swaggerapi/swagger-codegen-cli-v3:latest generate \
#    -i /local/spec.swagger.json \
#    -l typescript-angular \
#    -o /local/src/rest \
#    --additional-properties ngVersion=20
