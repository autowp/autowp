package goautowp

import (
	"net"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/peer"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestTimezones(t *testing.T) {
	t.Parallel()

	grpcClient := pb.NewAutowpClient(conn)

	res, err := grpcClient.GetTimezones(t.Context(), &emptypb.Empty{})
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestFeedbackNoBody(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &pb.CreateFeedbackRequest{})
	require.Error(t, err)
}

func TestFeedbackEmptyValues(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &pb.CreateFeedbackRequest{Feedback: &pb.Feedback{
		Name:    "",
		Email:   "",
		Message: "",
	}})
	require.Error(t, err)
}

func TestFeedbackEmptyName(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &pb.CreateFeedbackRequest{Feedback: &pb.Feedback{
		Name:    "",
		Email:   "test@example.com",
		Message: "message",
	}})
	require.Error(t, err)
}

func TestFeedbackEmptyEmail(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &pb.CreateFeedbackRequest{Feedback: &pb.Feedback{
		Name:    "",
		Email:   "",
		Message: "message",
	}})
	require.Error(t, err)
}

func TestFeedbackEmptyMessage(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &pb.CreateFeedbackRequest{Feedback: &pb.Feedback{
		Name:    "user",
		Email:   "test@example.com",
		Message: "",
	}})
	require.Error(t, err)
}

/*func TestFeedbackMessage(t *testing.T) {
	config := LoadConfig()
	config.Feedback.Captcha = false

	ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.IPAddr{IP: net.IPv4(192, 168, 0, 1)}})

	_, err = srv.CreateFeedback(ctx, &APICreateFeedbackRequest{
		Name:    "user",
		Email:   "test@example.com",
		Message: "message",
	})
	require.NoError(t, err)
}*/
