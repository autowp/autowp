package goautowp

import (
	"context"
	"net"
	"os"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/util"
	"github.com/sirupsen/logrus"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/test/bufconn"
)

var (
	cnt       *Container
	bufDialer func(context.Context, string) (net.Conn, error)
	conn      *grpc.ClientConn
)

func TestMain(m *testing.M) {
	const bufSize = 1024 * 1024

	var lis *bufconn.Listener

	cfg := config.LoadConfig(".")

	cnt = NewContainer(cfg)

	logrus.SetLevel(logrus.DebugLevel)

	grpcServer, err := cnt.GRPCServerWithServices(context.TODO())
	if err != nil {
		panic(err)
	}

	lis = bufconn.Listen(bufSize)

	bufDialer = func(context.Context, string) (net.Conn, error) {
		return lis.Dial()
	}

	go func() {
		if err := grpcServer.Serve(lis); err != nil {
			logrus.Errorf("Server exited with error: %v", err)
		}
	}()

	/*return cnt, func(context.Context, string) (net.Conn, error) {
		return lis.Dial()
	}*/

	go func() {
		if err := grpcServer.Serve(lis); err != nil {
			logrus.Errorf("Server exited with error: %v", err)
		}
	}()

	conn, err = grpc.NewClient(
		"localhost",
		grpc.WithContextDialer(bufDialer),
		grpc.WithTransportCredentials(insecure.NewCredentials()),
	)
	if err != nil {
		panic(err)
	}

	res := m.Run()

	util.Close(conn)

	os.Exit(res)
}
