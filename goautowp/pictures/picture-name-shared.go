package pictures

import (
	"slices"
	"strings"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
)

// Shared details are formatted as a single item, preserving the ordinary text and HTML
// year formatting, market badges, tooltips and escaping.
func (s *PictureNameFormatter) sharedPictureItem(
	pictureItems []PictureNameFormatterItem,
	lang string,
) (items.ItemNameFormatterOptions, error) {
	shared := pictureItems[0].Item

	name, err := s.i18nBundle.FormatList(pictureItemNames(pictureItems), lang)
	if err != nil {
		return items.ItemNameFormatterOptions{}, err
	}

	shared.Name = name

	for _, pictureItem := range pictureItems[1:] {
		item := pictureItem.Item
		if shared.Spec != item.Spec {
			shared.Spec = ""
			shared.SpecFull = ""
		}

		if shared.SpecFull != item.SpecFull {
			shared.SpecFull = ""
		}

		if shared.Body != item.Body {
			shared.Body = ""
		}

		if !samePictureModelYears(shared, item) {
			shared.BeginModelYear = 0
			shared.EndModelYear = 0
			shared.BeginModelYearFraction = ""
			shared.EndModelYearFraction = ""
		}

		if !samePictureProductionYears(shared, item) {
			shared.BeginYear = 0
			shared.EndYear = 0
			shared.BeginMonth = 0
			shared.EndMonth = 0
		}
	}

	return shared, nil
}

func samePictureModelYears(left, right items.ItemNameFormatterOptions) bool {
	sameYears := left.BeginModelYear == right.BeginModelYear && left.EndModelYear == right.EndModelYear
	sameFractions := left.BeginModelYearFraction == right.BeginModelYearFraction &&
		left.EndModelYearFraction == right.EndModelYearFraction
	sameToday := left.EndModelYear > 0 || pictureItemToday(left) == pictureItemToday(right)

	return sameYears && sameFractions && sameToday
}

func samePictureProductionYears(left, right items.ItemNameFormatterOptions) bool {
	sameYears := left.BeginYear == right.BeginYear && left.EndYear == right.EndYear
	sameMonths := left.BeginMonth == right.BeginMonth && left.EndMonth == right.EndMonth
	sameToday := left.EndYear > 0 || pictureItemToday(left) == pictureItemToday(right)

	return sameYears && sameMonths && sameToday
}

func pictureItemToday(item items.ItemNameFormatterOptions) bool {
	return item.Today != nil && *item.Today
}

func pictureItemNames(pictureItems []PictureNameFormatterItem) []string {
	pictureItems = slices.Clone(pictureItems)
	slices.SortStableFunc(pictureItems, func(left, right PictureNameFormatterItem) int {
		leftPerson := left.ItemTypeID == schema.ItemTableItemTypeIDPerson
		rightPerson := right.ItemTypeID == schema.ItemTableItemTypeIDPerson

		if leftPerson == rightPerson {
			return 0
		}

		if leftPerson {
			return -1
		}

		return 1
	})

	names := make([]string, 0, len(pictureItems))
	previousWords := []string{}

	for _, pictureItem := range pictureItems {
		name := pictureItem.Item.Name
		if pictureItem.ItemTypeID == schema.ItemTableItemTypeIDPerson {
			names = append(names, name)
			previousWords = []string{}

			continue
		}

		words := strings.Fields(name)
		common := 0

		for common < len(words) && common < len(previousWords) && words[common] == previousWords[common] {
			common++
		}

		// Keep complete names when one name is a prefix of the other, or they are identical.
		if common > 0 && common < len(words) && common < len(previousWords) {
			name = strings.Join(words[common:], " ")
		}

		names = append(names, name)
		previousWords = words
	}

	return names
}
