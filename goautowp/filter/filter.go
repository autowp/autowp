package filter

import (
	"regexp"
	"strings"

	"github.com/fiam/gounidecode/unidecode"
)

var specialCharacters = map[rune]string{
	'№':  "N",
	' ':  "_",
	'"':  "_",
	'/':  "_",
	'*':  "_",
	'`':  "_",
	'#':  "_",
	'&':  "_",
	'\'': "_",
	'!':  "_",
	'@':  "_",
	'$':  "s",
	'%':  "_",
	'^':  "_",
	'=':  "-",
	'|':  "_",
	'?':  "_",
	'„':  ",",
	'“':  "_",
	'”':  "_",
	'{':  "_",
	'}':  "_",
	':':  "-",
	';':  "_",
	'-':  "-",
	'(':  "_",
	')':  "_",
}

var (
	disallowedFilenameCharsRegexp = regexp.MustCompile("[^A-Za-z0-9.(){}_-]")
	repeatedUnderscoreRegexp      = regexp.MustCompile("[_]{2,}")
)

func replaceSpecialCharacters(s string) string {
	var sb strings.Builder

	for _, c := range s {
		d, ok := specialCharacters[c]
		if ok {
			sb.WriteString(d)
		} else {
			sb.WriteRune(c)
		}
	}

	return sb.String()
}

func SanitizeFilename(filename string) string {
	filename = unidecode.Unidecode(filename)

	filename = strings.ToLower(filename)

	filename = replaceSpecialCharacters(filename)

	filename = disallowedFilenameCharsRegexp.ReplaceAllString(filename, "_")

	filename = strings.Trim(filename, "_-")

	filename = repeatedUnderscoreRegexp.ReplaceAllString(filename, "_")

	switch filename {
	case ".", "..", "":
		filename = "_"
	}

	return filename
}
