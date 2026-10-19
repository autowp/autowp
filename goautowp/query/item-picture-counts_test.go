package query

import (
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/stretchr/testify/require"
)

func TestOwnerPictureCountsAreAggregatedBeforeItemJoins(t *testing.T) {
	t.Parallel()

	options := ItemListOptions{
		AggregateDescendantPictures: true,
		TypeID:                      []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &ItemParentCacheListOptions{
			PictureItemsByItemID: &PictureItemListOptions{
				Pictures: &PictureListOptions{OwnerID: 433, Status: schema.PictureStatusAccepted},
			},
		},
	}
	dataset, err := options.Select(goqu.New("postgres", nil), ItemAlias)
	require.NoError(t, err)

	sql, _, err := dataset.ToSQL()
	require.NoError(t, err)

	// The owner's pictures are selected once; both cache keys are constrained before
	// counting, rather than checking every ancestor or repeating a lookup per picture.
	require.Contains(t, sql, `FROM "item" AS "i" INNER JOIN (WITH i_ipcd_selected_pictures AS (SELECT DISTINCT`)
	require.Contains(t, sql, `"i_ipcd_pii_p"."owner_id" = 433`)
	require.Contains(t, sql, `"i_ipcd_pii_p"."status" = 'accepted'`)
	require.Contains(t, sql, `"i_ipcd"."item_id" = ANY(ARRAY(SELECT DISTINCT`)
	require.Contains(t, sql, `INNER JOIN (SELECT DISTINCT "i_ipcd_iparents"."id"`)
	require.Contains(t, sql, `"i_ipcd"."parent_id" = "i_ipcd_iparents"."id"`)
	require.NotContains(t, sql, `"i_ipcd"."parent_id" = ANY`)
	require.Contains(t, sql, `"i_ipcd_iparents"."item_type_id" IN (5)`)
	require.Contains(t, sql, `COUNT(DISTINCT("i_ipcd_selected_pictures"."picture_id")) AS "pictures_count"`)
	require.NotContains(t, sql, "LATERAL")
}
