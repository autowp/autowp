package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

// ContentReportEntityType identifies what a content report points at.
type ContentReportEntityType int16

const (
	ContentReportEntityTypePicture ContentReportEntityType = 1
	ContentReportEntityTypeComment ContentReportEntityType = 2
	ContentReportEntityTypeOther   ContentReportEntityType = 3
)

// ContentReportReason is the category the reporter picked.
type ContentReportReason int16

const (
	ContentReportReasonCopyright ContentReportReason = 1
	ContentReportReasonIllegal   ContentReportReason = 2
	ContentReportReasonSpam      ContentReportReason = 3
	ContentReportReasonPrivacy   ContentReportReason = 4
	ContentReportReasonOther     ContentReportReason = 5
)

// ContentReportStatus is where a report is in the moderation flow.
type ContentReportStatus int16

const (
	ContentReportStatusOpen     ContentReportStatus = 1
	ContentReportStatusAccepted ContentReportStatus = 2
	ContentReportStatusRejected ContentReportStatus = 3
)

const (
	ContentReportTableName              = "content_report"
	ContentReportTableIDColName         = "id"
	ContentReportTableEntityTypeColName = "entity_type"
	ContentReportTableEntityIDColName   = "entity_id"
	ContentReportTableReasonColName     = "reason"
	ContentReportTableMessageColName    = "message"
	ContentReportTableReporterIDColName = "reporter_id"
	ContentReportTableReporterIPColName = "reporter_ip"
	ContentReportTableCreatedAtColName  = "created_at"
	ContentReportTableStatusColName     = "status"
	ContentReportTableResolvedByColName = "resolved_by"
	ContentReportTableResolvedAtColName = "resolved_at"
	ContentReportTableResolutionColName = "resolution"
)

var (
	ContentReportTable              = goqu.T(ContentReportTableName)
	ContentReportTableIDCol         = ContentReportTable.Col(ContentReportTableIDColName)
	ContentReportTableEntityTypeCol = ContentReportTable.Col(ContentReportTableEntityTypeColName)
	ContentReportTableEntityIDCol   = ContentReportTable.Col(ContentReportTableEntityIDColName)
	ContentReportTableReporterIDCol = ContentReportTable.Col(ContentReportTableReporterIDColName)
	ContentReportTableCreatedAtCol  = ContentReportTable.Col(ContentReportTableCreatedAtColName)
	ContentReportTableStatusCol     = ContentReportTable.Col(ContentReportTableStatusColName)
)

// ContentReportRow is one row of content_report.
type ContentReportRow struct {
	ID         int64                   `db:"id"`
	EntityType ContentReportEntityType `db:"entity_type"`
	EntityID   int64                   `db:"entity_id"`
	Reason     ContentReportReason     `db:"reason"`
	Message    string                  `db:"message"`
	ReporterID sql.NullInt64           `db:"reporter_id"`
	CreatedAt  time.Time               `db:"created_at"`
	Status     ContentReportStatus     `db:"status"`
	ResolvedBy sql.NullInt64           `db:"resolved_by"`
	ResolvedAt sql.NullTime            `db:"resolved_at"`
	Resolution string                  `db:"resolution"`
}
