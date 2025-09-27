package mosts

import (
	"context"
	"slices"
	"strings"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type WheelAxis struct {
	Tyrewidth  int64
	Tyreseries int64
	Radius     int64
}

type Wheelsize struct {
	Front    WheelAxis
	Rear     WheelAxis
	OrderAsc bool
}

func (s Wheelsize) Items(
	ctx context.Context,
	db *goqu.Database,
	attrsRepository *attrs.Repository,
	listOptions *query.ItemListOptions,
	_ string,
) (*MostData, error) {
	wheel := s.Rear

	tyrewidth, err := attrsRepository.Attribute(ctx, wheel.Tyrewidth)
	if err != nil {
		return nil, err
	}

	tyrewidthValueTable, err := attrs.ValueTableByType(tyrewidth.TypeID.AttributeTypeID)
	if err != nil {
		return nil, err
	}

	tyreseries, err := attrsRepository.Attribute(ctx, wheel.Tyreseries)
	if err != nil {
		return nil, err
	}

	tyreseriesValueTable, err := attrs.ValueTableByType(tyreseries.TypeID.AttributeTypeID)
	if err != nil {
		return nil, err
	}

	radius, err := attrsRepository.Attribute(ctx, wheel.Radius)
	if err != nil {
		return nil, err
	}

	radiusValueTable, err := attrs.ValueTableByType(radius.TypeID.AttributeTypeID)
	if err != nil {
		return nil, err
	}

	const (
		tyrewidthAlias  = "tyrewidth"
		tyreseriesAlias = "tyreseries"
		radiusAlias     = "radius"
	)

	var (
		tyrewidthAliasTable  = goqu.T(tyrewidthAlias)
		tyreseriesAliasTable = goqu.T(tyreseriesAlias)
		radiusAliasTable     = goqu.T(radiusAlias)

		tyrewidthValCol  = tyrewidthAliasTable.Col(tyrewidthValueTable.ValueColName)
		tyreseriesValCol = tyreseriesAliasTable.Col(tyreseriesValueTable.ValueColName)
		radiusValCol     = radiusAliasTable.Col(radiusValueTable.ValueColName)

		iAliasTable = goqu.T(query.ItemAlias)
		itemIDCol   = iAliasTable.Col(schema.ItemTableIDColName)
	)

	orderCol := goqu.L("2*?*?/100+?*25.4", tyrewidthValCol, tyreseriesValCol, radiusValCol)
	orderExpr := orderCol.Desc()

	if s.OrderAsc {
		orderExpr = orderCol.Asc()
	}

	sqSelect, err := listOptions.Select(db, query.ItemAlias)
	if err != nil {
		return nil, err
	}

	var itemIDs []int64

	if !listOptions.IsIDUnique() {
		sqSelect = sqSelect.GroupBy(itemIDCol)
	}

	err = sqSelect.
		Select(itemIDCol).
		Join(tyrewidthValueTable.Table.As(tyrewidthAlias), goqu.On(
			itemIDCol.Eq(tyrewidthAliasTable.Col(tyrewidthValueTable.ItemIDColName)),
			tyrewidthAliasTable.Col(tyrewidthValueTable.AttributeIDColName).Eq(wheel.Tyrewidth),
			tyrewidthValCol.Gt(0),
		)).
		Join(tyreseriesValueTable.Table.As(tyreseriesAlias), goqu.On(
			itemIDCol.Eq(tyreseriesAliasTable.Col(tyrewidthValueTable.ItemIDColName)),
			tyreseriesAliasTable.Col(tyreseriesValueTable.AttributeIDColName).Eq(wheel.Tyreseries),
			tyreseriesValCol.Gt(0),
		)).
		Join(radiusValueTable.Table.As(radiusAlias), goqu.On(
			itemIDCol.Eq(radiusAliasTable.Col(tyrewidthValueTable.ItemIDColName)),
			radiusAliasTable.Col(radiusValueTable.AttributeIDColName).Eq(wheel.Radius),
			radiusValCol.Gt(0),
		)).
		Order(orderExpr).
		Limit(uint(listOptions.Limit)).
		ScanValsContext(ctx, &itemIDs)
	if err != nil {
		return nil, err
	}

	result := make([]MostDataCar, 0)

	for _, itemID := range itemIDs {
		valueHTML, err := s.wheelSizeText(ctx, attrsRepository, itemID)
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

func (s Wheelsize) wheelSizeText(
	ctx context.Context,
	attrsRepository *attrs.Repository,
	itemID int64,
) (string, error) {
	text := make([]string, 0, 2)

	for _, wheel := range []WheelAxis{s.Front, s.Rear} {
		tyrewidth, err := attrsRepository.ActualValue(ctx, wheel.Tyrewidth, itemID)
		if err != nil {
			return "", err
		}

		tyreseries, err := attrsRepository.ActualValue(ctx, wheel.Tyreseries, itemID)
		if err != nil {
			return "", err
		}

		radius, err := attrsRepository.ActualValue(ctx, wheel.Radius, itemID)
		if err != nil {
			return "", err
		}

		wheelObj := attrs.WheelSize{
			Width:  tyrewidth.IntValue,
			Series: tyreseries.IntValue,
			Radius: radius.FloatValue,
		}

		value := wheelObj.TyreName()
		if len(value) > 0 && slices.Index(text, value) == -1 {
			text = append(text, value)
		}
	}

	return strings.Join(text, "<br />"), nil
}
