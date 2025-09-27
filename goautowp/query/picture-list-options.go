package query

import (
	"errors"
	"strconv"
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

var errNoTimezone = errors.New("timezone not provided")

const (
	PictureAlias = "p"
)

func AppendPictureAlias(alias string) string {
	return alias + "_" + PictureAlias
}

type PictureListOptions struct {
	Status                schema.PictureStatus
	Statuses              []schema.PictureStatus
	OwnerID               int64
	PictureItem           *PictureItemListOptions
	ID                    int64
	IDs                   []int64
	IDGt                  int64
	IDLt                  int64
	ExcludeID             int64
	ExcludeIDs            []int64
	HasCopyrights         bool
	Limit                 uint32
	Page                  uint32
	AcceptedInDays        int32
	AddDate               *civil.Date
	AddDateLt             *time.Time
	AddDateGte            *time.Time
	AcceptDate            *civil.Date
	AcceptDateLt          *time.Time
	AcceptDateGte         *time.Time
	AddedFrom             *civil.Date
	Timezone              *time.Location
	Identity              string
	CommentTopic          *CommentTopicListOptions
	HasNoComments         bool
	HasPoint              bool
	HasNoPoint            bool
	HasNoPictureItem      bool
	ReplacePicture        *PictureListOptions
	HasNoReplacePicture   bool
	PictureModerVote      *PictureModerVoteListOptions
	HasNoPictureModerVote bool
	DfDistance            *DfDistanceListOptions
	HasSpecialName        bool
}

func (s *PictureListOptions) Clone() *PictureListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	clone.PictureItem = s.PictureItem.Clone()
	clone.AddDate = s.AddDate
	clone.AddDateLt = s.AddDateLt
	clone.AddDateGte = s.AddDateGte
	clone.AcceptDate = s.AcceptDate
	clone.AcceptDateLt = s.AcceptDateLt
	clone.AcceptDateGte = s.AcceptDateGte
	clone.AddedFrom = s.AddedFrom
	clone.Timezone = s.Timezone
	clone.CommentTopic = s.CommentTopic.Clone()
	clone.ReplacePicture = s.ReplacePicture.Clone()
	clone.PictureModerVote = s.PictureModerVote.Clone()
	clone.DfDistance = s.DfDistance.Clone()

	return &clone
}

func (s *PictureListOptions) Select(db *goqu.Database, alias string) (*goqu.SelectDataset, error) {
	return s.apply(
		alias,
		db.Select().From(schema.PictureTable.As(alias)),
	)
}

func (s *PictureListOptions) CountSelect(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	sqSelect, err := s.Select(db, alias)
	if err != nil {
		return nil, err
	}

	if s.IsIDUnique() {
		return sqSelect.Select(goqu.COUNT(goqu.Star())), nil
	}

	return sqSelect.Select(
		goqu.COUNT(goqu.DISTINCT(goqu.T(alias).Col(schema.PictureTableIDColName))),
	), nil
}

func (s *PictureListOptions) IsIDUnique() bool {
	if s == nil {
		return true
	}

	return (s.PictureItem == nil || s.PictureItem.IsPictureIDUnique()) &&
		s.PictureModerVote == nil &&
		s.DfDistance == nil
}

func (s *PictureListOptions) JoinToIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.PictureTable.As(alias),
			goqu.On(
				srcCol.Eq(goqu.T(alias).Col(schema.PictureTableIDColName)),
			),
		),
	)
}

func (s *PictureListOptions) PictureItemAlias(alias string, idx int) string {
	return AppendPictureItemAlias(alias, strconv.Itoa(idx))
}

func (s *PictureListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var (
		err        error
		aliasTable = goqu.T(alias)
		idCol      = aliasTable.Col(schema.PictureTableIDColName)
	)

	if s.ID != 0 {
		sqSelect = sqSelect.Where(idCol.Eq(s.ID))
	}

	if len(s.IDs) > 0 {
		sqSelect = sqSelect.Where(idCol.In(s.IDs))
	}

	if s.IDGt != 0 {
		sqSelect = sqSelect.Where(idCol.Gt(s.IDGt))
	}

	if s.IDLt != 0 {
		sqSelect = sqSelect.Where(idCol.Lt(s.IDLt))
	}

	if s.ExcludeID != 0 {
		sqSelect = sqSelect.Where(idCol.Neq(s.ExcludeID))
	}

	if len(s.ExcludeIDs) > 0 {
		sqSelect = sqSelect.Where(idCol.NotIn(s.ExcludeIDs))
	}

	if s.Identity != "" {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTableIdentityColName).Eq(s.Identity))
	}

	if s.Status != "" {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTableStatusColName).Eq(s.Status))
	}

	if len(s.Statuses) > 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTableStatusColName).In(s.Statuses))
	}

	if s.OwnerID != 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTableOwnerIDColName).Eq(s.OwnerID))
	}

	if s.PictureItem != nil {
		sqSelect, err = s.PictureItem.JoinToPictureIDAndApply(
			idCol,
			s.PictureItemAlias(alias, 0),
			sqSelect,
		)
		if err != nil {
			return nil, err
		}
	}

	if s.HasCopyrights {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.PictureTableCopyrightsTextIDColName).IsNotNull(),
		)
	}

	sqSelect, err = s.applyAddDate(alias, sqSelect)
	if err != nil {
		return nil, err
	}

	sqSelect, err = s.applyAcceptDate(alias, sqSelect)
	if err != nil {
		return nil, err
	}

	if s.CommentTopic != nil {
		s.CommentTopic.TypeID = schema.CommentMessageTypeIDPictures

		sqSelect = s.CommentTopic.JoinToItemIDAndApply(
			idCol,
			AppendCommentTopicAlias(alias),
			sqSelect,
		)
	}

	sqSelect = s.applyHasNoComments(alias, sqSelect)

	if s.HasPoint {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTablePointColName).IsNotNull())
	}

	if s.HasNoPoint {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureTablePointColName).IsNull())
	}

	sqSelect = s.applyHasNoPictureItem(alias, sqSelect)

	sqSelect, err = s.ReplacePicture.JoinToIDAndApply(
		aliasTable.Col(schema.PictureTableReplacePictureIDColName),
		AppendPictureAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect = s.applyHasNoReplacePicture(alias, sqSelect)

	sqSelect = s.PictureModerVote.JoinToPictureIDAndApply(
		idCol,
		AppendPictureModerVoteAlias(alias),
		sqSelect,
	)

	if s.HasNoPictureModerVote {
		pmvAlias := alias + "no_pmv"
		pmvAliasTable := goqu.T(pmvAlias)

		sqSelect = sqSelect.LeftJoin(
			schema.PictureModerVoteTable.As(pmvAlias),
			goqu.On(idCol.Eq(pmvAliasTable.Col(schema.PictureModerVoteTablePictureIDColName))),
		).Where(pmvAliasTable.Col(schema.PictureModerVoteTablePictureIDColName).IsNull())
	}

	sqSelect, err = s.DfDistance.JoinToSrcPictureIDAndApply(
		idCol,
		AppendDfDistanceAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	if s.HasSpecialName {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.PictureTableNameColName).IsNotNull(),
			aliasTable.Col(schema.PictureTableNameColName).Neq(""),
		)
	}

	return sqSelect, nil
}

func (s *PictureListOptions) applyAddDate(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var (
		err        error
		aliasTable = goqu.T(alias)
		addDateCol = aliasTable.Col(schema.PictureTableAddDateColName)
	)

	if s.AddedFrom != nil {
		if s.Timezone == nil {
			return nil, errNoTimezone
		}

		sqSelect = sqSelect.Where(
			addDateCol.Gte(s.AddedFrom.In(s.Timezone).In(time.UTC).Format(time.DateTime)),
		)
	}

	if s.AddDate != nil {
		sqSelect, err = s.setDateFilter(sqSelect, addDateCol, *s.AddDate, s.Timezone)
		if err != nil {
			return nil, err
		}
	}

	if s.AddDateLt != nil {
		if s.Timezone == nil {
			return nil, errNoTimezone
		}

		sqSelect = sqSelect.Where(addDateCol.Lt(s.AddDateLt.In(time.UTC).Format(time.DateTime)))
	}

	if s.AddDateGte != nil {
		if s.Timezone == nil {
			return nil, errNoTimezone
		}

		sqSelect = sqSelect.Where(addDateCol.Gte(s.AddDateGte.In(time.UTC).Format(time.DateTime)))
	}

	return sqSelect, nil
}

func (s *PictureListOptions) applyAcceptDate(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var (
		err           error
		aliasTable    = goqu.T(alias)
		acceptDateCol = aliasTable.Col(schema.PictureTableAcceptDatetimeColName)
	)

	if s.AcceptedInDays > 0 {
		sqSelect = sqSelect.Where(
			acceptDateCol.Gt(
				goqu.Func(
					"DATE_SUB",
					goqu.Func("CURDATE"),
					goqu.L("INTERVAL ? DAY", s.AcceptedInDays),
				),
			),
		)
	}

	if s.AcceptDate != nil {
		sqSelect, err = s.setDateFilter(sqSelect, acceptDateCol, *s.AcceptDate, s.Timezone)
		if err != nil {
			return nil, err
		}
	}

	if s.AcceptDateLt != nil {
		if s.Timezone == nil {
			return nil, errNoTimezone
		}

		sqSelect = sqSelect.Where(
			acceptDateCol.Lt(s.AcceptDateLt.In(time.UTC).Format(time.DateTime)),
		)
	}

	if s.AcceptDateGte != nil {
		if s.Timezone == nil {
			return nil, errNoTimezone
		}

		sqSelect = sqSelect.Where(
			acceptDateCol.Gte(s.AcceptDateGte.In(time.UTC).Format(time.DateTime)),
		)
	}

	return sqSelect, nil
}

func (s *PictureListOptions) applyHasNoReplacePicture(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if !s.HasNoReplacePicture {
		return sqSelect
	}

	pAlias := alias + "no_p"
	pAliasTable := goqu.T(pAlias)

	return sqSelect.LeftJoin(
		schema.PictureTable.As(pAlias),
		goqu.On(goqu.T(alias).Col(schema.PictureTableReplacePictureIDColName).Eq(
			pAliasTable.Col(schema.PictureTableIDColName),
		)),
	).Where(pAliasTable.Col(schema.PictureTableIDColName).IsNull())
}

func (s *PictureListOptions) applyHasNoPictureItem(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if !s.HasNoPictureItem {
		return sqSelect
	}

	idCol := goqu.T(alias).Col(schema.PictureTableIDColName)
	piAlias := alias + "no_pi"
	piAliasTable := goqu.T(piAlias)

	return sqSelect.LeftJoin(
		schema.PictureItemTable.As(piAlias),
		goqu.On(idCol.Eq(piAliasTable.Col(schema.PictureItemTableItemIDColName))),
	).Where(piAliasTable.Col(schema.PictureItemTableItemIDColName).IsNull())
}

func (s *PictureListOptions) applyHasNoComments(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if !s.HasNoComments {
		return sqSelect
	}

	idCol := goqu.T(alias).Col(schema.PictureTableIDColName)
	ctAlias := alias + "no_cm"
	ctAliasTable := goqu.T(ctAlias)

	return sqSelect.LeftJoin(
		schema.CommentTopicTable.As(ctAlias),
		goqu.On(
			idCol.Eq(ctAliasTable.Col(schema.CommentTopicTableItemIDColName)),
			ctAliasTable.Col(schema.CommentTopicTableTypeIDColName).
				Eq(schema.CommentMessageTypeIDPictures),
		),
	).Where(
		goqu.Or(
			ctAliasTable.Col(schema.CommentTopicTableItemIDColName).IsNull(),
			ctAliasTable.Col(schema.CommentTopicTableMessagesColName).Eq(0),
		),
	)
}

func (s *PictureListOptions) setDateFilter(
	sqSelect *goqu.SelectDataset,
	column exp.IdentifierExpression,
	date civil.Date,
	timezone *time.Location,
) (*goqu.SelectDataset, error) {
	if s.Timezone == nil {
		return nil, errNoTimezone
	}

	return sqSelect.Where(
		column.Gte(date.In(timezone).In(time.UTC).Format(time.DateTime)),
		column.Lt(date.In(timezone).AddDate(0, 0, 1).In(time.UTC).Format(time.DateTime)),
	), nil
}
