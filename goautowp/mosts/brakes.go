package mosts

import (
	"context"
	"html"
	"maps"
	"slices"
	"strings"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type BrakesAxis struct {
	Diameter  int64
	Thickness int64
}

type Brakes struct {
	Front    BrakesAxis
	Rear     BrakesAxis
	OrderAsc bool
}

func (s Brakes) Items(
	ctx context.Context,
	db *goqu.Database,
	attrsRepository *attrs.Repository,
	listOptions *query.ItemListOptions,
	lang string,
) (*MostData, error) {
	rear := s.Rear
	front := s.Front

	const (
		diameterAlias  = "diameter"
		thicknessAlias = "thickness"
		sizeAlias      = "size_value"
	)

	diameterAliasTable := goqu.T(diameterAlias)
	thicknessAliasTable := goqu.T(thicknessAlias)
	iAliasTable := goqu.T(query.ItemAlias)
	itemIDCol := iAliasTable.Col(schema.ItemTableIDColName)
	sizeAliasCol := goqu.T(sizeAlias)

	orderExp := sizeAliasCol.Desc()
	if s.OrderAsc {
		orderExp = sizeAliasCol.Asc()
	}

	selects := make([]*goqu.SelectDataset, 0)

	for _, axis := range []BrakesAxis{rear, front} {
		axisSelect, err := listOptions.Select(db, query.ItemAlias)
		if err != nil {
			return nil, err
		}

		diameter, err := attrsRepository.Attribute(ctx, axis.Diameter)
		if err != nil {
			return nil, err
		}

		diameterValueTable, err := attrs.ValueTableByType(diameter.TypeID.AttributeTypeID)
		if err != nil {
			return nil, err
		}

		thickness, err := attrsRepository.Attribute(ctx, axis.Thickness)
		if err != nil {
			return nil, err
		}

		thicknessValueTable, err := attrs.ValueTableByType(thickness.TypeID.AttributeTypeID)
		if err != nil {
			return nil, err
		}

		axisSelect = axisSelect.Select(
			itemIDCol,
			goqu.L("? * ?",
				diameterAliasTable.Col(diameterValueTable.ValueColName),
				thicknessAliasTable.Col(thicknessValueTable.ValueColName),
			).As(sizeAlias),
		).
			Join(diameterValueTable.Table.As(diameterAlias), goqu.On(
				itemIDCol.Eq(diameterAliasTable.Col(diameterValueTable.ItemIDColName)),
				diameterAliasTable.Col(diameterValueTable.AttributeIDColName).Eq(axis.Diameter),
				diameterAliasTable.Col(diameterValueTable.ValueColName).Gt(0),
			)).
			Join(thicknessValueTable.Table.As(thicknessAlias), goqu.On(
				itemIDCol.Eq(thicknessAliasTable.Col(thicknessValueTable.ItemIDColName)),
				thicknessAliasTable.Col(thicknessValueTable.AttributeIDColName).Eq(axis.Thickness),
				thicknessAliasTable.Col(thicknessValueTable.ValueColName).Gt(0),
			)).
			Order(orderExp).
			Limit(uint(listOptions.Limit))

		if !listOptions.IsIDUnique() {
			axisSelect = axisSelect.GroupBy(itemIDCol)
		}

		axisSelect = axisSelect.GroupByAppend(sizeAliasCol)

		selects = append(selects, axisSelect)
	}

	unionSelect := selects[0].UnionAll(selects[1])

	const unionAlias = "tbl"

	unionAliasTable := goqu.T(unionAlias)

	orderExpr := goqu.MAX(unionAliasTable.Col(sizeAlias)).Desc()
	if s.OrderAsc {
		orderExpr = goqu.MIN(unionAliasTable.Col(sizeAlias)).Asc()
	}

	var itemIDs []int64

	err := db.Select(schema.ItemTableIDColName).
		From(unionSelect.As(unionAlias)).
		GroupBy(schema.ItemTableIDColName).
		Order(orderExpr).
		Limit(uint(listOptions.Limit)).
		ScanValsContext(ctx, &itemIDs)
	if err != nil {
		return nil, err
	}

	result := make([]MostDataCar, 0, len(itemIDs))

	for _, itemID := range itemIDs {
		valueHTML, err := s.brakesHTML(ctx, attrsRepository, itemID, lang)
		if err != nil {
			return nil, err
		}

		result = append(result, MostDataCar{
			ItemID:    itemID,
			ValueHTML: valueHTML,
		})
	}

	return &MostData{
		UnitID: 0,
		Cars:   result,
	}, nil
}

func (s Brakes) brakesHTML(
	ctx context.Context, attrsRepository *attrs.Repository, itemID int64, lang string,
) (string, error) {
	axises := []BrakesAxis{s.Front, s.Rear}

	text := make(map[string]bool, len(axises))

	for _, axis := range axises {
		_, diameterValue, err := attrsRepository.ActualValueText(ctx, axis.Diameter, itemID, lang)
		if err != nil {
			return "", err
		}

		_, thicknessValue, err := attrsRepository.ActualValueText(ctx, axis.Thickness, itemID, lang)
		if err != nil {
			return "", err
		}

		if len(diameterValue) > 0 || len(thicknessValue) > 0 {
			value := html.EscapeString(diameterValue) + " × " + html.EscapeString(thicknessValue) +
				` <span class="unit">мм</span>`

			text[value] = true
		}
	}

	return strings.Join(slices.Collect(maps.Keys(text)), "<br />"), nil
}
