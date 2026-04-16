package goautowp

import (
	"context"
	"database/sql"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const ArticlesPreviewBaseURL = "/img/articles/preview/"

type ArticlesGRPCServer struct {
	UnimplementedArticlesServer

	db *goqu.Database
}

func NewArticlesGRPCServer(db *goqu.Database) *ArticlesGRPCServer {
	return &ArticlesGRPCServer{
		db: db,
	}
}

func (s *ArticlesGRPCServer) GetList(
	ctx context.Context,
	in *ArticlesRequest,
) (*ArticlesResponse, error) {
	type row struct {
		ID              int64          `db:"id"`
		Name            string         `db:"name"`
		AuthorID        sql.NullInt64  `db:"author_id"`
		Catname         string         `db:"catname"`
		AddDate         time.Time      `db:"add_date"`
		PreviewFilename sql.NullString `db:"preview_filename"`
		Description     string         `db:"description"`
	}

	rows := make([]row, 0)

	paginator := util.Paginator{
		SQLSelect: s.db.Select(schema.ArticleTableIDCol, schema.ArticleTableNameCol, schema.ArticleTableAuthorIDCol,
			schema.ArticleTableCatnameCol, schema.ArticleTableAddDateCol, schema.ArticleTablePreviewFilenameCol,
			schema.ArticleTableDescriptionCol).
			From(schema.ArticleTable).
			Where(schema.ArticleTableEnabledCol).
			Order(schema.ArticleTableAddDateCol.Desc()),
		CurrentPageNumber: int32(in.GetPage()), //nolint: gosec
		ItemCountPerPage:  util.DefaultItemCountPerPage,
	}

	sqlSelect, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = sqlSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	articles := make([]*Article, 0)

	for _, article := range rows {
		authorID := article.AuthorID.Int64
		if !article.AuthorID.Valid {
			authorID = 0
		}

		previewURL := ""
		if article.PreviewFilename.Valid {
			previewURL = ArticlesPreviewBaseURL + article.PreviewFilename.String
		}

		articles = append(articles, &Article{
			Id:          article.ID,
			Name:        article.Name,
			AuthorId:    authorID,
			Catname:     article.Catname,
			Date:        timestamppb.New(article.AddDate),
			PreviewUrl:  previewURL,
			Description: article.Description,
		})
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &ArticlesResponse{
		Items: articles,
		Paginator: &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Previous:         pages.Previous,
			Next:             pages.Next,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
		},
	}, nil
}

func (s *ArticlesGRPCServer) GetItemByCatname(
	ctx context.Context,
	in *ArticleByCatnameRequest,
) (*Article, error) {
	type row struct {
		ID              int64          `db:"id"`
		Name            string         `db:"name"`
		AuthorID        sql.NullInt64  `db:"author_id"`
		Catname         string         `db:"catname"`
		AddDate         time.Time      `db:"add_date"`
		PreviewFilename sql.NullString `db:"preview_filename"`
		HTML            sql.NullString `db:"html"`
	}

	article := row{}

	success, err := s.db.Select(
		schema.ArticleTableIDCol, schema.ArticleTableNameCol, schema.ArticleTableAuthorIDCol,
		schema.ArticleTableCatnameCol, schema.ArticleTableAddDateCol, schema.ArticleTablePreviewFilenameCol,
		schema.ArticleTableHTMLCol,
	).
		From(schema.ArticleTable).
		Where(schema.ArticleTableEnabledCol, schema.ArticleTableCatnameCol.Eq(in.GetCatname())).
		ScanStructContext(ctx, &article)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, status.Error(codes.Internal, "not found")
	}

	authorID := article.AuthorID.Int64
	if !article.AuthorID.Valid {
		authorID = 0
	}

	html := article.HTML.String
	if !article.HTML.Valid {
		html = ""
	}

	previewURL := ""
	if article.PreviewFilename.Valid {
		previewURL = ArticlesPreviewBaseURL + article.PreviewFilename.String
	}

	return &Article{
		Id:         article.ID,
		Name:       article.Name,
		AuthorId:   authorID,
		Catname:    article.Catname,
		Date:       timestamppb.New(article.AddDate),
		PreviewUrl: previewURL,
		Html:       html,
	}, nil
}
