package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func TestDuplicateFinder(t *testing.T) {
	t.Parallel()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	cfg := config.LoadConfig(".")
	ctx := t.Context()
	kc := cnt.Keycloak()

	df, err := NewDuplicateFinder(goquDB, cfg.DuplicateFinder)
	require.NoError(t, err)

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	id1 := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)
	err = df.Index(ctx, id1, "http://localhost:80/large.jpg")
	require.NoError(t, err)

	id2 := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)
	err = df.Index(ctx, id2, "http://localhost:80/small.jpg")
	require.NoError(t, err)

	var hash1 int64

	success, err := goquDB.Select(schema.DfHashTableHashCol).
		From(schema.DfHashTable).
		Where(schema.DfHashTablePictureIDCol.Eq(id1)).
		ScanValContext(ctx, &hash1)
	require.NoError(t, err)
	require.True(t, success)

	var hash2 int64

	success, err = goquDB.Select(schema.DfHashTableHashCol).
		From(schema.DfHashTable).
		Where(schema.DfHashTablePictureIDCol.Eq(id2)).
		ScanValContext(ctx, &hash2)
	require.NoError(t, err)
	require.True(t, success)

	var distance int

	success, err = goquDB.Select(schema.DfDistanceTableDistanceCol).
		From(schema.DfDistanceTable).
		Where(
			schema.DfDistanceTableSrcPictureIDCol.Eq(id1),
			schema.DfDistanceTableDstPictureIDCol.Eq(id2),
		).
		ScanValContext(ctx, &distance)
	require.NoError(t, err)
	require.True(t, success)
	require.LessOrEqual(t, distance, 2)
}
