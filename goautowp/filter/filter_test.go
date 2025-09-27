package filter

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestSanitizeFilename(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		Input  string
		Output string
	}{
		{
			"абвгдеёжзиклмнопрстуфх ц ч ш щ ъыь эюя",
			"abvgdeiozhziklmnoprstufkh_ts_ch_sh_shch_y_eiuia",
		},
		{"Škoda", "skoda"},
		{"数据库", "shu_ju_ku"},
		{"just.test", "just.test"},
		{".", "_"},
		{"..", "_"},
		{"...", "..."},
		{"", "_"},
		{"just test", "just_test"},
		{"просто тест ", "prosto_test"},
	}

	for _, testCase := range testCases {
		result := SanitizeFilename(testCase.Input)
		require.Equal(t, testCase.Output, result)
	}
}
