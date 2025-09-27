package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	PictureItemAlias = "pi"
)

type PictureItemListOptions struct {
	TypeID                      schema.PictureItemType
	PictureID                   int64
	ItemID                      int64
	ItemIDs                     []int64
	ItemIDExpr                  exp.Expression
	Pictures                    *PictureListOptions
	PerspectiveID               int32
	ExcludePerspectiveID        []int32
	HasNoPerspectiveID          bool
	ExcludeAncestorOrSelfID     int64
	ItemParentCacheAncestor     *ItemParentCacheListOptions
	Item                        *ItemListOptions
	ItemVehicleType             *ItemVehicleTypeListOptions
	PictureItemByItemID         *PictureItemListOptions
	PictureItemByPictureID      *PictureItemListOptions
	PerspectiveGroupPerspective *PerspectiveGroupPerspectiveListOptions
	HasArea                     bool
}

func AppendPictureItemAlias(alias string, suffix string) string {
	return alias + "_" + PictureItemAlias + suffix
}

func (s *PictureItemListOptions) Clone() *PictureItemListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	clone.Pictures = s.Pictures.Clone()
	clone.ItemParentCacheAncestor = s.ItemParentCacheAncestor.Clone()
	clone.Item = s.Item.Clone()
	clone.ItemVehicleType = s.ItemVehicleType.Clone()
	clone.PictureItemByItemID = s.PictureItemByItemID.Clone()
	clone.PictureItemByPictureID = s.PictureItemByPictureID.Clone()
	clone.PerspectiveGroupPerspective = s.PerspectiveGroupPerspective.Clone()

	return &clone
}

func (s *PictureItemListOptions) IsPictureIDUnique() bool {
	return s.ItemID != 0 && s.TypeID != 0
}

func (s *PictureItemListOptions) IsItemIDUnique() bool {
	return s.PictureID != 0 && s.TypeID != 0
}

func (s *PictureItemListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	return s.apply(
		alias,
		db.Select().From(schema.PictureItemTable.As(alias)),
	)
}

func (s *PictureItemListOptions) JoinToItemIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.PictureItemTable.As(alias),
			goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.PictureItemTableItemIDColName))),
		),
	)
}

func (s *PictureItemListOptions) JoinToPictureIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.PictureItemTable.As(alias),
			goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.PictureItemTablePictureIDColName))),
		),
	)
}

func (s *PictureItemListOptions) ItemParentCacheAncestorAlias(alias string) string {
	return AppendItemParentCacheAlias(alias, "a")
}

func (s *PictureItemListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var (
		err              error
		aliasTable       = goqu.T(alias)
		itemIDCol        = aliasTable.Col(schema.PictureItemTableItemIDColName)
		pictureIDCol     = aliasTable.Col(schema.PictureItemTablePictureIDColName)
		perspectiveIDCol = aliasTable.Col(schema.PictureItemTablePerspectiveIDColName)
		typeCol          = aliasTable.Col(schema.PictureItemTableTypeColName)
	)

	if s.TypeID != 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.PictureItemTableTypeColName).Eq(s.TypeID))
	}

	if s.PictureID != 0 {
		sqSelect = sqSelect.Where(pictureIDCol.Eq(s.PictureID))
	}

	if s.ItemID != 0 {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemID))
	}

	if len(s.ItemIDs) > 0 {
		sqSelect = sqSelect.Where(itemIDCol.In(s.ItemIDs))
	}

	if s.ItemIDExpr != nil {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemIDExpr))
	}

	if s.PerspectiveID != 0 {
		sqSelect = sqSelect.Where(perspectiveIDCol.Eq(s.PerspectiveID))
	}

	if len(s.ExcludePerspectiveID) > 0 {
		sqSelect = sqSelect.Where(
			goqu.Or(
				perspectiveIDCol.NotIn(s.ExcludePerspectiveID),
				perspectiveIDCol.IsNull(),
			),
		)
	}

	if s.HasNoPerspectiveID {
		sqSelect = sqSelect.Where(
			perspectiveIDCol.IsNull(),
			typeCol.Eq(schema.PictureItemTypeContent),
		)
	}

	if s.ExcludeAncestorOrSelfID > 0 {
		eaosAlias := alias + "_eaos"
		eaosAliasTable := goqu.T(eaosAlias)
		sqSelect = sqSelect.
			LeftJoin(goqu.T(schema.ItemParentCacheTableName).As(eaosAlias), goqu.On(
				itemIDCol.Eq(eaosAliasTable.Col(schema.ItemParentCacheTableItemIDColName)),
				eaosAliasTable.Col(schema.ItemParentCacheTableParentIDColName).
					Eq(s.ExcludeAncestorOrSelfID),
			)).
			Where(eaosAliasTable.Col(schema.ItemParentCacheTableItemIDColName).IsNull())
	}

	if s.HasArea {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.PictureItemTableCropWidthColName),
			aliasTable.Col(schema.PictureItemTableCropHeightColName),
		)
	}

	sqSelect, err = s.Pictures.JoinToIDAndApply(
		pictureIDCol,
		AppendPictureAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect, _, err = s.Item.JoinToIDAndApply(itemIDCol, AppendItemAlias(alias, "i"), sqSelect)
	if err != nil {
		return nil, err
	}

	sqSelect, err = s.ItemParentCacheAncestor.JoinToItemIDAndApply(
		itemIDCol,
		s.ItemParentCacheAncestorAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect, err = s.ItemVehicleType.JoinToVehicleIDAndApply(
		itemIDCol,
		AppendItemVehicleTypeAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect, err = s.PictureItemByItemID.JoinToItemIDAndApply(
		itemIDCol,
		AppendPictureItemAlias(alias, ""),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect, err = s.PictureItemByPictureID.JoinToPictureIDAndApply(
		pictureIDCol,
		AppendPictureItemAlias(alias, ""),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	sqSelect = s.PerspectiveGroupPerspective.JoinToPerspectiveIDAndApply(
		aliasTable.Col(schema.PictureItemTablePerspectiveIDColName),
		AppendPerspectiveGroupPerspectiveAlias(alias),
		sqSelect,
	)

	return sqSelect, nil
}
