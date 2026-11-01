package pictures

import (
	"context"
	"testing"
	"time"

	"github.com/redis/go-redis/v9"
	"github.com/stretchr/testify/require"
)

func TestSubscribeReturnsOnQuit(t *testing.T) {
	t.Parallel()

	// Bogus/unreachable address: go-redis connects lazily, so constructing the client
	// and calling Subscribe()/Channel() never blocks — only an actual read would. This
	// verifies Subscribe() honors quit without needing a live Redis connection.
	redisClient := redis.NewClient(&redis.Options{Addr: "127.0.0.1:1"})
	defer redisClient.Close()

	hub := NewHub()
	quit := make(chan bool)
	close(quit)

	done := make(chan error, 1)

	go func() {
		done <- Subscribe(context.Background(), redisClient, hub, quit)
	}()

	select {
	case err := <-done:
		require.NoError(t, err)
	case <-time.After(testWaitTimeout):
		t.Fatal("Subscribe did not return promptly after quit was closed")
	}
}
