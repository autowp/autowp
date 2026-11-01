package goautowp

import (
	"database/sql"
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func newItemOfDayTestExtractor(t *testing.T) (*itemExtractorFixture, *items.Item, *goqu.Database, *sql.DB) {
	t.Helper()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	suffix := time.Now().UnixNano()
	brandID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Day brand %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_BRAND,
		Catname: fmt.Sprintf("day-%d", suffix),
	})
	vehicleID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Day vehicle %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)
	_, err = pb.NewItemsClient(conn).CreateItemParent(apiCtx, &pb.ItemParent{
		ItemId: vehicleID, ParentId: brandID, Catname: "model",
	})
	require.NoError(t, err)

	for _, perspective := range []int32{schema.PerspectiveFrontStrict, schema.PerspectiveBackStrict} {
		addPicture(t, cnt, conn, "./test/test.jpg",
			pictureshttp.PicturePostForm{ItemID: vehicleID, PerspectiveID: perspective},
			pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	}

	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	repository, err := container.ItemsRepository(ctx)
	require.NoError(t, err)
	row, err := repository.Item(ctx, &query.ItemListOptions{ItemID: vehicleID, Language: "en"}, nil)
	require.NoError(t, err)

	return newItemExtractorFixture(container), row, goquDB, database
}
