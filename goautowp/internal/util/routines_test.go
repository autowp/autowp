package util

import (
	"database/sql"
	"errors"
	"testing"
	"time"

	"cloud.google.com/go/civil"
	"github.com/lib/pq"
	"github.com/stretchr/testify/require"
	"golang.org/x/text/language"
	"google.golang.org/genproto/googleapis/type/date"
)

type closerFunc func() error

func (f closerFunc) Close() error {
	return f()
}

func TestGrpcDateToDate(t *testing.T) {
	t.Parallel()

	require.Nil(t, GrpcDateToDate(nil))

	result := GrpcDateToDate(&date.Date{Year: 2024, Month: 3, Day: 15})
	require.Equal(t, &civil.Date{Year: 2024, Month: time.March, Day: 15}, result)
}

func TestDateToGrpcDate(t *testing.T) {
	t.Parallel()

	require.Nil(t, DateToGrpcDate(civil.Date{}))

	result := DateToGrpcDate(civil.Date{Year: 2024, Month: time.March, Day: 15})
	require.Equal(t, &date.Date{Year: 2024, Month: 3, Day: 15}, result)
}

func TestClose(t *testing.T) {
	t.Parallel()

	called := false

	Close(closerFunc(func() error {
		called = true

		return nil
	}))
	require.True(t, called)

	require.NotPanics(t, func() {
		Close(closerFunc(func() error {
			return errors.New("close error") //nolint:err113
		}))
	})
}

func TestContains(t *testing.T) {
	t.Parallel()

	require.True(t, Contains([]int{1, 2, 3}, 2))
	require.False(t, Contains([]int{1, 2, 3}, 4))
	require.False(t, Contains([]int{}, 1))
}

func TestSQLNullInt64ToPtr(t *testing.T) {
	t.Parallel()

	require.Nil(t, SQLNullInt64ToPtr(sql.NullInt64{Valid: false}))

	result := SQLNullInt64ToPtr(sql.NullInt64{Int64: 42, Valid: true})
	require.NotNil(t, result)
	require.EqualValues(t, 42, *result)
}

func TestSubstr(t *testing.T) {
	t.Parallel()

	require.Equal(t, "ell", substr("hello", 1, 3))
	require.Empty(t, substr("hello", 10, 3))
	require.Equal(t, "llo", substr("hello", 2, 10))
}

func TestGetTextPreview(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		name     string
		text     string
		options  TextPreviewOptions
		expected string
	}{
		{
			name:     "no limits",
			text:     "  hello world  \r\n",
			options:  TextPreviewOptions{},
			expected: "hello world",
		},
		{
			name:     "maxlines",
			text:     "line1\nline2\nline3",
			options:  TextPreviewOptions{Maxlines: 2},
			expected: "line1\nline2",
		},
		{
			name:     "maxlength truncates",
			text:     "hello world",
			options:  TextPreviewOptions{Maxlength: 5},
			expected: "hello...",
		},
		{
			name:     "maxlength longer than text",
			text:     "hi",
			options:  TextPreviewOptions{Maxlength: 5},
			expected: "hi",
		},
	}

	for _, testCase := range testCases {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			require.Equal(t, testCase.expected, GetTextPreview(testCase.text, testCase.options))
		})
	}
}

func TestBoolPtr(t *testing.T) {
	t.Parallel()

	result := BoolPtr(true)
	require.NotNil(t, result)
	require.True(t, *result)
}

func TestTimePtr(t *testing.T) {
	t.Parallel()

	now := time.Now()
	result := TimePtr(now)
	require.NotNil(t, result)
	require.Equal(t, now, *result)
}

func TestMin(t *testing.T) {
	t.Parallel()

	require.Equal(t, 1, Min(1, 2))
	require.Equal(t, 1, Min(2, 1))
}

func TestMax(t *testing.T) {
	t.Parallel()

	require.Equal(t, 2, Max(1, 2))
	require.Equal(t, 2, Max(2, 1))
}

func TestRemoveValueFromArray(t *testing.T) {
	t.Parallel()

	result := RemoveValueFromArray([]int{1, 2, 3, 2}, 2)
	require.Equal(t, []int{1, 3}, result)

	result = RemoveValueFromArray([]int{}, 2)
	require.Equal(t, []int{}, result)
}

func TestIntersectBounds(t *testing.T) {
	t.Parallel()

	rect1 := Rect[int]{Left: 5, Top: 5, Width: 20, Height: 20}
	rect2 := Rect[int]{Left: 0, Top: 0, Width: 10, Height: 10}

	result := IntersectBounds(rect1, rect2)
	require.Equal(t, 5, result.Left)
	require.Equal(t, 5, result.Top)
}

func TestNullInt64ToScalar(t *testing.T) {
	t.Parallel()

	require.EqualValues(t, 0, NullInt64ToScalar(sql.NullInt64{Valid: false}))
	require.EqualValues(t, 7, NullInt64ToScalar(sql.NullInt64{Int64: 7, Valid: true}))
}

func TestNullInt32ToScalar(t *testing.T) {
	t.Parallel()

	require.EqualValues(t, 0, NullInt32ToScalar(sql.NullInt32{Valid: false}))
	require.EqualValues(t, 7, NullInt32ToScalar(sql.NullInt32{Int32: 7, Valid: true}))
}

func TestNullInt16ToScalar(t *testing.T) {
	t.Parallel()

	require.EqualValues(t, 0, NullInt16ToScalar(sql.NullInt16{Valid: false}))
	require.EqualValues(t, 7, NullInt16ToScalar(sql.NullInt16{Int16: 7, Valid: true}))
}

func TestNullByteToScalar(t *testing.T) {
	t.Parallel()

	require.EqualValues(t, 0, NullByteToScalar(sql.NullByte{Valid: false}))
	require.EqualValues(t, 7, NullByteToScalar(sql.NullByte{Byte: 7, Valid: true}))
}

func TestNullStringToString(t *testing.T) {
	t.Parallel()

	require.Empty(t, NullStringToString(sql.NullString{Valid: false}))
	require.Equal(t, "hello", NullStringToString(sql.NullString{String: "hello", Valid: true}))
}

func TestNullBoolToBoolPtr(t *testing.T) {
	t.Parallel()

	require.Nil(t, NullBoolToBoolPtr(sql.NullBool{Valid: false}))

	result := NullBoolToBoolPtr(sql.NullBool{Bool: true, Valid: true})
	require.NotNil(t, result)
	require.True(t, *result)
}

func TestIsPgDuplicateKeyError(t *testing.T) {
	t.Parallel()

	require.True(t, IsPgDuplicateKeyError(&pq.Error{Code: "23505"}))
	require.False(t, IsPgDuplicateKeyError(&pq.Error{Code: "40P01"}))
	require.False(t, IsPgDuplicateKeyError(errors.New("other error"))) //nolint:err113
}

func TestIsPgDeadlockError(t *testing.T) {
	t.Parallel()

	require.True(t, IsPgDeadlockError(&pq.Error{Code: "40P01"}))
	require.False(t, IsPgDeadlockError(&pq.Error{Code: "23505"}))
	require.False(t, IsPgDeadlockError(errors.New("other error"))) //nolint:err113
}

func TestKeyOfMapMaxValue(t *testing.T) {
	t.Parallel()

	result := KeyOfMapMaxValue(map[int]int{1: 3, 2: 10, 3: 5})
	require.Equal(t, 2, result)

	require.Equal(t, 0, KeyOfMapMaxValue(map[int]int{}))
}

func TestRemoveDuplicate(t *testing.T) {
	t.Parallel()

	result := RemoveDuplicate([]int{1, 2, 2, 3, 1})
	require.Equal(t, []int{1, 2, 3}, result)
}

func TestStringDefault(t *testing.T) {
	t.Parallel()

	require.Equal(t, "value", StringDefault("value", "default"))
	require.Equal(t, "default", StringDefault("", "default"))
}

func TestHTMLEscapeString(t *testing.T) {
	t.Parallel()

	result := HTMLEscapeString("<b>bold</b>")
	require.Equal(t, "&lt;b&gt;bold&lt;/b&gt;", string(result))
}

func TestTitleCase(t *testing.T) {
	t.Parallel()

	require.Empty(t, TitleCase("", language.English))
	require.Equal(t, "Hello World", TitleCase("hello world", language.English))
	require.Equal(t, "Привет", TitleCase("привет", language.Russian))
}

func TestRepeatWithDelim(t *testing.T) {
	t.Parallel()

	require.Equal(t, "a,a,a", RepeatWithDelim("a", ",", 3))
	require.Empty(t, RepeatWithDelim("a", ",", 0))
}
