package schema_test

import (
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func TestExcluded(t *testing.T) {
	t.Parallel()

	expr := schema.Excluded("name")

	require.Equal(t, "EXCLUDED.name", expr.Literal())
}
