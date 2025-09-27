package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const (
	CommentMessageAlias = "cm"
)

type CommentMessageListOptions struct {
	Attention    schema.CommentMessageModeratorAttention
	CommentType  schema.CommentMessageType
	PictureItems *PictureItemListOptions
}

func (s *CommentMessageListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	return s.apply(
		alias,
		db.From(schema.CommentMessageTable.As(alias)),
	)
}

func (s *CommentMessageListOptions) CountSelect(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	sqSelect, err := s.Select(db, alias)
	if err != nil {
		return nil, err
	}

	return sqSelect.Select(goqu.COUNT(goqu.Star())), nil
}

func (s *CommentMessageListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var (
		err        error
		aliasTable = goqu.T(alias)
	)

	sqSelect = sqSelect.Where(
		aliasTable.Col(schema.CommentMessageTableModeratorAttentionColName).Eq(s.Attention),
		aliasTable.Col(schema.CommentMessageTableTypeIDColName).Eq(s.CommentType),
	)

	sqSelect, err = s.PictureItems.JoinToPictureIDAndApply(
		aliasTable.Col(schema.CommentMessageTableItemIDColName),
		AppendPictureItemAlias(alias, ""),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	return sqSelect, nil
}
