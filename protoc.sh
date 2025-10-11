#!/bin/bash

set -e

# go install google.golang.org/protobuf/cmd/protoc-gen-go@latest
# go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@latest
# go get -u github.com/googleapis/googleapis@latest
# export PATH=$PATH:/home/dvp/go/go1.24.0/bin/:/home/dvp/go/bin

# api-linter -I=$GOPATH/pkg/mod/github.com/googleapis/googleapis@v0.0.0-20240823220356-a67e27687c1b/ \
#            -I=. \
#            spec.proto

protoc --proto_path="/usr/local/include" \
       --proto_path="$GOPATH/pkg/mod/github.com/grpc-ecosystem/grpc-gateway/v2@v2.27.3" \
       --proto_path=. \
       --grpc-gateway_out=goautowp \
       --grpc-gateway_opt paths=source_relative \
       --go_out=goautowp \
       --go_opt=paths=source_relative \
       --go-grpc_out=goautowp \
       --go-grpc_opt=paths=source_relative \
       --ng_out=frontend/src/grpc \
       --openapiv2_out=. \
       --openapiv2_opt allow_merge=true \
       --openapiv2_opt use_proto3_field_semantics=true \
       spec.proto

rm -rf frontend/src/rest/*

openapi-generator-cli generate
