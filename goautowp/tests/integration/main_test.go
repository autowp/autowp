package integration

import (
	"context"
	"os"
	"testing"

	"github.com/autowp/goautowp"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/testutil"
	"google.golang.org/grpc"
)

var (
	cnt  *goautowp.Container
	conn *grpc.ClientConn
)

func TestMain(m *testing.M) {
	os.Exit(runTests(m))
}

func runTests(m *testing.M) int {
	cnt = goautowp.NewContainer(config.LoadConfig("../.."))

	if err := logging.SetLevel("debug"); err != nil {
		logging.Error(err.Error())

		return 1
	}

	server, err := cnt.GRPCServerWithServices(context.Background())
	if err != nil {
		logging.Error(err.Error())

		return 1
	}

	client, cleanup, err := testutil.NewGRPCClient(server)
	if err != nil {
		logging.Error(err.Error())

		return 1
	}

	conn = client
	result := m.Run()

	if err := cleanup(); err != nil {
		logging.Error(err.Error())

		return 1
	}

	return result
}
