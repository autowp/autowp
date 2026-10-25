package i18nbundle

import (
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/nicksnyder/go-i18n/v2/i18n"
)

// FormatList joins plain text values using localized list punctuation. English follows
// the site's en-GB style, without a serial comma. Escape the result when rendering HTML.
func (s *I18n) FormatList(values []string, lang string) (string, error) {
	if len(values) < 2 {
		return strings.Join(values, ""), nil
	}

	localizer := s.Localizer(lang)

	separator, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: "list/separator"})
	if err != nil {
		return "", err
	}

	last := values[len(values)-1]
	base, _ := s.Tag(lang).Base()
	messageID := "list/last-separator"

	// These contextual conjunction rules match Unicode ICU's list formatter:
	// https://github.com/unicode-org/icu/blob/main/icu4j/main/core/src/main/java/com/ibm/icu/text/ListFormatter.java
	switch base.String() {
	case "es":
		word := strings.ToLower(last)
		startsWithI := strings.HasPrefix(word, "i")
		startsWithHi := strings.HasPrefix(word, "hi")

		startsWithDiphthong := strings.HasPrefix(word, "hia") || strings.HasPrefix(word, "hie")
		if startsWithI || (startsWithHi && !startsWithDiphthong) {
			messageID = "list/last-separator-before-i"
		}
	case "he":
		first, _ := utf8.DecodeRuneInString(last)
		if last != "" && !unicode.Is(unicode.Hebrew, first) {
			messageID = "list/last-separator-before-non-hebrew"
		}
	}

	lastSeparator, err := localizer.Localize(&i18n.LocalizeConfig{MessageID: messageID})
	if err != nil {
		return "", err
	}

	return strings.Join(values[:len(values)-1], separator) + lastSeparator + last, nil
}
