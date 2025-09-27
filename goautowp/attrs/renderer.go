package attrs

import (
	"fmt"
	"html/template"
	"strings"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
)

type Renderer interface {
	Render(attribute *AttributeRow, values map[int64]string, units map[int64]I18nUnit) template.HTML
}

func unitSuffixHTML(name, abbr string) template.HTML {
	return ` <span class="unit" title="` + util.HTMLEscapeString(
		name,
	) + `">` + util.HTMLEscapeString(
		abbr,
	) + "</span>"
}

func renderUnitSuffix(attribute *AttributeRow, units map[int64]I18nUnit) template.HTML {
	var res template.HTML

	if attribute.UnitID.Valid {
		if unit, ok := units[attribute.UnitID.Int64]; ok {
			res += unitSuffixHTML(unit.Name, unit.Abbr)
		}
	}

	return res
}

type BootVolume struct {
	Min int64
	Max int64
}

func (s BootVolume) Render(
	attribute *AttributeRow,
	values map[int64]string,
	units map[int64]I18nUnit,
) template.HTML {
	minValue, minValueOk := values[s.Min]
	maxValue, maxValueOk := values[s.Max]

	var res template.HTML

	if minValueOk && len(minValue) > 0 {
		if maxValueOk && len(maxValue) > 0 && minValue != maxValue {
			res = util.HTMLEscapeString(minValue) + "&ndash;" + util.HTMLEscapeString(maxValue)
		} else {
			res = util.HTMLEscapeString(minValue)
		}
	}

	if len(res) > 0 {
		res += renderUnitSuffix(attribute, units)
	}

	return res
}

type DefaultValue struct{}

func (s DefaultValue) Render(
	attribute *AttributeRow,
	values map[int64]string,
	units map[int64]I18nUnit,
) template.HTML {
	value, ok := values[attribute.ID]
	if !ok {
		return ""
	}

	if value == "—" {
		return ""
	}

	return util.HTMLEscapeString(value) + renderUnitSuffix(attribute, units)
}

type EngineConfiguration struct {
	CylindersCount  int64
	CylindersLayout int64
	ValvesCount     int64
}

func (s EngineConfiguration) Render(
	_ *AttributeRow,
	values map[int64]string,
	_ map[int64]I18nUnit,
) template.HTML {
	cylinders, cylindersOk := values[s.CylindersCount]
	layout, layoutOk := values[s.CylindersLayout]
	valves, valvesOk := values[s.ValvesCount]

	var result string

	if layoutOk && len(layout) > 0 {
		if cylindersOk && len(cylinders) > 0 {
			result = layout + cylinders
		} else {
			result = layout + "?"
		}
	} else {
		if cylindersOk && len(cylinders) > 0 {
			result = cylinders
		} else {
			result = ""
		}
	}

	if valvesOk && len(valves) > 0 {
		result += "/" + valves
	}

	return util.HTMLEscapeString(result)
}

type EnginePlacement struct {
	Placement   int64
	Orientation int64
}

func (s EnginePlacement) Render(
	_ *AttributeRow,
	values map[int64]string,
	_ map[int64]I18nUnit,
) template.HTML {
	placement, placementOk := values[s.Placement]
	orientation, orientationOk := values[s.Orientation]

	var array []string

	if placementOk && len(placement) > 0 {
		array = append(array, placement)
	}

	if orientationOk && len(orientation) > 0 {
		array = append(array, orientation)
	}

	return template.HTML(strings.Join(array, ", ")) //nolint: gosec
}

type FuelTank struct {
	Primary   int64
	Secondary int64
}

func (s FuelTank) Render(
	_ *AttributeRow,
	values map[int64]string,
	units map[int64]I18nUnit,
) template.HTML {
	primary, primaryOk := values[s.Primary]
	secondary, secondaryOk := values[s.Secondary]

	var res template.HTML

	if primaryOk && len(primary) > 0 {
		res = util.HTMLEscapeString(primary)
	}

	if secondaryOk && len(secondary) > 0 {
		res += "+" + util.HTMLEscapeString(secondary)
	}

	if len(res) > 0 {
		if unit, ok := units[schema.FuelTankPrimaryAttr]; ok {
			res += unitSuffixHTML(unit.Name, unit.Abbr)
		}
	}

	return res
}

type Gearbox struct {
	Type  int64
	Gears int64
	Name  int64
}

func (s Gearbox) Render(
	_ *AttributeRow,
	values map[int64]string,
	_ map[int64]I18nUnit,
) template.HTML {
	typeVal, typeOk := values[s.Type]
	gears, gearsOk := values[s.Gears]
	name, nameOk := values[s.Name]

	result := ""
	if typeOk && len(typeVal) > 0 {
		result += typeVal
	}

	if gearsOk && len(gears) > 0 {
		if len(result) > 0 {
			result += " " + gears
		} else {
			result += gears
		}
	}

	if nameOk && len(name) > 0 {
		if len(result) > 0 {
			result += " (" + name + ")"
		} else {
			result = name
		}
	}

	return util.HTMLEscapeString(result)
}

type Wheel struct {
	TyreWidth  int64
	TyreSeries int64
	Radius     int64
	Rimwidth   int64
}

func (s Wheel) Render(
	_ *AttributeRow,
	values map[int64]string,
	_ map[int64]I18nUnit,
) template.HTML {
	tyreWidth, tyreWidthOk := values[s.TyreWidth]
	tyreSeries, tyreSeriesOk := values[s.TyreSeries]
	radius, radiusOk := values[s.Radius]
	rimWidth, rimWidthOk := values[s.Rimwidth]

	diskName := ""
	if rimWidthOk && len(rimWidth) > 0 || radiusOk && len(radius) > 0 {
		diskName = fmt.Sprintf(
			"%sJ × %s",
			util.StringDefault(rimWidth, "?"),
			util.StringDefault(radius, "??"),
		)
	}

	tyreName := ""
	if tyreWidthOk && len(tyreWidth) > 0 || tyreSeriesOk && len(tyreSeries) > 0 ||
		radiusOk && len(radius) > 0 {
		tyreName = fmt.Sprintf(
			"%s/%s R%s",
			util.StringDefault(tyreWidth, "???"),
			util.StringDefault(tyreSeries, "??"),
			util.StringDefault(radius, "??"),
		)
	}

	return util.HTMLEscapeString(diskName) + "<br />" + util.HTMLEscapeString(tyreName)
}
