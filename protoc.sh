#!/bin/bash

set -e

frontend_options=()
case "$*" in
  "")
    if [[ ! -x frontend/node_modules/.bin/protoc-gen-ng ]]; then
      echo "Missing frontend generator. Run: npm ci --prefix frontend --include=dev" >&2
      exit 1
    fi
    frontend_options=(
      --plugin=protoc-gen-ng=frontend/node_modules/.bin/protoc-gen-ng
      --ng_out=frontend/src/grpc
    )
    ;;
  --go-only) ;;
  *)
    echo "Usage: $0 [--go-only]" >&2
    exit 2
    ;;
esac

# go install google.golang.org/protobuf/cmd/protoc-gen-go@latest
# go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@latest
# go get -u github.com/googleapis/googleapis@latest
# export PATH=$PATH:/home/dvp/go/go1.24.0/bin/:/home/dvp/go/bin

# api-linter -I=$GOPATH/pkg/mod/github.com/googleapis/googleapis@v0.0.0-20240823220356-a67e27687c1b/ \
#            -I=. \
#            spec.proto

mkdir -p goautowp/api/pb

protoc --proto_path="/usr/local/include" \
       --proto_path=. \
       --go_out=goautowp/api/pb \
       --go_opt=paths=source_relative \
       --go-grpc_out=goautowp/api/pb \
       --go-grpc_opt=paths=source_relative \
       "${frontend_options[@]}" \
       spec.proto

# Remove stubs generated at the old location to avoid duplicate descriptor registration.
rm -f goautowp/spec.pb.go goautowp/spec_grpc.pb.go
