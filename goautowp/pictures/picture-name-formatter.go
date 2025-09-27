package pictures

import (
	"html"
	"strings"

	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/util"
	"github.com/nicksnyder/go-i18n/v2/i18n"
)

type PictureNameFormatter struct {
	ItemNameFormatter *items.ItemNameFormatter
	i18nBundle        *i18nbundle.I18n
}

func NewPictureNameFormatter(
	itemNameFormatter *items.ItemNameFormatter, i18nBundle *i18nbundle.I18n,
) *PictureNameFormatter {
	return &PictureNameFormatter{
		ItemNameFormatter: itemNameFormatter,
		i18nBundle:        i18nBundle,
	}
}

type PictureNameFormatterOptions struct {
	Name  string
	Items []PictureNameFormatterItem
}

type PictureNameFormatterItem struct {
	Item        items.ItemNameFormatterOptions
	Perspective string
}

func (s *PictureNameFormatter) FormatText(
	picture PictureNameFormatterOptions, lang string,
) (string, error) {
	if picture.Name != "" {
		return picture.Name, nil
	}

	if len(picture.Items) > 1 {
		result := make([]string, 0)
		for _, item := range picture.Items {
			result = append(result, item.Item.Name)
		}

		return strings.Join(result, ", "), nil
	} else if len(picture.Items) == 1 {
		item := picture.Items[0]
		prefix := ""

		if item.Perspective != "" {
			localizer := s.i18nBundle.Localizer(lang)

			translated, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: item.Perspective})
			if err != nil {
				return "", err
			}

			prefix = util.TitleCase(translated, s.i18nBundle.Tag(lang)) + " "
		}

		formatted, err := s.ItemNameFormatter.FormatText(item.Item, lang)
		if err != nil {
			return "", err
		}

		return prefix + formatted, nil
	}

	return "Picture", nil
}

func (s *PictureNameFormatter) FormatHTML(
	picture PictureNameFormatterOptions, lang string,
) (string, error) {
	if picture.Name != "" {
		return html.EscapeString(picture.Name), nil
	}

	if len(picture.Items) > 1 {
		result := make([]string, 0)
		for _, item := range picture.Items {
			result = append(result, html.EscapeString(item.Item.Name))
		}

		return strings.Join(result, ", "), nil
	} else if len(picture.Items) == 1 {
		item := picture.Items[0]
		prefix := ""

		if item.Perspective != "" {
			localizer := s.i18nBundle.Localizer(lang)

			translated, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: item.Perspective})
			if err != nil {
				return "", err
			}

			prefix = html.EscapeString(util.TitleCase(translated, s.i18nBundle.Tag(lang))) + " "
		}

		formatted, err := s.ItemNameFormatter.FormatHTML(item.Item, lang)
		if err != nil {
			return "", err
		}

		return prefix + formatted, nil
	}

	return "Picture", nil
}
