package mosts

import (
	"fmt"
	"time"
)

const hundred = 100

type YearsRange struct {
	Name    string
	Folder  string
	MinYear int
	MaxYear int
}

var cy = time.Now().Year()

var prevYear = cy - 1

var years = []YearsRange{
	{
		Name:    "mosts/period/before1920",
		Folder:  "before1920",
		MaxYear: 1919, //nolint: mnd
	},
	{
		Name:    "mosts/period/1920-29",
		Folder:  "1920-29",
		MinYear: 1920, //nolint: mnd
		MaxYear: 1929, //nolint: mnd
	},
	{
		Name:    "mosts/period/1930-39",
		Folder:  "1930-39",
		MinYear: 1930, //nolint: mnd
		MaxYear: 1939, //nolint: mnd
	},
	{
		Name:    "mosts/period/1940-49",
		Folder:  "1940-49",
		MinYear: 1940, //nolint: mnd
		MaxYear: 1949, //nolint: mnd
	},
	{
		Name:    "mosts/period/1950-59",
		Folder:  "1950-59",
		MinYear: 1950, //nolint: mnd
		MaxYear: 1959, //nolint: mnd
	},
	{
		Name:    "mosts/period/1960-69",
		Folder:  "1960-69",
		MinYear: 1960, //nolint: mnd
		MaxYear: 1969, //nolint: mnd
	},
	{
		Name:    "mosts/period/1970-79",
		Folder:  "1970-79",
		MinYear: 1970, //nolint: mnd
		MaxYear: 1979, //nolint: mnd
	},
	{
		Name:    "mosts/period/1980-89",
		Folder:  "1980-89",
		MinYear: 1980, //nolint: mnd
		MaxYear: 1989, //nolint: mnd
	},
	{
		Name:    "mosts/period/1990-99",
		Folder:  "1990-99",
		MinYear: 1990, //nolint: mnd
		MaxYear: 1999, //nolint: mnd
	},
	{
		Name:    "mosts/period/2000-09",
		Folder:  "2000-09",
		MinYear: 2000, //nolint: mnd
		MaxYear: 2009, //nolint: mnd
	},
	{
		Name:    "mosts/period/2010-19",
		Folder:  "2010-19",
		MinYear: 2010, //nolint: mnd
		MaxYear: 2019, //nolint: mnd
	},
	{
		Name:    fmt.Sprintf("mosts/period/2020-%02d", prevYear%hundred),
		Folder:  fmt.Sprintf("2020-%02d", prevYear%hundred),
		MinYear: 2020, //nolint: mnd
		MaxYear: prevYear,
	},
	{
		Name:    "mosts/period/present",
		Folder:  "today",
		MinYear: cy,
		// to prevent infinite order cache matches
		MaxYear: cy + 10, //nolint: mnd
	},
}
