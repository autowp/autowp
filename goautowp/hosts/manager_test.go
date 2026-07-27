package hosts

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
)

func testManager() *Manager {
	return NewManager(map[string]config.LanguageConfig{
		"en": {Hostname: "wheelsage.org", Timezone: "UTC"},
		"ru": {Hostname: "www.autowp.ru", Timezone: "Europe/Moscow"},
	})
}

func TestURIByLanguage(t *testing.T) {
	t.Parallel()

	mgr := testManager()

	uri, err := mgr.URIByLanguage("en")
	require.NoError(t, err)
	require.Equal(t, "https://wheelsage.org", uri.String())

	uri, err = mgr.URIByLanguage("ru")
	require.NoError(t, err)
	require.Equal(t, "https://www.autowp.ru", uri.String())

	_, err = mgr.URIByLanguage("xx")
	require.ErrorIs(t, err, errHostForLanguageNotFound)
}

func TestTimezoneByLanguage(t *testing.T) {
	t.Parallel()

	mgr := testManager()

	timezone, err := mgr.TimezoneByLanguage("ru")
	require.NoError(t, err)
	require.Equal(t, "Europe/Moscow", timezone)

	_, err = mgr.TimezoneByLanguage("xx")
	require.ErrorIs(t, err, errHostForLanguageNotFound)
}
