#!/bin/bash

# go install google.golang.org/protobuf/cmd/protoc-gen-go@latest
# go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@latest
# go get -u github.com/googleapis/googleapis@latest
# export PATH=$PATH:/home/dvp/go/go1.24.0/bin/:/home/dvp/go/bin

# api-linter -I=$GOPATH/pkg/mod/github.com/googleapis/googleapis@v0.0.0-20240823220356-a67e27687c1b/ \
#            -I=. \
#            spec.proto

protoc --proto_path="$GOPATH/pkg/mod/github.com/googleapis/googleapis@v0.0.0-20250317144420-2d314e62e3e9/" \
       --proto_path="$GOPATH/pkg/mod/github.com/grpc-ecosystem/grpc-gateway/v2@v2.27.1" \
       --proto_path=. \
       --grpc-gateway_out=. \
       --grpc-gateway_opt paths=source_relative \
       --go_out=. \
       --go_opt=paths=source_relative \
       --go-grpc_out=. \
       --go-grpc_opt=paths=source_relative \
       spec.proto
