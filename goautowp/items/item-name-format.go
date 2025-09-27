package items

import (
	"fmt"
	"html"
	"strconv"
	"time"

	"github.com/autowp/goautowp/i18nbundle"
	"github.com/nicksnyder/go-i18n/v2/i18n"
)

const (
	textMonthFormat = "%02d."
	htmlMonthFormat = `<small class="month">%02d.</small>`
)

const hundred = 100

const rangeDelimiter = "–"

const unknownYear = "????"

type ItemNameFormatter struct {
	i18nBundle *i18nbundle.I18n
}

func NewItemNameFormatter(i18nBundle *i18nbundle.I18n) *ItemNameFormatter {
	return &ItemNameFormatter{i18nBundle: i18nBundle}
}

type ItemNameFormatterOptions struct {
	BeginModelYear         int32
	EndModelYear           int32
	BeginModelYearFraction string
	EndModelYearFraction   string
	Spec                   string
	SpecFull               string
	Body                   string
	Name                   string
	BeginYear              int32
	EndYear                int32
	Today                  *bool
	BeginMonth             int16
	EndMonth               int16
}

func (s *ItemNameFormatter) FormatText(item ItemNameFormatterOptions, lang string) (string, error) {
	result := item.Name

	if len(item.Spec) > 0 {
		result += " [" + item.Spec + "]"
	}

	if len(item.Body) > 0 {
		result += " (" + item.Body + ")"
	}

	by := item.BeginYear
	bm := item.BeginMonth
	ey := item.EndYear
	em := item.EndMonth

	bmy := item.BeginModelYear
	emy := item.EndModelYear

	bmyf := item.BeginModelYearFraction
	emyf := item.EndModelYearFraction

	bs := by / hundred
	es := ey / hundred

	useModelYear := bmy > 0 || emy > 0

	equalS := bs > 0 && es > 0 && (bs == es)
	equalY := equalS && by > 0 && ey > 0 && (by == ey)
	equalM := equalY && bm > 0 && em > 0 && (bm == em)

	localizer := s.i18nBundle.Localizer(lang)

	if useModelYear {
		modelYearsPrefix, err := s.getModelYearsPrefix(bmy, bmyf, emy, emyf, item.Today, localizer)
		if err != nil {
			return "", err
		}

		result = modelYearsPrefix + " " + result
	}

	if by > 0 || ey > 0 {
		renderedYears, err := s.renderYears(
			item.Today,
			by,
			bm,
			ey,
			em,
			equalS,
			equalY,
			equalM,
			localizer,
		)
		if err != nil {
			return "", err
		}

		result += " '" + renderedYears
	}

	return result, nil
}

func (s *ItemNameFormatter) FormatHTML(item ItemNameFormatterOptions, lang string) (string, error) {
	result := html.EscapeString(item.Name)

	if len(item.Spec) > 0 {
		attrs := `class="badge bg-info text-dark"`
		if len(item.SpecFull) > 0 {
			attrs += ` title="` + html.EscapeString(
				item.SpecFull,
			) + `" data-toggle="tooltip" data-placement="top"`
		}

		result += " <span " + attrs + ">" + html.EscapeString(item.Spec) + "</span>"
	}

	if len(item.Body) > 0 {
		result += " (" + html.EscapeString(item.Body) + ")"
	}

	by := item.BeginYear
	bm := item.BeginMonth
	ey := item.EndYear
	em := item.EndMonth

	bmy := item.BeginModelYear
	emy := item.EndModelYear

	bmyf := item.BeginModelYearFraction
	emyf := item.EndModelYearFraction

	useModelYear := bmy > 0 || emy > 0

	localizer := s.i18nBundle.Localizer(lang)

	if useModelYear {
		modelYearsMsg, err := localizer.Localize(
			&i18n.LocalizeConfig{MessageID: "carlist/model-years"},
		)
		if err != nil {
			return "", err
		}

		modelYearsPrefix, err := s.getModelYearsPrefix(bmy, bmyf, emy, emyf, item.Today, localizer)
		if err != nil {
			return "", err
		}

		result = `<span title="` + html.EscapeString(
			modelYearsMsg,
		) + `">` + html.EscapeString(
			modelYearsPrefix,
		) +
			"</span> " + result

		if by > 0 || ey > 0 {
			yearsMsg, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: "carlist/years"})
			if err != nil {
				return "", err
			}

			renderedYears, err := RenderYearsHTML(
				item.Today,
				by,
				bm,
				ey,
				em,
				localizer,
			)
			if err != nil {
				return "", err
			}

			result += `<small> '<span class="realyears" title="` + html.EscapeString(
				yearsMsg,
			) + `">` +
				renderedYears +
				"</span></small>"
		}
	} else if by > 0 || ey > 0 {
		renderedYears, err := RenderYearsHTML(
			item.Today,
			by,
			bm,
			ey,
			em,
			localizer,
		)
		if err != nil {
			return "", err
		}

		result += " '" + renderedYears
	}

	return result, nil
}

func (s *ItemNameFormatter) getModelYearsPrefix(
	begin int32,
	beginFraction string,
	end int32,
	endFraction string,
	today *bool,
	localizer *i18n.Localizer,
) (string, error) {
	if end == begin && beginFraction == endFraction {
		return fmt.Sprintf("%d%s", begin, endFraction), nil
	}

	bms := begin / hundred
	ems := end / hundred

	if bms == ems {
		return fmt.Sprintf("%d%s–%02d%s", begin, beginFraction, end%hundred, endFraction), nil
	}

	if begin <= 0 {
		return unknownYear + rangeDelimiter + fmt.Sprintf("%02d%s", end, endFraction), nil
	}

	if end > 0 {
		return fmt.Sprintf("%d%s–%d%s", begin, beginFraction, end, endFraction), nil
	}

	if today == nil || !*today {
		return fmt.Sprintf("%d%s–??", begin, beginFraction), nil
	}

	currentYear := int32(time.Now().Year()) //nolint: gosec

	if begin >= currentYear {
		return fmt.Sprintf("%d%s", begin, beginFraction), nil
	}

	prMsg, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: "present-time-abbr"})
	if err != nil {
		return "", err
	}

	return fmt.Sprintf("%d%s–%s", begin, beginFraction, prMsg), nil
}

func (s *ItemNameFormatter) renderYears(
	today *bool,
	by int32,
	bm int16,
	ey int32,
	em int16,
	equalS bool,
	equalY bool,
	equalM bool,
	localizer *i18n.Localizer,
) (string, error) {
	var err error

	if equalM {
		return fmt.Sprintf(textMonthFormat+"%d", bm, by), nil
	}

	if equalY {
		if bm > 0 && em > 0 {
			return monthsRange(bm, em) + "." + strconv.Itoa(int(by)), nil
		}

		return strconv.Itoa(int(by)), nil
	}

	if equalS {
		result1 := ""
		if bm > 0 {
			result1 = fmt.Sprintf(textMonthFormat, bm)
		}

		result1 += strconv.Itoa(int(by)) + rangeDelimiter

		result2 := ""
		if em > 0 {
			result2 = fmt.Sprintf(textMonthFormat, em)
		}

		var result3 string
		if em > 0 {
			result3 = strconv.Itoa(int(ey))
		} else {
			result3 = fmt.Sprintf("%02d", ey%hundred)
		}

		return result1 + result2 + result3, nil
	}

	result1 := ""
	if bm > 0 {
		result1 = fmt.Sprintf(textMonthFormat, bm)
	}

	result2 := unknownYear

	if by > 0 {
		result2 = strconv.Itoa(int(by))
	}

	result3 := ""

	if ey > 0 {
		if em > 0 {
			result3 = fmt.Sprintf(textMonthFormat, em)
		}

		result3 = rangeDelimiter + result3 + strconv.Itoa(int(ey))
	} else {
		result3, err = missedEndYearYearsSuffix(today, by, localizer)
		if err != nil {
			return "", err
		}
	}

	return result1 + result2 + result3, nil
}

func RenderYearsHTML(
	today *bool,
	by int32,
	bm int16,
	ey int32,
	em int16,
	localizer *i18n.Localizer,
) (string, error) {
	var err error

	bs := by / hundred
	es := ey / hundred
	equalS := bs > 0 && es > 0 && (bs == es)
	equalY := equalS && by > 0 && ey > 0 && (by == ey)
	equalM := equalY && bm > 0 && em > 0 && (bm == em)

	if equalM {
		return fmt.Sprintf(textMonthFormat+"%d", bm, by), nil
	}

	if equalY {
		if bm > 0 && em > 0 {
			return `<small class="month">` + monthsRange(
				bm,
				em,
			) + ".</small>" + strconv.Itoa(
				int(by),
			), nil
		}

		return strconv.Itoa(int(by)), nil
	}

	if equalS {
		result1 := ""
		if bm > 0 {
			result1 = fmt.Sprintf(htmlMonthFormat, bm)
		}

		result1 += strconv.Itoa(int(by)) + rangeDelimiter

		result2 := ""
		if em > 0 {
			result2 = fmt.Sprintf(htmlMonthFormat, em)
		}

		var result3 string
		if em > 0 {
			result3 = strconv.Itoa(int(ey))
		} else {
			result3 = fmt.Sprintf("%02d", ey%hundred)
		}

		return result1 + result2 + result3, nil
	}

	result1 := ""
	if bm > 0 {
		result1 = fmt.Sprintf(htmlMonthFormat, bm)
	}

	result2 := unknownYear
	if by > 0 {
		result2 = strconv.Itoa(int(by))
	}

	result3 := ""

	if ey > 0 {
		if em > 0 {
			result3 = fmt.Sprintf(htmlMonthFormat, em)
		}

		result3 = rangeDelimiter + result3 + strconv.Itoa(int(ey))
	} else {
		result3, err = missedEndYearYearsSuffix(today, by, localizer)
		if err != nil {
			return "", err
		}
	}

	return result1 + result2 + result3, nil
}

func missedEndYearYearsSuffix(today *bool, by int32, localizer *i18n.Localizer) (string, error) {
	currentYear := int32(time.Now().Year()) //nolint: gosec

	if by >= currentYear {
		return "", nil
	}

	if today != nil && *today {
		prMsg, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: "present-time-abbr"})
		if err != nil {
			return "", err
		}

		return rangeDelimiter + prMsg, nil
	}

	return rangeDelimiter + unknownYear, nil
}

func monthsRange(from int16, to int16) string {
	result1 := "??"
	if from > 0 {
		result1 = fmt.Sprintf("%02d", from)
	}

	result2 := "??"
	if to > 0 {
		result2 = fmt.Sprintf("%02d", to)
	}

	return result1 + rangeDelimiter + result2
}
