package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	CommentTopicAlias = "ct"
)

func AppendCommentTopicAlias(alias string) string {
	return alias + "_" + CommentTopicAlias
}

type CommentTopicListOptions struct {
	TypeID         schema.CommentMessageType
	MessagesGtZero bool
}

func (s *CommentTopicListOptions) Clone() *CommentTopicListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	return &clone
}

func (s *CommentTopicListOptions) JoinToItemIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	sqSelect = sqSelect.Join(
		schema.CommentTopicTable.As(alias),
		goqu.On(
			srcCol.Eq(goqu.T(alias).Col(schema.CommentTopicTableItemIDColName)),
		),
	)

	return s.apply(alias, sqSelect)
}

func (s *CommentTopicListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s.TypeID != 0 {
		goqu.T(alias).Col(schema.CommentTopicTableItemIDColName).Eq(s.TypeID)
	}

	if s.MessagesGtZero {
		goqu.T(alias).Col(schema.CommentTopicTableMessagesColName).Gt(0)
	}

	return sqSelect
}
