package query

import (
	"testing"

	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

// The mosts menu asks "which vehicle types does this brand have vehicles of?" once per type, per
// page render. Expressed as a join, that produced one row per matching vehicle and per row of its
// item_parent_cache closure - the same handful of types repeated thousands of times over for a
// large brand, duplicated in the menu built from them, and minutes of work on the server. Only a
// semi-join keeps it to one row per type, so pin the shape.
func TestVehicleTypeChildsFilterIsASemiJoin(t *testing.T) {
	t.Parallel()

	options := VehicleTypeListOptions{
		Childs: &VehicleTypeParentsListOptions{
			ItemVehicleTypeByID: &ItemVehicleTypeListOptions{
				ItemParentCacheAncestor: &ItemParentCacheListOptions{ParentID: 204},
			},
		},
		NoParent: true,
	}

	dataset, err := options.Select(goqu.New("postgres", nil), VehicleTypeTableAlias)
	require.NoError(t, err)

	sql, _, err := dataset.Select(goqu.T(VehicleTypeTableAlias).Col("id")).ToSQL()
	require.NoError(t, err)

	require.Contains(t, sql, "EXISTS")
	require.Contains(t, sql, `"vt"."id" = "vt_vtp"."parent_id"`)
	require.Contains(t, sql, `"vt_vtp_ivt_ipca"."parent_id" = 204`)

	// The outer query reads no column from those tables, so they must not be joined into it - that
	// is what multiplied the rows.
	require.NotContains(t, sql, `FROM "vehicle_type" AS "vt" INNER JOIN`)
}

func TestVehicleTypeWithoutChildsFilterStaysASimpleSelect(t *testing.T) {
	t.Parallel()

	options := VehicleTypeListOptions{ParentID: 17}

	dataset, err := options.Select(goqu.New("postgres", nil), VehicleTypeTableAlias)
	require.NoError(t, err)

	sql, _, err := dataset.Select(goqu.T(VehicleTypeTableAlias).Col("id")).ToSQL()
	require.NoError(t, err)

	require.Equal(t, `SELECT "vt"."id" FROM "vehicle_type" AS "vt" WHERE ("vt"."parent_id" = 17)`, sql)
}
