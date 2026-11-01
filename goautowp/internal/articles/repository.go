package articles

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
)

var ErrNotFound = errors.New("article not found")

type Repository struct {
	db *goqu.Database
}

func NewRepository(db *goqu.Database) *Repository {
	return &Repository{db: db}
}

func (s *Repository) List(ctx context.Context, page int32) ([]schema.ArticleRow, *util.Pages, error) {
	paginator := util.Paginator{
		SQLSelect: s.db.Select(schema.ArticleTableIDCol, schema.ArticleTableNameCol, schema.ArticleTableAuthorIDCol,
			schema.ArticleTableCatnameCol, schema.ArticleTableCreatedAtCol, schema.ArticleTablePreviewFilenameCol,
			schema.ArticleTableDescriptionCol).
			From(schema.ArticleTable).
			Where(schema.ArticleTableEnabledCol).
			Order(schema.ArticleTableCreatedAtCol.Desc()),
		CurrentPageNumber: page,
		ItemCountPerPage:  util.DefaultItemCountPerPage,
	}

	sqlSelect, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	rows := make([]schema.ArticleRow, 0)
	if err := sqlSelect.ScanStructsContext(ctx, &rows); err != nil {
		return nil, nil, err
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	return rows, pages, nil
}

func (s *Repository) ByCatname(ctx context.Context, catname string) (schema.ArticleRow, error) {
	var article schema.ArticleRow

	found, err := s.db.Select(
		schema.ArticleTableIDCol, schema.ArticleTableNameCol, schema.ArticleTableAuthorIDCol,
		schema.ArticleTableCatnameCol, schema.ArticleTableCreatedAtCol, schema.ArticleTablePreviewFilenameCol,
		schema.ArticleTableHTMLCol,
	).
		From(schema.ArticleTable).
		Where(schema.ArticleTableEnabledCol, schema.ArticleTableCatnameCol.Eq(catname)).
		ScanStructContext(ctx, &article)
	if err != nil {
		return schema.ArticleRow{}, err
	}

	if !found {
		return schema.ArticleRow{}, ErrNotFound
	}

	return article, nil
}
