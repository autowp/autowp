package contentreport

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

const reportsPerPage = 30

// Repository stores and resolves user reports about pictures and comments (DSA Art. 16).
type Repository struct {
	db *goqu.Database
}

// NewRepository constructor.
func NewRepository(db *goqu.Database) *Repository {
	return &Repository{db: db}
}

// CreateOptions is a new report.
type CreateOptions struct {
	EntityType schema.ContentReportEntityType
	EntityID   int64
	Reason     schema.ContentReportReason
	Message    string
	ReporterID sql.NullInt64
	ReporterIP string
}

// Create records a report. If the same signed-in user already has an open report for the same
// entity, it is left as is and its id is returned instead of inserting a duplicate.
func (s *Repository) Create(ctx context.Context, opts CreateOptions) (int64, error) {
	if opts.ReporterID.Valid {
		var existingID int64

		found, err := s.db.From(schema.ContentReportTable).
			Select(schema.ContentReportTableIDCol).
			Where(
				schema.ContentReportTableEntityTypeCol.Eq(opts.EntityType),
				schema.ContentReportTableEntityIDCol.Eq(opts.EntityID),
				schema.ContentReportTableReporterIDCol.Eq(opts.ReporterID.Int64),
				schema.ContentReportTableStatusCol.Eq(schema.ContentReportStatusOpen),
			).
			ScanValContext(ctx, &existingID)
		if err != nil {
			return 0, err
		}

		if found {
			return existingID, nil
		}
	}

	record := goqu.Record{
		schema.ContentReportTableEntityTypeColName: opts.EntityType,
		schema.ContentReportTableEntityIDColName:   opts.EntityID,
		schema.ContentReportTableReasonColName:     opts.Reason,
		schema.ContentReportTableMessageColName:    opts.Message,
		schema.ContentReportTableStatusColName:     schema.ContentReportStatusOpen,
	}

	if opts.ReporterID.Valid {
		record[schema.ContentReportTableReporterIDColName] = opts.ReporterID.Int64
	}

	if opts.ReporterIP != "" {
		record[schema.ContentReportTableReporterIPColName] = opts.ReporterIP
	}

	var id int64

	_, err := s.db.Insert(schema.ContentReportTable).
		Rows(record).
		Returning(schema.ContentReportTableIDCol).
		Executor().ScanValContext(ctx, &id)

	return id, err
}

// ListOptions filters the moderation queue.
type ListOptions struct {
	Status     schema.ContentReportStatus
	EntityType schema.ContentReportEntityType
	Page       int32
}

// List returns a page of reports, newest first.
func (s *Repository) List(
	ctx context.Context, opts ListOptions,
) ([]schema.ContentReportRow, *util.Pages, error) {
	sqSelect := s.db.From(schema.ContentReportTable).
		Order(schema.ContentReportTableCreatedAtCol.Desc(), schema.ContentReportTableIDCol.Desc())

	if opts.Status != 0 {
		sqSelect = sqSelect.Where(schema.ContentReportTableStatusCol.Eq(opts.Status))
	}

	if opts.EntityType != 0 {
		sqSelect = sqSelect.Where(schema.ContentReportTableEntityTypeCol.Eq(opts.EntityType))
	}

	page := opts.Page
	if page < 1 {
		page = 1
	}

	paginator := util.Paginator{
		SQLSelect:         sqSelect,
		ItemCountPerPage:  reportsPerPage,
		CurrentPageNumber: page,
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	pageSelect, err := paginator.GetItemsByPage(ctx, page)
	if err != nil {
		return nil, nil, err
	}

	rows := make([]schema.ContentReportRow, 0)
	if err = pageSelect.ScanStructsContext(ctx, &rows); err != nil {
		return nil, nil, err
	}

	return rows, pages, nil
}

// ResolvedReport is what Resolve reports back so the caller can notify the reporter.
type ResolvedReport struct {
	EntityType schema.ContentReportEntityType
	EntityID   int64
	Reason     schema.ContentReportReason
	ReporterID sql.NullInt64
}

// Resolve closes an open report as accepted or rejected. It is a no-op (returns ok=false) if the
// report does not exist or is already resolved.
func (s *Repository) Resolve(
	ctx context.Context, id, moderatorID int64, accepted bool, resolution string,
) (ResolvedReport, bool, error) {
	var row schema.ContentReportRow

	found, err := s.db.From(schema.ContentReportTable).
		Where(schema.ContentReportTableIDCol.Eq(id)).
		ScanStructContext(ctx, &row)
	if err != nil {
		return ResolvedReport{}, false, err
	}

	if !found || row.Status != schema.ContentReportStatusOpen {
		return ResolvedReport{}, false, nil
	}

	status := schema.ContentReportStatusRejected
	if accepted {
		status = schema.ContentReportStatusAccepted
	}

	_, err = s.db.Update(schema.ContentReportTable).
		Set(goqu.Record{
			schema.ContentReportTableStatusColName:     status,
			schema.ContentReportTableResolvedByColName: moderatorID,
			schema.ContentReportTableResolvedAtColName: goqu.Func("NOW"),
			schema.ContentReportTableResolutionColName: resolution,
		}).
		Where(schema.ContentReportTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return ResolvedReport{}, false, err
	}

	return ResolvedReport{
		EntityType: row.EntityType,
		EntityID:   row.EntityID,
		Reason:     row.Reason,
		ReporterID: row.ReporterID,
	}, true, nil
}
