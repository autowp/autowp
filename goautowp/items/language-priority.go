package items

import (
	"fmt"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

var languagePriority = map[string][]string{
	schema.DefaultLanguageCode: {
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.EnglishLanguageCode: {
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.FrenchLanguageCode: {
		schema.FrenchLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.PortugueseLanguageCode: {
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.PortugueseBrazilianLanguageCode: {
		schema.PortugueseBrazilianLanguageCode,
		schema.PortugueseLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.RussianLanguageCode: {
		schema.RussianLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.BelarusianLanguageCode: {
		schema.BelarusianLanguageCode,
		schema.RussianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.UkrainianLanguageCode: {
		schema.UkrainianLanguageCode,
		schema.RussianLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.SimplifiedChineseLanguageCode: {
		schema.SimplifiedChineseLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.SpanishLanguageCode: {
		schema.SpanishLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.ItalianLanguageCode: {
		schema.ItalianLanguageCode,
		schema.EnglishLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.HebrewLanguageCode: {
		schema.HebrewLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.JapaneseLanguageCode: {
		schema.JapaneseLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.GermanLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
	schema.GermanLanguageCode: {
		schema.GermanLanguageCode,
		schema.EnglishLanguageCode,
		schema.ItalianLanguageCode,
		schema.FrenchLanguageCode,
		schema.SpanishLanguageCode,
		schema.PortugueseLanguageCode,
		schema.PortugueseBrazilianLanguageCode,
		schema.RussianLanguageCode,
		schema.BelarusianLanguageCode,
		schema.UkrainianLanguageCode,
		schema.SimplifiedChineseLanguageCode,
		schema.JapaneseLanguageCode,
		schema.HebrewLanguageCode,
		schema.DefaultLanguageCode,
	},
}

// cachedLanguages is the set of viewer/UI languages item_language_cache stores a resolved name
// for - i.e. the site's selectable languages (config.Languages keys). schema.DefaultLanguageCode
// ("xx") and the plain schema.PortugueseLanguageCode ("pt") are deliberately excluded: they're
// never a real viewer's requested language, only fallback rungs within languagePriority itself.
var cachedLanguages = map[string]struct{}{
	schema.EnglishLanguageCode:             {},
	schema.SimplifiedChineseLanguageCode:   {},
	schema.RussianLanguageCode:             {},
	schema.PortugueseBrazilianLanguageCode: {},
	schema.FrenchLanguageCode:              {},
	schema.BelarusianLanguageCode:          {},
	schema.UkrainianLanguageCode:           {},
	schema.SpanishLanguageCode:             {},
	schema.ItalianLanguageCode:             {},
	schema.HebrewLanguageCode:              {},
	schema.GermanLanguageCode:              {},
	schema.JapaneseLanguageCode:            {},
}

// NormalizeCacheLanguage maps a requested language to one item_language_cache actually stores a
// row for, collapsing anything outside cachedLanguages (including "xx" and unselectable "pt") to
// English so every SelectExpr/join call generates the exact same query shape.
func NormalizeCacheLanguage(lang string) string {
	if _, ok := cachedLanguages[lang]; ok {
		return lang
	}

	return schema.EnglishLanguageCode
}

// resolveByPriority picks the best available name out of names (keyed by the language of the
// translation) for a viewer requesting lang, walking languagePriority[lang] in order. Returns ""
// if none of the candidate languages have a name - the pure-Go equivalent of the
// array_position-ordered SQL fallback, used to populate item_language_cache.
func resolveByPriority(names map[string]string, lang string) string {
	order, ok := languagePriority[lang]
	if !ok {
		order = languagePriority[schema.DefaultLanguageCode]
	}

	for _, candidate := range order {
		if name, ok := names[candidate]; ok {
			return name
		}
	}

	return ""
}

func langPriorityOrderExpr( //nolint: ireturn
	col exp.IdentifierExpression, lang string,
) (exp.OrderedExpression, error) {
	langPriority, ok := languagePriority[lang]
	if !ok {
		langPriority, ok = languagePriority[schema.DefaultLanguageCode]
	}

	if !ok {
		return nil, fmt.Errorf("%w: `%s`", errLangNotFound, schema.DefaultLanguageCode)
	}

	iLangPriority := make([]interface{}, len(langPriority))

	for i, v := range langPriority {
		iLangPriority[i] = v
	}

	return goqu.Func(
		"array_position",
		goqu.L("ARRAY["+util.RepeatWithDelim("?", ",", len(iLangPriority))+"]::varchar[]", iLangPriority...),
		col,
	).Asc(), nil
}
