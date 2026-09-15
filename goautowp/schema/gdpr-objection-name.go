package schema

import "github.com/doug-martin/goqu/v9"

const (
	GdprObjectionNameTableName                  = "gdpr_objection_name"
	GdprObjectionNameTableIDColName             = "id"
	GdprObjectionNameTableObjectionIDColName    = "objection_id"
	GdprObjectionNameTableNameColName           = "name"
	GdprObjectionNameTableNormalizedNameColName = "normalized_name"
	GdprObjectionNameTableCanonicalNameColName  = "canonical_name"
)

var (
	GdprObjectionNameTable                  = goqu.T(GdprObjectionNameTableName)
	GdprObjectionNameTableIDCol             = GdprObjectionNameTable.Col(GdprObjectionNameTableIDColName)
	GdprObjectionNameTableObjectionIDCol    = GdprObjectionNameTable.Col(GdprObjectionNameTableObjectionIDColName)
	GdprObjectionNameTableNameCol           = GdprObjectionNameTable.Col(GdprObjectionNameTableNameColName)
	GdprObjectionNameTableNormalizedNameCol = GdprObjectionNameTable.Col(
		GdprObjectionNameTableNormalizedNameColName,
	)
	GdprObjectionNameTableCanonicalNameCol = GdprObjectionNameTable.Col(GdprObjectionNameTableCanonicalNameColName)
)

// GdprObjectionNameRow is one known name spelling for a gdpr_objection case (see migration 53).
// CanonicalName is NormalizedName with its words sorted, so a given-name/family-name swap still
// matches (see compliance.CanonicalizeName).
type GdprObjectionNameRow struct {
	ID             int64  `db:"id"`
	ObjectionID    int64  `db:"objection_id"`
	Name           string `db:"name"`
	NormalizedName string `db:"normalized_name"`
	CanonicalName  string `db:"canonical_name"`
}
