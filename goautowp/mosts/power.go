package mosts

import (
	"context"
	"fmt"
	"html"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const cm3PerLiter = 1000

type Power struct {
	OrderAsc                  bool
	PowerAttribute            int64
	TurboAttribute            int64
	VolumeAttribute           int64
	CylindersLayoutAttribute  int64
	CylindersCountAttribute   int64
	ValvePerCylinderAttribute int64
}

func (s Power) Items(
	ctx context.Context,
	db *goqu.Database,
	attrsRepository *attrs.Repository,
	listOptions *query.ItemListOptions,
	lang string,
) (*MostData, error) {
	powerAttr, err := attrsRepository.Attribute(ctx, s.PowerAttribute)
	if err != nil {
		return nil, err
	}

	valueTable, err := attrs.ValueTableByType(powerAttr.TypeID.AttributeTypeID)
	if err != nil {
		return nil, err
	}

	orderExpr := valueTable.ValueCol.Desc()
	if s.OrderAsc {
		orderExpr = valueTable.ValueCol.Asc()
	}

	sqSelect, err := listOptions.Select(db, query.ItemAlias)
	if err != nil {
		return nil, err
	}

	iAliasTable := goqu.T(query.ItemAlias)
	itemIDCol := iAliasTable.Col(schema.ItemTableIDColName)

	var itemIDs []int64

	if !listOptions.IsIDUnique() {
		sqSelect = sqSelect.GroupBy(itemIDCol)
	}

	err = sqSelect.
		Select(itemIDCol).
		Join(valueTable.Table, goqu.On(itemIDCol.Eq(valueTable.ItemIDCol))).
		Where(
			valueTable.AttributeIDCol.Eq(s.PowerAttribute),
			valueTable.ValueCol.Gt(0),
		).
		Order(orderExpr).
		Limit(uint(listOptions.Limit)).
		ScanValsContext(ctx, &itemIDs)
	if err != nil {
		return nil, err
	}

	result := make([]MostDataCar, 0)

	for _, itemID := range itemIDs {
		valueHTML := ""

		_, value, err := attrsRepository.ActualValueText(ctx, s.PowerAttribute, itemID, lang)
		if err != nil {
			return nil, err
		}

		turboValue, turbo, err := attrsRepository.ActualValueText(
			ctx,
			s.TurboAttribute,
			itemID,
			lang,
		)
		if err != nil {
			return nil, err
		}

		for _, turboValueItem := range turboValue.ListValue {
			switch turboValueItem {
			case schema.EngineTurboNone:
				turbo = ""
			case schema.EngineTurboYes:
				turbo = "турбонаддув"
			default:
				if len(turbo) > 0 {
					turbo = "турбонаддув " + turbo
				}
			}
		}

		volume, err := attrsRepository.ActualValue(ctx, s.VolumeAttribute, itemID)
		if err != nil {
			return nil, err
		}

		_, cylindersLayout, err := attrsRepository.ActualValueText(
			ctx,
			s.CylindersLayoutAttribute,
			itemID,
			lang,
		)
		if err != nil {
			return nil, err
		}

		cylindersCount, err := attrsRepository.ActualValue(ctx, s.CylindersCountAttribute, itemID)
		if err != nil {
			return nil, err
		}

		valvePerCylinder, err := attrsRepository.ActualValue(
			ctx,
			s.ValvePerCylinderAttribute,
			itemID,
		)
		if err != nil {
			return nil, err
		}

		cyl := s.cylinders(cylindersLayout, cylindersCount.IntValue, valvePerCylinder.IntValue)

		valueHTML += value
		valueHTML += ` <span class="unit">л.с.</span>`

		if len(cyl) > 0 || len(turbo) > 0 || volume.IntValue > 0 {
			components := make([]string, 0)

			if len(cyl) > 0 {
				components = append(components, html.EscapeString(cyl))
			}

			if volume.IntValue > 0 {
				components = append(
					components,
					fmt.Sprintf(
						`%0.1f <span class="unit">л</span>`,
						float64(volume.IntValue)/cm3PerLiter,
					),
				)
			}

			if len(turbo) > 0 {
				components = append(components, turbo)
			}

			valueHTML += `<p class="note">` + strings.Join(components, ", ") + "</p>"
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

func (s Power) cylinders(layout string, cylinders int32, valvePerCylinder int32) string {
	var result string

	if len(layout) > 0 {
		if cylinders > 0 {
			result = layout + strconv.FormatInt(int64(cylinders), 10)
		} else {
			result = layout + "?"
		}
	} else {
		if cylinders > 0 {
			result = strconv.FormatInt(int64(cylinders), 10)
		} else {
			result = ""
		}
	}

	if valvePerCylinder > 0 {
		result += "/" + strconv.FormatInt(int64(valvePerCylinder), 10)
	}

	return result
}
