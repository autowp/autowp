package pictures

import (
	"html"
	"slices"
	"strings"
	"testing"

	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	nethtml "golang.org/x/net/html"
)

func newPictureNameTestFormatter(t *testing.T) *PictureNameFormatter {
	t.Helper()

	bundle, err := i18nbundle.New()
	require.NoError(t, err)

	return NewPictureNameFormatter(items.NewItemNameFormatter(bundle), bundle)
}

func samuraiPictureName() PictureNameFormatterOptions {
	item := items.ItemNameFormatterOptions{
		Name: "Suzuki Samurai Hardtop", Body: "SJ", Spec: "North America", SpecFull: "North America",
		BeginModelYear: 1987, EndModelYear: 1987, BeginYear: 1986, EndYear: 1987,
	}
	other := item
	other.Name = "Suzuki Samurai Convertible"

	return PictureNameFormatterOptions{Items: []PictureNameFormatterItem{
		{Item: item}, {Item: other},
	}}
}

func pictureNameHTMLText(t *testing.T, value string) string {
	t.Helper()

	node, err := nethtml.Parse(strings.NewReader(value))
	require.NoError(t, err)

	var (
		text  strings.Builder
		visit func(*nethtml.Node)
	)

	visit = func(node *nethtml.Node) {
		badge := false

		if node.Type == nethtml.ElementNode {
			require.Contains(t, []string{"html", "head", "body", "span", "small"}, node.Data)

			for _, attr := range node.Attr {
				require.Contains(
					t,
					[]string{"class", "title", "data-toggle", "data-placement"},
					attr.Key,
				)

				if node.Data == "span" && attr.Key == "class" &&
					slices.Contains(strings.Fields(attr.Val), "badge") {
					badge = true
				}
			}
		}
		// Market badges replace the square brackets of the text representation.
		if badge {
			text.WriteString("[")
		}

		if node.Type == nethtml.TextNode {
			text.WriteString(node.Data)
		}

		for child := node.FirstChild; child != nil; child = child.NextSibling {
			visit(child)
		}

		if badge {
			text.WriteString("]")
		}
	}
	visit(node)

	return text.String()
}

func TestPictureNameSharedDetails(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)

	const names = "Suzuki Samurai Hardtop and Convertible"

	tests := []struct {
		name string
		edit func(*items.ItemNameFormatterOptions)
		want string
	}{
		{
			name: "all shared",
			edit: func(_ *items.ItemNameFormatterOptions) {},
			want: "1987 " + names + " [North America] (SJ) '1986–87",
		},
		{
			name: "different model years",
			edit: func(item *items.ItemNameFormatterOptions) { item.EndModelYear = 1988 },
			want: names + " [North America] (SJ) '1986–87",
		},
		{
			name: "different model year fraction",
			edit: func(item *items.ItemNameFormatterOptions) { item.BeginModelYearFraction = "½" },
			want: names + " [North America] (SJ) '1986–87",
		},
		{
			name: "different production years",
			edit: func(item *items.ItemNameFormatterOptions) { item.EndYear = 1988 },
			want: "1987 " + names + " [North America] (SJ)",
		},
		{
			name: "different production months",
			edit: func(item *items.ItemNameFormatterOptions) { item.BeginMonth = 3 },
			want: "1987 " + names + " [North America] (SJ)",
		},
		{
			name: "different body",
			edit: func(item *items.ItemNameFormatterOptions) { item.Body = "JA" },
			want: "1987 " + names + " [North America] '1986–87",
		},
		{
			name: "missing body",
			edit: func(item *items.ItemNameFormatterOptions) { item.Body = "" },
			want: "1987 " + names + " [North America] '1986–87",
		},
		{
			name: "different market",
			edit: func(item *items.ItemNameFormatterOptions) { item.Spec = "Japan" },
			want: "1987 " + names + " (SJ) '1986–87",
		},
		{
			name: "missing model years",
			edit: func(item *items.ItemNameFormatterOptions) {
				item.BeginModelYear = 0
				item.EndModelYear = 0
			},
			want: names + " [North America] (SJ) '1986–87",
		},
		{
			name: "different start year",
			edit: func(item *items.ItemNameFormatterOptions) { item.BeginYear = 1985 },
			want: "1987 " + names + " [North America] (SJ)",
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			picture := samuraiPictureName()
			test.edit(&picture.Items[1].Item)
			text, err := formatter.FormatText(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, text)

			rendered, err := formatter.FormatHTML(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, pictureNameHTMLText(t, rendered))
		})
	}
}

func TestPictureNameSamuraiHTML(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	picture := samuraiPictureName()

	languages := []struct {
		code            string
		names           string
		modelYears      string
		productionYears string
	}{
		{
			code:            schema.EnglishLanguageCode,
			names:           "Suzuki Samurai Hardtop and Convertible",
			modelYears:      "model years",
			productionYears: "years of production",
		},
		{
			code:            schema.RussianLanguageCode,
			names:           "Suzuki Samurai Hardtop и Convertible",
			modelYears:      "модельный год",
			productionYears: "года выпуска",
		},
		{
			code:            schema.GermanLanguageCode,
			names:           "Suzuki Samurai Hardtop und Convertible",
			modelYears:      "Modelljahre",
			productionYears: "Produktionsjahre",
		},
		{
			code:            schema.JapaneseLanguageCode,
			names:           "Suzuki Samurai Hardtop、Convertible",
			modelYears:      "モデルイヤー",
			productionYears: "生産年",
		},
	}
	for _, language := range languages {
		rendered, err := formatter.FormatHTML(picture, language.code)
		require.NoError(t, err)
		require.Equal(
			t,
			`<span title="`+language.modelYears+`">1987</span> `+language.names+` `+
				`<span class="badge bg-info text-dark" title="North America" data-toggle="tooltip" `+
				`data-placement="top">North America</span> (SJ)<small> '<span class="realyears" title="`+
				language.productionYears+`">1986–87</span></small>`,
			rendered,
		)
	}
}

func TestPictureNameCommonWords(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)

	tests := []struct {
		names []string
		want  string
	}{
		{
			names: []string{"Ford Mustang Coupe", "Ford Mustang Convertible"},
			want:  "Ford Mustang Coupe and Convertible",
		},
		{names: []string{"BMW 320i", "BMW 320d"}, want: "BMW 320i and 320d"},
		{names: []string{"Suzuki Baleno", "Suzuki Samurai"}, want: "Suzuki Baleno and Samurai"},
		{names: []string{"Ford", "Ford Mustang"}, want: "Ford and Ford Mustang"},
		{names: []string{"Ford Mustang", "Ford"}, want: "Ford Mustang and Ford"},
		{names: []string{"Ford Mustang", "Ford Mustang"}, want: "Ford Mustang and Ford Mustang"},
		{
			names: []string{"BMW 320i", "BMW 320i Touring", "BMW 320d"},
			want:  "BMW 320i, BMW 320i Touring and 320d",
		},
		{
			names: []string{
				"Suzuki Samurai Hardtop",
				"Suzuki Samurai Convertible",
				"Toyota RAV4",
				"Toyota Corolla",
			},
			want: "Suzuki Samurai Hardtop, Convertible, Toyota RAV4 and Corolla",
		},
		{names: []string{"GAZ-24", "GAZ-24-10"}, want: "GAZ-24 and GAZ-24-10"},
		{
			names: []string{"ГАЗ Волга Седан", "ГАЗ Волга Универсал"},
			want:  "ГАЗ Волга Седан and Универсал",
		},
		{names: []string{"A & B Coupe", "A & B Roadster"}, want: "A & B Coupe and Roadster"},
		{
			names: []string{"A &amp; B Coupe", "A &amp; B Roadster"},
			want:  "A &amp; B Coupe and Roadster",
		},
		{names: []string{"🚗 Авто Седан", "🚗 Авто Купе"}, want: "🚗 Авто Седан and Купе"},
		{
			names: []string{"Škoda Octavia Седан", "Škoda Octavia Универсал"},
			want:  "Škoda Octavia Седан and Универсал",
		},
		{names: []string{"丰田 皇冠 轿车", "丰田 皇冠 旅行车"}, want: "丰田 皇冠 轿车 and 旅行车"},
		{
			names: []string{"Лада\u00a0Веста Седан", "Лада\u00a0Веста Универсал"},
			want:  "Лада\u00a0Веста Седан and Универсал",
		},
	}
	for _, test := range tests {
		t.Run(test.want, func(t *testing.T) {
			t.Parallel()

			picture := PictureNameFormatterOptions{Items: []PictureNameFormatterItem{}}
			for _, name := range test.names {
				picture.Items = append(picture.Items, PictureNameFormatterItem{
					Item: items.ItemNameFormatterOptions{Name: name},
				})
			}

			text, err := formatter.FormatText(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, text)

			rendered, err := formatter.FormatHTML(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, html.EscapeString(test.want), rendered)
		})
	}
}

func TestPictureNamePersonsFirst(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	picture := PictureNameFormatterOptions{Items: []PictureNameFormatterItem{
		{
			Item:       items.ItemNameFormatterOptions{Name: "Ford Mustang Coupe"},
			ItemTypeID: schema.ItemTableItemTypeIDVehicle,
		},
		{
			Item:       items.ItemNameFormatterOptions{Name: "Henry Ford"},
			ItemTypeID: schema.ItemTableItemTypeIDPerson,
		},
		{
			Item:       items.ItemNameFormatterOptions{Name: "Ford Mustang Convertible"},
			ItemTypeID: schema.ItemTableItemTypeIDVehicle,
		},
		{
			Item:       items.ItemNameFormatterOptions{Name: "Lee Iacocca"},
			ItemTypeID: schema.ItemTableItemTypeIDPerson,
		},
	}}
	originalItems := slices.Clone(picture.Items)

	const want = "Henry Ford, Lee Iacocca, Ford Mustang Coupe and Convertible"

	for _, lang := range []string{schema.EnglishLanguageCode, schema.RussianLanguageCode} {
		expected := want
		if lang == schema.RussianLanguageCode {
			expected = "Henry Ford, Lee Iacocca, Ford Mustang Coupe и Convertible"
		}

		text, err := formatter.FormatText(picture, lang)
		require.NoError(t, err)
		require.Equal(t, expected, text)

		rendered, err := formatter.FormatHTML(picture, lang)
		require.NoError(t, err)
		require.Equal(t, expected, rendered)
		require.Equal(t, originalItems, picture.Items)
	}
}

func TestPictureNamePersonsKeepFullNames(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	tests := []struct {
		name    string
		persons []string
		want    string
	}{
		{
			name:    "common first name",
			persons: []string{"Henry Ford", "Henry Leland"},
			want:    "Henry Ford, Henry Leland, Ford Mustang Coupe and Convertible",
		},
		{
			name:    "common last name",
			persons: []string{"Henry Ford", "Edsel Ford"},
			want:    "Henry Ford, Edsel Ford, Ford Mustang Coupe and Convertible",
		},
		{
			name:    "common prefix with vehicle",
			persons: []string{"Ford Person"},
			want:    "Ford Person, Ford Mustang Coupe and Convertible",
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			picture := PictureNameFormatterOptions{Items: []PictureNameFormatterItem{}}
			for _, name := range test.persons {
				picture.Items = append(picture.Items, PictureNameFormatterItem{
					Item:       items.ItemNameFormatterOptions{Name: name},
					ItemTypeID: schema.ItemTableItemTypeIDPerson,
				})
			}

			for _, name := range []string{"Ford Mustang Coupe", "Ford Mustang Convertible"} {
				picture.Items = append(picture.Items, PictureNameFormatterItem{
					Item:       items.ItemNameFormatterOptions{Name: name},
					ItemTypeID: schema.ItemTableItemTypeIDVehicle,
				})
			}

			text, err := formatter.FormatText(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, text)

			rendered, err := formatter.FormatHTML(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, rendered)
		})
	}
}

func TestPictureNameDetailsMustMatchEveryItem(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	picture := samuraiPictureName()
	third := picture.Items[0]
	third.Item.Name = "Suzuki Samurai Pickup"
	picture.Items = append(picture.Items, third)
	picture.Items[1].Item.Body = "JA"
	picture.Items[1].Item.Spec = "Japan"
	picture.Items[1].Item.BeginModelYear = 1986
	picture.Items[1].Item.EndYear = 1988
	originalItems := append([]PictureNameFormatterItem{}, picture.Items...)

	text, err := formatter.FormatText(picture, schema.EnglishLanguageCode)
	require.NoError(t, err)
	require.Equal(t, "Suzuki Samurai Hardtop, Convertible and Pickup", text)

	rendered, err := formatter.FormatHTML(picture, schema.EnglishLanguageCode)
	require.NoError(t, err)
	require.Equal(t, text, rendered)
	require.Equal(t, originalItems, picture.Items)
}

func TestPictureNameYearDetails(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	ongoing := true
	ended := false

	const names = "Suzuki Samurai Hardtop and Convertible [North America] (SJ)"

	tests := []struct {
		name string
		edit func(*PictureNameFormatterOptions)
		want string
	}{
		{
			name: "shared fractions and months",
			edit: func(picture *PictureNameFormatterOptions) {
				for i := range picture.Items {
					picture.Items[i].Item.BeginModelYearFraction = "½"
					picture.Items[i].Item.EndModelYearFraction = "½"
					picture.Items[i].Item.BeginMonth = 3
					picture.Items[i].Item.EndMonth = 7
				}
			},
			want: "1987½ " + names + " '03.1986–07.1987",
		},
		{
			name: "different ongoing production status",
			edit: func(picture *PictureNameFormatterOptions) {
				for i := range picture.Items {
					picture.Items[i].Item.EndYear = 0
				}

				picture.Items[0].Item.Today = &ongoing
				picture.Items[1].Item.Today = &ended
			},
			want: "1987 " + names,
		},
		{
			name: "different ongoing model year status",
			edit: func(picture *PictureNameFormatterOptions) {
				for i := range picture.Items {
					picture.Items[i].Item.EndModelYear = 0
				}

				picture.Items[0].Item.Today = &ongoing
				picture.Items[1].Item.Today = &ended
			},
			want: names + " '1986–87",
		},
		{
			name: "nil and false ongoing status render identically",
			edit: func(picture *PictureNameFormatterOptions) {
				for i := range picture.Items {
					picture.Items[i].Item.EndYear = 0
				}

				picture.Items[1].Item.Today = &ended
			},
			want: "1987 " + names + " '1986–????",
		},
	}
	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			picture := samuraiPictureName()
			test.edit(&picture)
			text, err := formatter.FormatText(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, test.want, text)

			rendered, err := formatter.FormatHTML(picture, schema.EnglishLanguageCode)
			require.NoError(t, err)
			require.Equal(t, text, pictureNameHTMLText(t, rendered))
		})
	}
}

func TestPictureNameEscapingAndFallbacks(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)
	lang := schema.EnglishLanguageCode

	picture := samuraiPictureName()
	for i := range picture.Items {
		picture.Items[i].Item.BeginModelYearFraction = `½"><img src=x onerror="alert(3)">`
		picture.Items[i].Item.EndModelYearFraction = `½"><img src=x onerror="alert(3)">`
		picture.Items[i].Item.Body = `<script>alert("body")</script>`
		picture.Items[i].Item.Spec = `<img src=x onerror="alert(1)">`
		picture.Items[i].Item.SpecFull = `" onmouseover="alert(2)`
	}

	picture.Items[0].Item.Name = `<b>Škoda & ГАЗ</b> Седан`
	picture.Items[1].Item.Name = `<b>Škoda & ГАЗ</b> Универсал`

	text, err := formatter.FormatText(picture, lang)
	require.NoError(t, err)
	rendered, err := formatter.FormatHTML(picture, lang)
	require.NoError(t, err)
	require.Equal(t, text, pictureNameHTMLText(t, rendered))
	require.NotContains(t, rendered, "<script>")
	require.NotContains(t, rendered, "<img ")
	require.NotContains(t, rendered, "<b>")
	require.Contains(t, rendered, `title="&#34; onmouseover=&#34;alert(2)"`)

	picture.Name = `Custom <b>title</b> & фото`
	text, err = formatter.FormatText(picture, lang)
	require.NoError(t, err)
	require.Equal(t, picture.Name, text)
	rendered, err = formatter.FormatHTML(picture, lang)
	require.NoError(t, err)
	require.Equal(t, html.EscapeString(picture.Name), rendered)

	picture = PictureNameFormatterOptions{}
	text, err = formatter.FormatText(picture, lang)
	require.NoError(t, err)
	require.Equal(t, "Picture", text)

	rendered, err = formatter.FormatHTML(picture, lang)
	require.NoError(t, err)
	require.Equal(t, "Picture", rendered)

	picture.Items = []PictureNameFormatterItem{{
		Item: items.ItemNameFormatterOptions{
			Name: "Škoda & ГАЗ",
		}, Perspective: "perspective/interior",
	}}
	text, err = formatter.FormatText(picture, lang)
	require.NoError(t, err)
	require.Equal(t, "Interior Škoda & ГАЗ", text)

	rendered, err = formatter.FormatHTML(picture, lang)
	require.NoError(t, err)
	require.Equal(t, "Interior Škoda &amp; ГАЗ", rendered)
}

func TestPictureNameLocalizedList(t *testing.T) {
	t.Parallel()

	formatter := newPictureNameTestFormatter(t)

	tests := []struct {
		lang string
		want string
	}{
		{lang: "en", want: "Suzuki Vitara, Samurai and Baleno"},
		{lang: "ru", want: "Suzuki Vitara, Samurai и Baleno"},
		{lang: "uk", want: "Suzuki Vitara, Samurai і Baleno"},
		{lang: "be", want: "Suzuki Vitara, Samurai і Baleno"},
		{lang: "fr", want: "Suzuki Vitara, Samurai et Baleno"},
		{lang: "it", want: "Suzuki Vitara, Samurai e Baleno"},
		{lang: "de", want: "Suzuki Vitara, Samurai und Baleno"},
		{lang: "pt", want: "Suzuki Vitara, Samurai e Baleno"},
		{lang: "es", want: "Suzuki Vitara, Samurai y Baleno"},
		{lang: "he", want: "Suzuki Vitara, Samurai ו-Baleno"},
		{lang: "zh", want: "Suzuki Vitara、Samurai和Baleno"},
		{lang: "ja", want: "Suzuki Vitara、Samurai、Baleno"},
	}
	for _, test := range tests {
		t.Run(test.lang, func(t *testing.T) {
			t.Parallel()

			picture := PictureNameFormatterOptions{Items: []PictureNameFormatterItem{
				{Item: items.ItemNameFormatterOptions{Name: "Suzuki Vitara"}},
				{Item: items.ItemNameFormatterOptions{Name: "Suzuki Samurai"}},
				{Item: items.ItemNameFormatterOptions{Name: "Suzuki Baleno"}},
			}}
			text, err := formatter.FormatText(picture, test.lang)
			require.NoError(t, err)
			require.Equal(t, test.want, text)
			rendered, err := formatter.FormatHTML(picture, test.lang)
			require.NoError(t, err)
			require.Equal(t, html.EscapeString(test.want), rendered)
		})
	}
}
