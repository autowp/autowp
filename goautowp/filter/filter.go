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

	re := regexp.MustCompile("[^A-Za-z0-9.(){}_-]")
	filename = re.ReplaceAllString(filename, "_")

	filename = strings.Trim(filename, "_-")

	re2 := regexp.MustCompile("[_]{2,}")
	filename = re2.ReplaceAllString(filename, "_")

	switch filename {
	case ".", "..", "":
		filename = "_"
	}

	return filename
}
