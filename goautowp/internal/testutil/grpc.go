package testutil

import (
	"context"
	"errors"
	"net"

	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/test/bufconn"
)

// NewGRPCClient serves a registered server in memory. Close the returned client and server
// using the cleanup function after all requests have finished.
func NewGRPCClient(server *grpc.Server) (*grpc.ClientConn, func() error, error) {
	const bufferSize = 1024 * 1024

	listener := bufconn.Listen(bufferSize)

	conn, err := grpc.NewClient(
		"localhost",
		grpc.WithContextDialer(func(ctx context.Context, _ string) (net.Conn, error) {
			return listener.DialContext(ctx)
		}),
		grpc.WithTransportCredentials(insecure.NewCredentials()),
	)
	if err != nil {
		return nil, nil, errors.Join(err, listener.Close())
	}

	done := make(chan error, 1)
	go func() { done <- server.Serve(listener) }()

	cleanup := func() error {
		err := conn.Close()

		server.Stop()

		serveErr := <-done
		if errors.Is(serveErr, grpc.ErrServerStopped) {
			serveErr = nil
		}

		return errors.Join(err, serveErr)
	}

	return conn, cleanup, nil
}
