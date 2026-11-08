package traffic

import (
	"os"
	"strings"
	"testing"

	"github.com/autowp/goautowp/internal/testutil"
	"github.com/stretchr/testify/require"
)

func TestSourceMigrationBackfillsVerifiedAddresses(t *testing.T) {
	t.Parallel()
	_, pool, _ := testutil.NewPostgres(t, "../..")
	ctx := t.Context()
	_, err := pool.ExecContext(ctx, `CREATE TEMP TABLE ip_whitelist (ip inet PRIMARY KEY, description text NOT NULL);
 INSERT INTO ip_whitelist VALUES
 ('40.77.167.1', 'bingbot verified reverse/forward DNS'),
 ('192.0.2.1', 'manual click'),
 ('192.0.2.2', 'google verified reverse/forward DNS');`)
	require.NoError(t, err)
	migration, err := os.ReadFile("../../migrations/57_traffic-source.up.sql")
	require.NoError(t, err)
	_, err = pool.ExecContext(ctx, strings.ReplaceAll(string(migration), "CREATE TABLE ", "CREATE TEMP TABLE "))
	require.NoError(t, err)

	var sourceID string
	require.NoError(
		t,
		pool.QueryRowContext(ctx, "SELECT source_id FROM ip_source WHERE ip = '40.77.167.1'").Scan(&sourceID),
	)
	require.Equal(t, "bingbot", sourceID)

	var classified int
	require.NoError(t, pool.QueryRowContext(ctx, "SELECT COUNT(*) FROM ip_source").Scan(&classified))
	require.Equal(t, 2, classified)

	down, err := os.ReadFile("../../migrations/57_traffic-source.down.sql")
	require.NoError(t, err)
	_, err = pool.ExecContext(ctx, string(down))
	require.NoError(t, err)

	var exemptions int
	require.NoError(t, pool.QueryRowContext(ctx, "SELECT COUNT(*) FROM ip_whitelist").Scan(&exemptions))
	require.Equal(t, 3, exemptions)
}
