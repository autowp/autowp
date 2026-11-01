package grpcerrors

import (
	"errors"

	"github.com/autowp/goautowp/internal/auth"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

// Authentication distinguishes invalid credentials from failures while resolving the user.
func Authentication(err error) error {
	if errors.Is(err, auth.ErrMissingMetadata) || errors.Is(err, auth.ErrInvalidToken) {
		return status.Error(codes.Unauthenticated, "invalid or missing authentication token")
	}

	return status.Error(codes.Internal, err.Error())
}

func FieldViolations(violations []*errdetails.BadRequest_FieldViolation) error {
	st := status.New(codes.InvalidArgument, "invalid request")

	st, err := st.WithDetails(&errdetails.BadRequest{FieldViolations: violations})
	if err != nil {
		return status.Error(codes.Internal, err.Error())
	}

	return st.Err()
}
