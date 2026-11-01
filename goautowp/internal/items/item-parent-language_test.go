package items_test

import (
	"testing"

	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/stretchr/testify/require"
)

func TestExtractNameBrandAliases(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		brand   string
		alias   string
		vehicle string
		want    string
	}{
		{name: "cyrillic case folding", brand: "ВАЗ", vehicle: "ваз 2101", want: "2101"},
		{name: "accented case folding", brand: "Škoda", vehicle: "ŠKODA Octavia", want: "Octavia"},
		{name: "chinese word boundary", brand: "红旗", vehicle: "红旗 H9", want: "H9"},
		{name: "cyrillic inside a word", brand: "ВАЗ", vehicle: "АвтоВАЗ 2101", want: "АвтоВАЗ 2101"},
		{name: "latin inside a word", brand: "Ford", vehicle: "Bedford CF", want: "Bedford CF"},
		{name: "literal punctuation", brand: "AC (Cars)", vehicle: "Roadster by AC (Cars)", want: "Roadster"},
		{name: "company suffix", brand: "Škoda", vehicle: "Roadster by The Škoda Company", want: "Roadster"},
		{name: "hyphenated prefix", brand: "Škoda", vehicle: "Škoda-Octavia", want: "Octavia"},
		{
			name:    "longest alias first",
			brand:   "Mercedes",
			alias:   "Mercedes-Benz",
			vehicle: "Mercedes-Benz C 200",
			want:    "C 200",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			db, database, _ := testutil.NewPostgres(t, "../..")
			_, err := database.ExecContext(t.Context(), `
				CREATE TEMP TABLE item_language (item_id bigint, language text, name text);
				CREATE TEMP TABLE brand_alias (item_id bigint, name text);
			`)
			require.NoError(t, err)

			_, err = database.ExecContext(t.Context(), `
				INSERT INTO item_language (item_id, language, name) VALUES (1, 'en', $1), (2, 'en', $2)
			`, tt.brand, tt.vehicle)
			require.NoError(t, err)

			if tt.alias != "" {
				_, err = database.ExecContext(t.Context(), `
					INSERT INTO brand_alias (item_id, name) VALUES (1, $1)
				`, tt.alias)
				require.NoError(t, err)
			}

			repository := items.NewItemParentLanguageRepository(db, []string{"en"})
			name, err := repository.ExtractName(t.Context(), schema.ItemRow{ID: 1}, schema.ItemRow{ID: 2}, "en")
			require.NoError(t, err)
			require.Equal(t, tt.want, name)
		})
	}
}
