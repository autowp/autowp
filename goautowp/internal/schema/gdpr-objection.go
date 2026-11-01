package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	GdprObjectionTableName                = "gdpr_objection"
	GdprObjectionTableIDColName           = "id"
	GdprObjectionTableReferenceColName    = "reference"
	GdprObjectionTableContactEmailColName = "contact_email"
	GdprObjectionTableNoteColName         = "note"
	GdprObjectionTableSourceTextIDColName = "source_text_id"
	GdprObjectionTableCreatedAtColName    = "created_at"
	GdprObjectionTableHitCountColName     = "hit_count"
	GdprObjectionTableLastHitAtColName    = "last_hit_at"
)

var (
	GdprObjectionTable                = goqu.T(GdprObjectionTableName)
	GdprObjectionTableIDCol           = GdprObjectionTable.Col(GdprObjectionTableIDColName)
	GdprObjectionTableReferenceCol    = GdprObjectionTable.Col(GdprObjectionTableReferenceColName)
	GdprObjectionTableContactEmailCol = GdprObjectionTable.Col(GdprObjectionTableContactEmailColName)
	GdprObjectionTableNoteCol         = GdprObjectionTable.Col(GdprObjectionTableNoteColName)
	GdprObjectionTableSourceTextIDCol = GdprObjectionTable.Col(GdprObjectionTableSourceTextIDColName)
	GdprObjectionTableCreatedAtCol    = GdprObjectionTable.Col(GdprObjectionTableCreatedAtColName)
	GdprObjectionTableHitCountCol     = GdprObjectionTable.Col(GdprObjectionTableHitCountColName)
	GdprObjectionTableLastHitAtCol    = GdprObjectionTable.Col(GdprObjectionTableLastHitAtColName)
)

// GdprObjectionRow is a row of the internal author-credit suppression list - one row per case,
// not per name spelling (see gdpr_objection_name / migration 53). SourceTextID, when set, points
// at the original request correspondence in textstorage_text - evidentiary, not part of the
// matching signal, so repository list/match queries never select it.
type GdprObjectionRow struct {
	ID           int64         `db:"id"`
	Reference    string        `db:"reference"`
	ContactEmail string        `db:"contact_email"`
	Note         string        `db:"note"`
	SourceTextID sql.NullInt32 `db:"source_text_id"`
	CreatedAt    time.Time     `db:"created_at"`
	HitCount     int32         `db:"hit_count"`
	LastHitAt    sql.NullTime  `db:"last_hit_at"`
}
