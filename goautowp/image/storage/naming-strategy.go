package storage

import (
	"fmt"
	"regexp"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/filter"
)

const (
	NamingStrategyTypeSerial  string = "serial"
	NamingStrategyTypePattern string = "pattern"
)

type GenerateOptions struct {
	Pattern       string
	Extension     string
	Index         int
	Count         int
	PreferredName string
}

type NamingStrategy interface {
	Generate(options GenerateOptions) string
}

const ItemPerDir = 1000

type NamingStrategySerial struct {
	deep int
}

type NamingStrategyPattern struct{}

func normalizePattern(pattern string) string {
	a := regexp.MustCompile(`[/\\]+`)
	patternComponents := a.Split(pattern, -1)

	result := make([]string, 0)

	for _, component := range patternComponents {
		switch component {
		case ".", "..":

		default:
			result = append(result, filter.SanitizeFilename(component))
		}
	}

	return strings.Join(result, "/")
}

func (s NamingStrategyPattern) Generate(options GenerateOptions) string {
	pattern := normalizePattern(options.Pattern)
	components := make([]string, 0)

	if len(pattern) > 0 {
		components = append(components, pattern)
	}

	if options.Index > 0 || len(pattern) == 0 {
		components = append(components, strconv.Itoa(options.Index))
	}

	result := strings.Join(components, "")

	if len(options.Extension) > 0 {
		result = result + "." + options.Extension
	}

	return result
}

func (s NamingStrategySerial) Generate(options GenerateOptions) string {
	fileIndex := options.Count + 1

	dirPath := path(fileIndex, s.deep)

	fileBasename := strconv.Itoa(fileIndex)
	if len(options.PreferredName) > 0 {
		fileBasename = filter.SanitizeFilename(options.PreferredName)
	}

	suffix := ""
	if options.Index > 0 {
		suffix = "_" + strconv.Itoa(options.Index)
	}

	result := fileBasename + suffix
	if len(options.Extension) > 0 {
		result = result + "." + options.Extension
	}

	return dirPath + result
}

func path(index int, deep int) string {
	chars := len(strconv.Itoa(ItemPerDir - 1)) // use log10, fkn n00b
	path := ""

	if deep > 0 {
		cur := index / ItemPerDir
		for range deep {
			div := cur / ItemPerDir
			mod := cur - div*ItemPerDir
			path = fmt.Sprintf("%0"+strconv.Itoa(chars)+"d", mod) + "/" + path
			cur = div
		}
	}

	return path
}
