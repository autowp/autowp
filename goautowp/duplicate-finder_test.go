package goautowp

import (
	"context"
	"fmt"
	"math/rand"
	"net/http"
	"net/http/httptest"
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

	var distance int

	success, err := goquDB.Select(schema.DfDistanceTableDistanceCol).
		From(schema.DfDistanceTable).
		Where(
			schema.DfDistanceTableSrcPictureIDCol.Eq(id1),
			schema.DfDistanceTableDstPictureIDCol.Eq(id2),
		).
		ScanValContext(ctx, &distance)
	require.NoError(t, err)
	require.True(t, success)
	// large.jpg and small.jpg are the same source photo at different
	// resolutions; PDQ's Jarosz-filter downsampling makes it very resistant
	// to resizing, so real-world distance for this pair is ~2 out of 256
	// bits. Leave headroom above that to avoid flakiness from re-encoding.
	require.LessOrEqual(t, distance, 10)
}

// TestIndexRespectsContextDeadline is a regression test for the
// duplicate-finder AMQP consumer's HTTP fetch having no bound: a hung
// remote image host would otherwise stall the single-threaded consumer
// loop indefinitely. Uses a short parent-context deadline rather than
// waiting out the real fetchImageTimeout constant — context.WithTimeout
// always resolves to the earlier of the parent's deadline and its own
// duration, so this exercises the same code path fast.
func TestIndexRespectsContextDeadline(t *testing.T) {
	t.Parallel()

	srv := httptest.NewServer(http.HandlerFunc(func(http.ResponseWriter, *http.Request) {
		time.Sleep(200 * time.Millisecond)
	}))
	defer srv.Close()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	cfg := config.LoadConfig(".")

	df, err := NewDuplicateFinder(goquDB, cfg.DuplicateFinder)
	require.NoError(t, err)

	ctx, cancel := context.WithTimeout(t.Context(), 20*time.Millisecond)
	defer cancel()

	err = df.Index(ctx, 1, srv.URL)
	require.ErrorIs(t, err, context.DeadlineExceeded)
}
