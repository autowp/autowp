package itemparentextractor_test

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/grpc/itemextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/itemparentextractor"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type parentDuplicateFixture struct {
	*itemparentextractor.Extractor

	itemsRepository func(ctx context.Context) (*items.Repository, error)
	itemExtractor   *itemextractor.Extractor
}

func newConnectedParentFixture(t *testing.T) (*parentDuplicateFixture, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := testutil.NewPostgres(t, "../../../..")
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item (LIKE public.item INCLUDING DEFAULTS INCLUDING INDEXES);
 ALTER TABLE pg_temp.item ALTER COLUMN body SET DEFAULT '';
 ALTER TABLE pg_temp.item ALTER COLUMN produced_exactly SET DEFAULT false;
 CREATE TEMP TABLE item_parent (item_id bigint,parent_id bigint,catname text,type integer,
 manual_catname boolean DEFAULT false);
 CREATE TEMP TABLE item_parent_cache (item_id bigint,parent_id bigint,diff integer);
 CREATE TEMP TABLE item_language_cache (item_id bigint,language text,name text,PRIMARY KEY(item_id,language));
 CREATE TEMP TABLE item_point (item_id bigint,point geography(Point,4326));
 CREATE TEMP TABLE user_item_subscribe (item_id bigint,user_id bigint);
 INSERT INTO item_parent_cache VALUES
 (100,100,0),(101,100,1),(101,101,0),(200,200,0),
 (10,10,0),(11,11,0),(12,12,0),(13,13,0),(14,14,0),(15,15,0),(16,16,0),(20,20,0),
 (10,20,1),(11,20,1),(12,20,1),(13,20,1),(14,20,1),(15,20,1),(16,20,1),
 (10,500,1),(10,501,2),(11,500,1),(100,502,1),(500,500,0),(501,501,0),(502,502,0);
 INSERT INTO user_item_subscribe VALUES (500,7),(700,7),(10,7),(501,8);
 INSERT INTO item_point VALUES (500,ST_GeogFromText('POINT(30 50)')),(700,NULL),(10,ST_GeogFromText('POINT(40 60)'));
 `)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item (id,catname,item_type_id,engine_item_id) VALUES
 (100,'engine-a',$1,NULL),(101,'engine-child',$1,NULL),(200,'engine-b',$1,NULL),(300,'empty-engine',$1,NULL),
 (10,'a',$2,100),(11,'b',$2,101),(12,'c',$2,100),(13,'d',$2,200),
 (14,'e',$2,200),(15,'f',$2,200),(16,'g',$2,200),(20,'ancestor',$2,100),
 (500,'twin-a',$3,NULL),(501,'twin-b',$3,NULL),(502,'twin-engine',$3,NULL),
 (700,'cat-a',$4,NULL),(701,'cat-b',$4,NULL),(702,'cat-engine',$4,NULL),(703,'excluded',$4,NULL)`,
		schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDVehicle,
		schema.ItemTableItemTypeIDTwins, schema.ItemTableItemTypeIDCategory)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item_parent (item_id,parent_id,catname,type) VALUES
 (10,700,'cat-a',$1),(11,701,'cat-b',$1),(20,701,'shared',$1),(100,702,'engine',$1),(500,703,'excluded',$1)`,
		schema.ItemParentTypeDefault)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `
 INSERT INTO item_language_cache
 SELECT id,'en',CASE id WHEN 500 THEN 'Zebra' WHEN 501 THEN 'apple' ELSE 'EN ' || id END FROM item;
 INSERT INTO item_language_cache
 SELECT id,'ru',CASE id WHEN 500 THEN 'Яблоко' WHEN 501 THEN 'Арбуз' ELSE 'РУ ' || id END FROM item;
 `)
	require.NoError(t, err)
	db.Logger(nil)

	repository := items.NewRepository(db, 0, nil, nil, nil)
	itemsDependency := func(_ context.Context) (*items.Repository, error) { return repository, nil }
	extractor := itemextractor.New(itemextractor.Dependencies{ItemsRepository: itemsDependency})
	parents := itemparentextractor.New(itemsDependency, nil, extractor, nil)

	return &parentDuplicateFixture{
		Extractor:       parents,
		itemsRepository: itemsDependency,
		itemExtractor:   extractor,
	}, db, pool
}
