package goautowp

import (
	"context"
	"errors"
	"net"
	"net/http"
	"sync/atomic"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

func TestHTTPShutdownWaitsForLateView(t *testing.T) {
	t.Parallel()

	var listenConfig net.ListenConfig

	listener, err := listenConfig.Listen(t.Context(), "tcp", "127.0.0.1:0")
	require.NoError(t, err)

	entered := make(chan struct{})
	release := make(chan struct{})

	var recorded atomic.Bool

	server := &http.Server{
		ReadHeaderTimeout: time.Second,
		Handler: http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
			close(entered)
			<-release
			recorded.Store(true)
			w.WriteHeader(http.StatusNoContent)
		}),
	}

	t.Cleanup(func() { require.NoError(t, server.Close()) })

	quit := make(chan bool)
	returned := make(chan struct{})
	done := make(chan error, 1)

	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	go func() {
		done <- serveHTTPServer(ctx, server, quit, func() error {
			err := server.Serve(listener)

			close(returned)

			return err
		})
	}()

	clientDone := make(chan error, 1)

	go func() {
		client := &http.Client{Timeout: 5 * time.Second}

		request, err := http.NewRequestWithContext(t.Context(), http.MethodGet, "http://"+listener.Addr().String(), nil)
		if err != nil {
			clientDone <- err

			return
		}

		response, err := client.Do(request)
		if response != nil {
			err = errors.Join(err, response.Body.Close())
		}

		clientDone <- err
	}()

	select {
	case <-entered:
	case <-time.After(5 * time.Second):
		t.Fatal("handler did not start")
	}

	cancel()
	close(quit)

	select {
	case <-returned:
	case <-time.After(5 * time.Second):
		t.Fatal("listener did not stop")
	}

	select {
	case err := <-done:
		close(release)
		t.Fatalf("server returned before handler drained: %v", err)
	default:
	}

	close(release)
	require.NoError(t, <-clientDone)

	select {
	case err := <-done:
		require.NoError(t, err)
	case <-time.After(5 * time.Second):
		t.Fatal("shutdown did not finish")
	}

	require.True(t, recorded.Load(), "final view flush must follow the last handler")
}

func TestHTTPServeFailureDoesNotWaitForQuit(t *testing.T) {
	t.Parallel()

	failure := context.DeadlineExceeded

	done := make(chan error, 1)
	go func() {
		done <- serveHTTPServer(
			t.Context(),
			&http.Server{ReadHeaderTimeout: time.Second},
			make(chan bool),
			func() error { return failure },
		)
	}()

	select {
	case err := <-done:
		require.ErrorIs(t, err, failure)
	case <-time.After(time.Second):
		t.Fatal("serve failure left shutdown waiting for quit")
	}
}
