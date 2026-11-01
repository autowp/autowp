package articles

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/articles"
	"github.com/autowp/goautowp/internal/schema"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const previewBaseURL = "/img/articles/preview/"

type Server struct {
	pb.UnimplementedArticlesServer

	repository *articles.Repository
}

func NewServer(repository *articles.Repository) *Server {
	return &Server{repository: repository}
}

func (s *Server) GetList(ctx context.Context, in *pb.ArticlesRequest) (*pb.ArticlesResponse, error) {
	rows, pages, err := s.repository.List(ctx, in.GetPage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*pb.Article, 0, len(rows))
	for _, row := range rows {
		items = append(items, articleToProto(row))
	}

	return &pb.ArticlesResponse{
		Items: items,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *Server) GetItemByCatname(ctx context.Context, in *pb.ArticleByCatnameRequest) (*pb.Article, error) {
	article, err := s.repository.ByCatname(ctx, in.GetCatname())
	if errors.Is(err, articles.ErrNotFound) {
		return nil, status.Error(codes.NotFound, "not found")
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return articleToProto(article), nil
}

func articleToProto(article schema.ArticleRow) *pb.Article {
	var previewURL string
	if article.PreviewFilename.Valid {
		previewURL = previewBaseURL + article.PreviewFilename.String
	}

	var authorID int64
	if article.AuthorID.Valid {
		authorID = article.AuthorID.Int64
	}

	var html string
	if article.HTML.Valid {
		html = article.HTML.String
	}

	return &pb.Article{
		Id:          article.ID,
		Name:        article.Name,
		AuthorId:    authorID,
		Catname:     article.Catname,
		CreateTime:  timestamppb.New(article.CreatedAt),
		PreviewUrl:  previewURL,
		Description: article.Description,
		Html:        html,
	}
}
