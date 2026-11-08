package traffic

import (
	"net"
	"net/netip"
	"testing"
	"time"

	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestSourceCatalog(t *testing.T) {
	t.Parallel()
	svc, _ := temporaryNetworkTraffic(t)
	sources, err := svc.Monitoring.Sources(t.Context())
	require.NoError(t, err)
	require.Len(t, sources, 14)

	ids := make(map[string]bool)

	for _, source := range sources {
		require.NotEmpty(t, source.ID)
		require.NotEmpty(t, source.Name)
		require.False(t, ids[source.ID])

		ids[source.ID] = true
		for _, network := range source.Networks {
			prefix, err := netip.ParsePrefix(network)
			require.NoError(t, err)
			require.Equal(t, prefix.Masked(), prefix)
		}
	}
}

func TestSourceTraffic(t *testing.T) {
	t.Parallel()
	svc, db := temporaryNetworkTraffic(t)
	ctx := t.Context()

	var now time.Time

	_, err := db.Select(goqu.L("CURRENT_TIMESTAMP")).ScanValContext(ctx, &now)
	require.NoError(t, err)
	require.NoError(
		t,
		svc.Whitelist.addVerified(ctx, net.ParseIP("40.77.167.1"), "bingbot verified reverse/forward DNS"),
	)
	require.NoError(t, svc.Whitelist.Add(ctx, net.ParseIP("74.7.241.200"), "manual"))
	require.NoError(
		t,
		svc.Whitelist.addVerified(ctx, net.ParseIP("216.73.216.10"), "googlebot verified reverse/forward DNS"),
	)
	require.NoError(t, svc.Monitoring.AddCounts(ctx, []MonitoringCount{
		{IP: "216.73.216.1", Timestamp: now, Count: 30},
		{IP: "216.73.217.1", Timestamp: now, Count: 40},
		{IP: "216.73.216.10", Timestamp: now, Count: 5},
		{IP: "74.7.241.200", Timestamp: now, Count: 50},
		{IP: "74.7.241.200", Timestamp: now.Truncate(time.Hour), Count: 10},
		{IP: "40.77.167.1", Timestamp: now, Count: 20},
		{IP: "40.77.167.2", Timestamp: now, Count: 90},
		{IP: "2001:db8::1", Timestamp: now, Count: 100},
		{IP: "216.73.216.2", Timestamp: now.Add(-24 * time.Hour), Count: 1000},
	}))
	rows, err := svc.Monitoring.ListSources(ctx)
	require.NoError(t, err)

	byID := make(map[string]SourceTraffic)
	for _, row := range rows {
		byID[row.ID] = row
	}

	require.EqualValues(t, 70, byID["anthropic"].Count)
	require.EqualValues(t, 2, byID["anthropic"].Addresses)
	// One OpenAI address is in both published bot lists; count it once.
	require.EqualValues(t, 60, byID["openai"].Count)
	require.EqualValues(t, 1, byID["openai"].Addresses)
	require.EqualValues(t, 1, byID["openai"].WhitelistedAddresses)
	require.EqualValues(t, 20, byID["bingbot"].Count)
	require.EqualValues(t, 1, byID["bingbot"].WhitelistedAddresses)
	// Only the individually verified Bing address is classified. DNS beats CIDR.
	require.EqualValues(t, 5, byID["googlebot"].Count)

	ips, err := svc.Monitoring.ListSourceIPs(ctx, "anthropic", 50)
	require.NoError(t, err)
	require.Len(t, ips, 2)
	require.Equal(t, "216.73.217.1", ips[0].IP.String())

	candidates, err := svc.Monitoring.ListByBanProfile(ctx, AutobanProfile{Limit: 1})
	require.NoError(t, err)
	require.NotContains(t, candidates, net.ParseIP("40.77.167.1"))
	require.NotContains(t, candidates, net.ParseIP("74.7.241.200"))
	// Whitelisting never deletes observations, and clearing manual exemption does
	// not manufacture a DNS identity from the free-form description.
	require.NoError(t, svc.AutoWhitelistIP(ctx, net.ParseIP("40.77.167.1")))
	item, err := svc.Whitelist.Get(ctx, net.ParseIP("40.77.167.1"))
	require.NoError(t, err)
	require.Equal(t, "bingbot", item.SourceID)
	require.NoError(t, svc.Whitelist.Add(ctx, net.ParseIP("40.77.167.2"), "bingbot verified reverse/forward DNS"))
	item, err = svc.Whitelist.Get(ctx, net.ParseIP("40.77.167.2"))
	require.NoError(t, err)
	require.Empty(t, item.SourceID)

	pending, err := svc.Monitoring.listOfTop(ctx, 50, true)
	require.NoError(t, err)

	for _, candidate := range pending {
		require.NotEqual(t, "40.77.167.1", candidate.IP.String())
	}

	rows, err = svc.Monitoring.ListSources(ctx)
	require.NoError(t, err)

	for _, row := range rows {
		if row.ID == "bingbot" {
			require.EqualValues(t, 20, row.Count)
		}
	}

	require.NoError(t, svc.Whitelist.Remove(ctx, net.ParseIP("40.77.167.1")))
	rows, err = svc.Monitoring.ListSources(ctx)
	require.NoError(t, err)

	for _, row := range rows {
		if row.ID == "bingbot" {
			require.EqualValues(t, 20, row.Count)
			require.Zero(t, row.WhitelistedAddresses)
		}
	}
}

func TestSourceOverlapsAndEmptyRegistry(t *testing.T) {
	t.Parallel()
	svc, _ := temporaryNetworkTraffic(t)

	ctx := t.Context()
	for _, source := range []Source{
		{ID: "broad", Name: "Broad", Networks: []string{"2001:db8::/32"}},
		{ID: "narrow", Name: "Narrow", Networks: []string{"2001:db8:1::/64"}},
	} {
		require.NoError(t, svc.Monitoring.CreateSource(ctx, source))
	}

	require.NoError(t, svc.Monitoring.AddCounts(ctx, []MonitoringCount{
		{IP: "2001:db8:1::1", Timestamp: time.Now(), Count: 15},
		{IP: "2001:db8:2::1", Timestamp: time.Now(), Count: 25},
	}))
	rows, err := svc.Monitoring.ListSources(ctx)
	require.NoError(t, err)

	for _, row := range rows {
		switch row.ID {
		case "broad":
			require.EqualValues(t, 25, row.Count)
		case "narrow":
			require.EqualValues(t, 15, row.Count)
		}
	}

	for _, row := range rows {
		require.NoError(t, svc.Monitoring.DeleteSource(ctx, row.ID))
	}

	rows, err = svc.Monitoring.ListSources(ctx)
	require.NoError(t, err)
	require.Empty(t, rows)
}

func TestSourceShowsIntersectingActiveBans(t *testing.T) {
	t.Parallel()
	svc, _ := temporaryNetworkTraffic(t)
	ctx := t.Context()
	// The Googlebot identity is retained after its whitelist exemption is removed.
	ip := net.ParseIP("192.0.2.17")
	require.NoError(t, svc.Whitelist.addVerified(ctx, ip, "googlebot verified reverse/forward DNS"))
	require.NoError(t, svc.Whitelist.Remove(ctx, ip))
	require.NoError(t, svc.Ban.AddNetwork(ctx, netip.MustParsePrefix("192.0.2.0/24"), time.Hour, "dns network"))
	require.NoError(t, svc.Ban.AddNetwork(ctx, netip.MustParsePrefix("216.73.217.0/24"), time.Hour, "anthropic subnet"))
	require.NoError(t, svc.Ban.Add(ctx, net.ParseIP("216.73.216.1"), time.Hour, 9, "individual"))
	require.NoError(t, svc.Ban.Add(ctx, net.ParseIP("216.73.216.2"), -time.Hour, 9, "expired"))
	rows, err := svc.SourcesTraffic(ctx)
	require.NoError(t, err)

	for _, row := range rows {
		switch row.ID {
		case "anthropic":
			require.Len(t, row.Bans, 2)
		case "googlebot":
			require.Len(t, row.Bans, 1)
			require.Equal(t, "192.0.2.0/24", row.Bans[0].Network)
		case "openai":
			require.Empty(t, row.Bans)
		}
	}
}
