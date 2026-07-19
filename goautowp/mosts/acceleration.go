package mosts

import (
	"context"
	"errors"
	"strings"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const mph60ToKmh100 = 0.98964381346271110050637609692728

var errUnexpectedAttributeType = errors.New("unexpected attribute type")

type Acceleration struct {
	To100kmhAttribute int64
	To60mphAttribute  int64
	OrderAsc          bool
}

func (s Acceleration) Items(
	ctx context.Context,
	db *goqu.Database,
	attrsRepository *attrs.Repository,
	listOptions *query.ItemListOptions,
	lang string,
) (*MostData, error) {
	to100kmhAttribute, err := attrsRepository.Attribute(ctx, s.To100kmhAttribute)
	if err != nil {
		return nil, err
	}

	to60mphAttribute, err := attrsRepository.Attribute(ctx, s.To60mphAttribute)
	if err != nil {
		return nil, err
	}

	axises := []struct {
		Attr *schema.AttrsAttributeRow
		Q    float64
	}{
		{
			Attr: to100kmhAttribute,
			Q:    1,
		},
		{
			Attr: to60mphAttribute,
			Q:    mph60ToKmh100,
		},
	}

	const valueColumnAlias = "size_value"

	valueColumnAliasCol := goqu.C(valueColumnAlias)

	const alias = "axis"

	aliasTable := goqu.T(alias)
	iAliasTable := goqu.T(query.ItemAlias)
	itemIDCol := iAliasTable.Col(schema.ItemTableIDColName)

	selects := make([]*goqu.SelectDataset, 0, len(axises))

	for _, axis := range axises {
		if !axis.Attr.TypeID.Valid {
			return nil, errUnexpectedAttributeType
		}

		attrValuesTable, err := attrs.ValueTableByType(axis.Attr.TypeID.AttributeTypeID)
		if err != nil {
			return nil, err
		}

		sqSelect, err := listOptions.Select(db, query.ItemAlias)
		if err != nil {
			return nil, err
		}

		valueCol := aliasTable.Col(attrValuesTable.ValueColName)

		var valueColumn exp.Aliaseable = valueCol
		if axis.Q != 1 {
			valueColumn = goqu.L("? / ?", valueCol, axis.Q)
		}

		axisSelect := sqSelect.
			Select(itemIDCol, valueColumn.As(valueColumnAlias)).
			Join(attrValuesTable.Table.As(alias), goqu.On(itemIDCol.Eq(aliasTable.Col(attrValuesTable.ItemIDColName)))).
			Where(
				aliasTable.Col(attrValuesTable.AttributeIDColName).Eq(axis.Attr.ID),
				valueCol.Gt(0),
			).
			GroupBy(itemIDCol, goqu.C(valueColumnAlias)).
			Limit(uint(listOptions.Limit))

		if s.OrderAsc {
			axisSelect = axisSelect.Order(valueColumnAliasCol.Asc())
		} else {
			axisSelect = axisSelect.Order(valueColumnAliasCol.Desc())
		}

		selects = append(selects, axisSelect)
	}

	const tblAlias = "tbl"

	tblValueCol := goqu.T(tblAlias).Col(valueColumnAlias)

	orderExpr := tblValueCol.Desc()
	if s.OrderAsc {
		orderExpr = tblValueCol.Asc()
	}

	var itemIDs []int64

	err = db.Select(schema.ItemTableIDColName).
		From(selects[0].UnionAll(selects[1]).As(tblAlias)).
		Order(orderExpr).
		Limit(uint(listOptions.Limit)).
		ScanValsContext(ctx, &itemIDs)
	if err != nil {
		return nil, err
	}

	result := make([]MostDataCar, 0, len(itemIDs))

	for _, itemID := range itemIDs {
		valueHTML, err := s.text(ctx, attrsRepository, itemID, lang)
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

func (s Acceleration) text(
	ctx context.Context, attrsRepository *attrs.Repository, itemID int64, lang string,
) (string, error) {
	text := make([]string, 0)

	axises := []struct {
		Attr int64
		Unit string
	}{
		{
			Attr: s.To100kmhAttribute,
			Unit: "сек до&#xa0;100&#xa0;км/ч",
		},
		{
			Attr: s.To60mphAttribute,
			Unit: "сек до&#xa0;60&#xa0;миль/ч",
		},
	}

	for _, axis := range axises {
		_, value, err := attrsRepository.ActualValueText(ctx, axis.Attr, itemID, lang)
		if err != nil {
			return "", err
		}

		if len(value) > 0 {
			text = append(text, value+` <span class="unit">`+axis.Unit+"</span>")
		}
	}

	return strings.Join(text, "<br />"), nil
}
