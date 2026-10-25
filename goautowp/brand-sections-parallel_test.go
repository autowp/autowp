package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newBrandSectionsTestServer(t *testing.T) (*ItemsGRPCServer, *goqu.Database, *sql.DB, int64, string) {
	t.Helper()

	ctx := t.Context()
	catname := "parallel-sections-" + uuid.NewString()
	brandID := createItem(t, conn, cnt, &Item{
		Name: "Parallel sections brand", Catname: catname, IsGroup: true, ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})
	cfg := config.LoadConfig(".")
	pool, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })

	database := goqu.New("postgres", pool)
	container := &Container{config: cfg, goquDB: database}
	server, err := container.ItemsGRPCServer(ctx)
	require.NoError(t, err)

	for _, child := range []struct {
		name        string
		itemType    ItemType
		vehicleType int64
		concept     bool
	}{
		{name: "Zulu", itemType: ItemType_ITEM_TYPE_VEHICLE},
		{name: "Alpha", itemType: ItemType_ITEM_TYPE_VEHICLE},
		{name: "Moto", itemType: ItemType_ITEM_TYPE_VEHICLE, vehicleType: items.VehicleTypeIDMoto},
		{name: "Engine", itemType: ItemType_ITEM_TYPE_ENGINE},
		{name: "Concept", itemType: ItemType_ITEM_TYPE_VEHICLE, concept: true},
	} {
		id := createItem(t, conn, cnt, &Item{Name: child.name, ItemTypeId: child.itemType, IsConcept: child.concept})
		_, err := server.repository.CreateItemParent(ctx, id, brandID, schema.ItemParentTypeDefault, child.name)
		require.NoError(t, err)

		if child.vehicleType != 0 {
			require.NoError(t, server.repository.AddItemVehicleType(ctx, id, child.vehicleType))
		}
	}

	return server, database, pool, brandID, catname
}

func TestBrandSectionsConcurrentReads(t *testing.T) {
	t.Parallel()

	server, database, _, brandID, catname := newBrandSectionsTestServer(t)
	for _, lang := range []string{"en", "ru"} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		serialCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
		expected, err := server.carSections(serialCtx, lang, brandID, catname)
		require.NoError(t, err)
		other, err := server.otherGroups(serialCtx, brandID, catname, lang)
		require.NoError(t, err)

		expected = append(expected, &BrandSection{Name: "Other", Groups: other})

		gate := &itemBatchQueryLogger{}
		unblock := blockItemExtractionQueries(t, gate, 4)

		var active, maximum atomic.Int64

		database.Logger(&itemBatchQueryLogger{beforeQuery: func() {
			current := active.Add(1)
			defer active.Add(-1)

			for old := maximum.Load(); current > old; old = maximum.Load() {
				if maximum.CompareAndSwap(old, current) {
					break
				}
			}

			gate.Printf("")
		}})

		done := make(chan error, 1)

		var actual []*BrandSection

		go func() {
			var extractErr error

			actual, extractErr = server.brandSections(ctx, lang, brandID, catname)
			done <- extractErr
		}()

		unblock()
		require.NoError(t, <-done)

		database.Logger(nil)
		cancel()

		require.EqualValues(t, 4, maximum.Load(), "all brand reads must share one concurrency limit")
		require.Len(t, actual, 7)

		for index := range expected {
			require.True(t, proto.Equal(expected[index], actual[index]), "section %d, language %s", index, lang)
		}

		names := make([]string, len(actual))
		for index, section := range actual {
			names[index] = section.GetName()
		}

		require.Equal(t, []string{
			"", "catalogue/section/moto", "catalogue/section/buses", "catalogue/section/trucks",
			"catalogue/section/tractors", "catalogue/section/engines", "Other",
		}, names)

		require.Len(t, actual[0].GetGroups(), 2)
		require.Equal(t, frontend.BrandItemRoute(catname, "Alpha"), actual[0].GetGroups()[0].GetRouterLink())
		require.Equal(t, frontend.BrandItemRoute(catname, "Zulu"), actual[0].GetGroups()[1].GetRouterLink())
		require.Len(t, actual[1].GetGroups(), 1)
		require.Empty(t, actual[2].GetGroups())
		require.Equal(t, frontend.BrandEnginesRoute(catname), actual[5].GetRouterLink())
		require.Len(t, actual[6].GetGroups(), 1)
		require.Equal(t, frontend.BrandConceptsRoute(catname), actual[6].GetGroups()[0].GetRouterLink())
	}
}

func TestBrandSectionsCancelReads(t *testing.T) {
	t.Parallel()

	for _, testCase := range []struct {
		name string
		all  bool
	}{
		{name: "all sections", all: true},
		{name: "car sections"},
	} {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			server, database, pool, brandID, catname := newBrandSectionsTestServer(t)

			extract := server.carSections
			if testCase.all {
				extract = server.brandSections
			}

			ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
			defer cancel()

			pool.SetMaxOpenConns(1)
			connection, err := pool.Conn(ctx)
			require.NoError(t, err)

			defer connection.Close()

			started := make(chan struct{}, 4)

			database.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

			done := make(chan error, 1)

			go func() {
				_, extractErr := extract(ctx, "en", brandID, catname)
				done <- extractErr
			}()

			for range 4 {
				select {
				case <-started:
				case <-ctx.Done():
					t.Fatal("section reads did not start together")
				}
			}

			cancel()
			require.ErrorIs(t, <-done, context.Canceled)
			database.Logger(nil)
		})
	}
}
