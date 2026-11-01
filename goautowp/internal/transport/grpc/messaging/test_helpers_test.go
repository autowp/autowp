package messaging

import (
	"sync"
	"sync/atomic"
	"testing"
	"time"
)

type queryLogger struct {
	queries     atomic.Int64
	beforeQuery func()
}

func (s *queryLogger) Printf(_ string, _ ...interface{}) {
	s.queries.Add(1)

	if s.beforeQuery != nil {
		s.beforeQuery()
	}
}

// Gate before SQL acquisition so a one-connection fixture can detect concurrent reads.
func blockQueries(t *testing.T, logger *queryLogger, count int) func() {
	t.Helper()

	allStarted := make(chan struct{})
	release := make(chan struct{})

	var once sync.Once

	unblock := func() { once.Do(func() { close(release) }) }
	t.Cleanup(unblock)

	var observed atomic.Int64

	logger.beforeQuery = func() {
		current := observed.Add(1)
		if current > int64(count) {
			return
		}

		if current == int64(count) {
			close(allStarted)
		}

		<-release
	}

	return func() {
		t.Helper()

		defer unblock()

		timer := time.NewTimer(5 * time.Second)
		defer timer.Stop()

		select {
		case <-allStarted:
		case <-timer.C:
			t.Fatal("independent message counts did not start together")
		}
	}
}
