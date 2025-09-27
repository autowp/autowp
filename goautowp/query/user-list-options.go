package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const UserTableAlias = "u"

type UserListOptions struct {
	ID            int64
	IDs           []int64
	ExcludeIDs    []int64
	Identity      string
	InContacts    int64
	Deleted       *bool
	HasSpecs      *bool
	IsOnline      bool
	HasPictures   *bool
	Limit         uint64
	Page          uint64
	Search        string
	ItemSubscribe *UserItemSubscribeListOptions
}

func (s *UserListOptions) Select(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.apply(alias, db.From(schema.UserTable.As(alias)))
}

func (s *UserListOptions) apply(alias string, sqSelect *goqu.SelectDataset) *goqu.SelectDataset {
	var (
		aliasTable = goqu.T(alias)
		idCol      = aliasTable.Col(schema.UserTableIDColName)
	)

	if s.ID != 0 {
		sqSelect = sqSelect.Where(idCol.Eq(s.ID))
	}

	if len(s.IDs) != 0 {
		sqSelect = sqSelect.Where(idCol.In(s.IDs))
	}

	if len(s.ExcludeIDs) != 0 {
		sqSelect = sqSelect.Where(idCol.NotIn(s.ExcludeIDs))
	}

	if len(s.Identity) > 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableIdentityColName).Eq(s.Identity))
	}

	if len(s.Search) > 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableNameColName).ILike(s.Search + "%"))
	}

	if s.InContacts != 0 {
		sqSelect = sqSelect.Join(
			schema.ContactTable,
			goqu.On(idCol.Eq(schema.ContactTableContactUserIDCol))).
			Where(schema.ContactTableUserIDCol.Eq(s.InContacts))
	}

	if s.Deleted != nil {
		if *s.Deleted {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableDeletedColName).IsTrue())
		} else {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableDeletedColName).IsFalse())
		}
	}

	if s.HasSpecs != nil {
		if *s.HasSpecs {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableSpecsVolumeColName).Gt(0))
		} else {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableSpecsVolumeColName).Eq(0))
		}
	}

	if s.HasPictures != nil {
		if *s.HasPictures {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTablePicturesTotalColName).Gt(0))
		} else {
			sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTablePicturesTotalColName).Eq(0))
		}
	}

	if s.IsOnline {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.UserTableLastOnlineColName).Gte(
			goqu.Func("DATE_SUB", goqu.Func("NOW"), goqu.L("INTERVAL 5 MINUTE")),
		))
	}

	return s.ItemSubscribe.JoinToItemIDAndApply(idCol, alias+"_"+userItemSubscribeAlias, sqSelect)
}
