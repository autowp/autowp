package goautowp

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type TextGRPCServer struct {
	UnimplementedTextServer

	auth *Auth
	db   *goqu.Database
}

func NewTextGRPCServer(
	auth *Auth,
	db *goqu.Database,
) *TextGRPCServer {
	return &TextGRPCServer{
		auth: auth,
		db:   db,
	}
}

func (s *TextGRPCServer) GetText(
	ctx context.Context,
	in *GetTextRequest,
) (*GetTextResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

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
		return nil, status.Error(codes.NotFound, "not found")
	}

	if currentRevision == 0 {
		currentRevision = lastRevision
	}

	if currentRevision != lastRevision && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
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

	return &GetTextResponse{
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
