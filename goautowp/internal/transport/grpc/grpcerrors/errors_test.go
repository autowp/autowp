package grpcerrors_test

import (
	"database/sql"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

func TestAuthentication(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name       string
		err        error
		code       codes.Code
		httpStatus int
		message    string
	}{
		{
			name: "missing metadata", err: auth.ErrMissingMetadata, code: codes.Unauthenticated,
			httpStatus: http.StatusUnauthorized, message: "invalid or missing authentication token",
		},
		{
			name: "invalid token", err: auth.ErrInvalidToken, code: codes.Unauthenticated,
			httpStatus: http.StatusUnauthorized, message: "invalid or missing authentication token",
		},
		{
			name: "wrapped token failure", err: fmt.Errorf("verify token: %w", auth.ErrInvalidToken),
			code: codes.Unauthenticated, httpStatus: http.StatusUnauthorized,
			message: "invalid or missing authentication token",
		},
		{
			name: "repository failure", err: sql.ErrConnDone, code: codes.Internal,
			httpStatus: http.StatusInternalServerError, message: sql.ErrConnDone.Error(),
		},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			actual := status.Convert(grpcerrors.Authentication(test.err))
			require.Equal(t, test.code, actual.Code())
			require.Equal(t, test.message, actual.Message())

			recorder := httptest.NewRecorder()
			ctx, _ := gin.CreateTestContext(recorder)
			new(auth.Service).RESTError(ctx, test.err)
			require.Equal(t, test.httpStatus, recorder.Code)
			require.Equal(t, test.message, recorder.Body.String())
		})
	}
}

func TestFieldViolations(t *testing.T) {
	t.Parallel()

	violations := []*errdetails.BadRequest_FieldViolation{
		{Field: "name", Description: "required"},
		{Field: "language", Description: "unsupported"},
	}
	actual := status.Convert(grpcerrors.FieldViolations(violations))
	require.Equal(t, codes.InvalidArgument, actual.Code())
	require.Equal(t, "invalid request", actual.Message())
	serialized, err := proto.Marshal(actual.Proto())
	require.NoError(t, err)

	decoded := actual.Proto()
	proto.Reset(decoded)
	require.NoError(t, proto.Unmarshal(serialized, decoded))
	details := status.FromProto(decoded).Details()
	require.Len(t, details, 1)
	detail, ok := details[0].(*errdetails.BadRequest)
	require.True(t, ok)
	require.True(t, proto.Equal(&errdetails.BadRequest{FieldViolations: violations}, detail))
}
