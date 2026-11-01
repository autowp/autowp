package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

// GdprObjectionCleanupCandidateEntityType is what kind of content a cleanup candidate points at.
type GdprObjectionCleanupCandidateEntityType int16

const (
	// GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture is a picture whose freeform
	// copyrights_text_id text (see pictures.Repository.FindPicturesWithCopyrightsTextContaining)
	// mentions a suppressed name.
	GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture GdprObjectionCleanupCandidateEntityType = 1
	// GdprObjectionCleanupCandidateEntityTypeComment is a comment_message whose text (see
	// comments.Repository.FindCommentsContaining) mentions a suppressed name.
	GdprObjectionCleanupCandidateEntityTypeComment GdprObjectionCleanupCandidateEntityType = 2
)

const (
	GdprObjectionCleanupCandidateTableName               = "gdpr_objection_cleanup_candidate"
	GdprObjectionCleanupCandidateTableIDColName          = "id"
	GdprObjectionCleanupCandidateTableObjectionIDColName = "objection_id"
	GdprObjectionCleanupCandidateTableEntityTypeColName  = "entity_type"
	GdprObjectionCleanupCandidateTableEntityIDColName    = "entity_id"
	GdprObjectionCleanupCandidateTableFoundAtColName     = "found_at"
	GdprObjectionCleanupCandidateTableResolvedAtColName  = "resolved_at"
	GdprObjectionCleanupCandidateTableResolvedByColName  = "resolved_by"
)

var (
	GdprObjectionCleanupCandidateTable      = goqu.T(GdprObjectionCleanupCandidateTableName)
	GdprObjectionCleanupCandidateTableIDCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableIDColName,
	)
	GdprObjectionCleanupCandidateTableObjectionIDCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableObjectionIDColName,
	)
	GdprObjectionCleanupCandidateTableEntityTypeCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableEntityTypeColName,
	)
	GdprObjectionCleanupCandidateTableEntityIDCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableEntityIDColName,
	)
	GdprObjectionCleanupCandidateTableFoundAtCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableFoundAtColName,
	)
	GdprObjectionCleanupCandidateTableResolvedAtCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableResolvedAtColName,
	)
	GdprObjectionCleanupCandidateTableResolvedByCol = GdprObjectionCleanupCandidateTable.Col(
		GdprObjectionCleanupCandidateTableResolvedByColName,
	)
)

// GdprObjectionCleanupCandidateRow is a row of gdpr_objection_cleanup_candidate (migration 55).
type GdprObjectionCleanupCandidateRow struct {
	ID          int64                                   `db:"id"`
	ObjectionID int64                                   `db:"objection_id"`
	EntityType  GdprObjectionCleanupCandidateEntityType `db:"entity_type"`
	EntityID    int64                                   `db:"entity_id"`
	FoundAt     time.Time                               `db:"found_at"`
	ResolvedAt  sql.NullTime                            `db:"resolved_at"`
	ResolvedBy  sql.NullInt64                           `db:"resolved_by"`
}
