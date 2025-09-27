package goautowp

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type TextGRPCServer struct {
	UnimplementedTextServer

	db *goqu.Database
}

func NewTextGRPCServer(
	db *goqu.Database,
) *TextGRPCServer {
	return &TextGRPCServer{
		db: db,
	}
}

func (s *TextGRPCServer) GetText(
	ctx context.Context,
	in *APIGetTextRequest,
) (*APIGetTextResponse, error) {
	var (
		lastRevision    int64
		currentRevision = in.GetRevision()
		prevRevision    int64
		nextRevision    int64
	)

	success, err := s.db.Select(schema.TextstorageTextTableRevisionCol).
		From(schema.TextstorageTextTable).
		Where(schema.TextstorageTextTableIDCol.Eq(in.GetId())).
		ScanValContext(ctx, &lastRevision)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, status.Error(codes.NotFound, "NotFound")
	}

	if currentRevision == 0 {
		currentRevision = lastRevision
	}

	stCurrent := struct {
		Text   string `db:"text"`
		UserID int64  `db:"user_id"`
	}{}

	success, err = s.db.Select(schema.TextstorageRevisionTableTextCol, schema.TextstorageRevisionTableUserIDCol).
		From(schema.TextstorageRevisionTable).
		Where(
			schema.TextstorageRevisionTableTextIDCol.Eq(in.GetId()),
			schema.TextstorageRevisionTableRevisionCol.Eq(currentRevision),
		).
		ScanStructContext(ctx, &stCurrent)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	stPrevious := struct {
		Text   string `db:"text"`
		UserID int64  `db:"user_id"`
	}{}

	if currentRevision-1 > 0 {
		prevRevision = currentRevision - 1

		success, err = s.db.Select(schema.TextstorageRevisionTableTextCol, schema.TextstorageRevisionTableUserIDCol).
			From(schema.TextstorageRevisionTable).
			Where(
				schema.TextstorageRevisionTableTextIDCol.Eq(in.GetId()),
				schema.TextstorageRevisionTableRevisionCol.Eq(prevRevision),
			).
			ScanStructContext(ctx, &stPrevious)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !success {
			return nil, sql.ErrNoRows
		}
	}

	if currentRevision+1 <= lastRevision {
		nextRevision = currentRevision + 1
	}

	return &APIGetTextResponse{
		Current: &TextRevision{
			Text:     stCurrent.Text,
			Revision: currentRevision,
			UserId:   stCurrent.UserID,
		},
		Prev: &TextRevision{
			Text:     stPrevious.Text,
			Revision: prevRevision,
			UserId:   stPrevious.UserID,
		},
		Next: &TextRevision{
			Revision: nextRevision,
		},
	}, nil
}
