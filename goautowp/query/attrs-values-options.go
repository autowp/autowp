package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const AttrsValuesAlias = "av"

type AttrsValueListOptions struct {
	ZoneID      int64
	ItemID      int64
	ChildItemID int64
	AttributeID int64
	Conflict    bool
	UserValues  *AttrsUserValueListOptions
}

func (s *AttrsValueListOptions) Select(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.apply(
		alias,
		db.From(schema.AttrsValuesTable.As(alias)).
			Order(goqu.T(alias).Col(schema.AttrsValuesTableUpdateDateColName).Desc()),
	)
}

func (s *AttrsValueListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	var (
		aliasTable     = goqu.T(alias)
		attributeIDCol = aliasTable.Col(schema.AttrsValuesTableAttributeIDColName)
		itemIDCol      = aliasTable.Col(schema.AttrsValuesTableItemIDColName)
	)

	if s.ItemID != 0 {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemID))
	}

	if s.ChildItemID > 0 {
		sqSelect = sqSelect.Join(
			schema.ItemParentTable,
			goqu.On(itemIDCol.Eq(schema.ItemParentTableParentIDCol)),
		).Where(
			schema.ItemParentTableItemIDCol.Eq(s.ChildItemID),
		)
	}

	if s.AttributeID != 0 {
		sqSelect = sqSelect.Where(attributeIDCol.Eq(s.AttributeID))
	}

	if s.ZoneID != 0 {
		azaAlias := AppendAttrsZoneAttributesAlias(alias)

		sqSelect = sqSelect.Join(
			schema.AttrsZoneAttributesTable.As(azaAlias),
			goqu.On(
				attributeIDCol.Eq(
					goqu.T(azaAlias).Col(schema.AttrsZoneAttributesTableAttributeIDColName),
				),
			),
		).Where(goqu.T(azaAlias).Col(schema.AttrsZoneAttributesTableZoneIDColName).Eq(s.ZoneID))
	}

	if s.Conflict {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsValuesTableConflictColName).IsTrue(),
		)
	}

	return s.UserValues.JoinToAttributeIDItemIDAndApply(
		attributeIDCol,
		itemIDCol,
		AppendPictureItemAlias(alias, ""),
		sqSelect,
	)
}
