package i18nbundle

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestFormatListLanguages(t *testing.T) {
	t.Parallel()

	bundle, err := New()
	require.NoError(t, err)

	tests := []struct {
		lang  string
		two   string
		three string
	}{
		{lang: "en", two: "Vitara and Samurai", three: "Vitara, Samurai and Baleno"},
		{lang: "en-GB", two: "Vitara and Samurai", three: "Vitara, Samurai and Baleno"},
		{lang: "ru", two: "Vitara и Samurai", three: "Vitara, Samurai и Baleno"},
		{lang: "uk", two: "Vitara і Samurai", three: "Vitara, Samurai і Baleno"},
		{lang: "be", two: "Vitara і Samurai", three: "Vitara, Samurai і Baleno"},
		{lang: "fr", two: "Vitara et Samurai", three: "Vitara, Samurai et Baleno"},
		{lang: "it", two: "Vitara e Samurai", three: "Vitara, Samurai e Baleno"},
		{lang: "de", two: "Vitara und Samurai", three: "Vitara, Samurai und Baleno"},
		{lang: "de-DE", two: "Vitara und Samurai", three: "Vitara, Samurai und Baleno"},
		{lang: "pt", two: "Vitara e Samurai", three: "Vitara, Samurai e Baleno"},
		{lang: "pt-br", two: "Vitara e Samurai", three: "Vitara, Samurai e Baleno"},
		{lang: "pt-BR", two: "Vitara e Samurai", three: "Vitara, Samurai e Baleno"},
		{lang: "es", two: "Vitara y Samurai", three: "Vitara, Samurai y Baleno"},
		{lang: "es-ES", two: "Vitara y Samurai", three: "Vitara, Samurai y Baleno"},
		{lang: "he", two: "Vitara ו-Samurai", three: "Vitara, Samurai ו-Baleno"},
		{lang: "he-IL", two: "Vitara ו-Samurai", three: "Vitara, Samurai ו-Baleno"},
		{lang: "zh", two: "Vitara和Samurai", three: "Vitara、Samurai和Baleno"},
		{lang: "zh-CN", two: "Vitara和Samurai", three: "Vitara、Samurai和Baleno"},
		{lang: "ja", two: "Vitara、Samurai", three: "Vitara、Samurai、Baleno"},
		{lang: "ja-JP", two: "Vitara、Samurai", three: "Vitara、Samurai、Baleno"},
		{lang: "xx", two: "Vitara and Samurai", three: "Vitara, Samurai and Baleno"},
	}
	for _, test := range tests {
		t.Run(test.lang, func(t *testing.T) {
			t.Parallel()

			values := []string{"Vitara", "Samurai", "Baleno"}
			two, err := bundle.FormatList(values[:2], test.lang)
			require.NoError(t, err)
			require.Equal(t, test.two, two)
			three, err := bundle.FormatList(values, test.lang)
			require.NoError(t, err)
			require.Equal(t, test.three, three)
			require.Equal(t, []string{"Vitara", "Samurai", "Baleno"}, values)
		})
	}
}

func TestFormatListContext(t *testing.T) {
	t.Parallel()

	bundle, err := New()
	require.NoError(t, err)

	tests := []struct {
		lang string
		last string
		want string
	}{
		{lang: "es", last: "Ignis", want: "Vitara e Ignis"},
		{lang: "es-ES", last: "Isuzu", want: "Vitara e Isuzu"},
		{lang: "es", last: "Hino", want: "Vitara e Hino"},
		{lang: "es", last: "HISPANO-SUIZA", want: "Vitara e HISPANO-SUIZA"},
		{lang: "es", last: "Hiace", want: "Vitara y Hiace"},
		{lang: "es", last: "Hielo", want: "Vitara y Hielo"},
		{lang: "he", last: "בלנו", want: "Vitara ובלנו"},
		{lang: "he", last: "Baleno", want: "Vitara ו-Baleno"},
		{lang: "he", last: "🚗", want: "Vitara ו-🚗"},
	}
	for _, test := range tests {
		t.Run(test.want, func(t *testing.T) {
			t.Parallel()

			result, err := bundle.FormatList([]string{"Vitara", test.last}, test.lang)
			require.NoError(t, err)
			require.Equal(t, test.want, result)
		})
	}
}

func TestFormatListBoundaries(t *testing.T) {
	t.Parallel()

	bundle, err := New()
	require.NoError(t, err)

	empty, err := bundle.FormatList([]string{}, "ru")
	require.NoError(t, err)
	require.Empty(t, empty)

	single, err := bundle.FormatList([]string{"Лада 🚗"}, "ru")
	require.NoError(t, err)
	require.Equal(t, "Лада 🚗", single)

	four, err := bundle.FormatList([]string{"A", "B", "C", "D"}, "en")
	require.NoError(t, err)
	require.Equal(t, "A, B, C and D", four)
}
