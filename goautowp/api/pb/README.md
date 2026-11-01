# Go API contract

This package contains protobuf messages, gRPC clients, and server interfaces generated from
[`spec.proto`](../../../spec.proto). Regenerate both the Go contract and Angular client by running
`./protoc.sh` from the repository root. Generated Go files are excluded from Git.

Import `github.com/autowp/goautowp/api/pb` from clients and transport handlers. This package must
remain independent of application code. Validation and other application behavior belong in
hand-written functions outside this package.

The Go import path is independent of the protobuf package (`goautowp`), which determines the
message names and gRPC method paths on the wire.
