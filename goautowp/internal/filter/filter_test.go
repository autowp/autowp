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
		{"Citroën C4", "citroen_c4"},
		{"München Straße", "munchen_strasse"},
		{"Львів Київ", "l_viv_kiyiv"},
		{"红旗 H9", "hong_qi_h9"},
		{"トヨタ クラウン", "toyota_kuraun"},
		{"café", "cafe"},
		{"cafe\u0301", "cafe"},
		{"a\U00010000b", "ab"},
		{"a🚗b", "ab"},
		{"№ 5", "no._5"},
		{"½", "1_2"},
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
