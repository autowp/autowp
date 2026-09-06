package i18nbundle

import (
	"encoding/json"
	"testing"
	"text/template"

	"github.com/stretchr/testify/require"
)

// Every message value is parsed as a Go template the first time it's localized with data (see
// New()'s comment) - a broken {{ }} pair (e.g. a missing closing brace) doesn't fail at startup,
// it fails at Localize() time, for whichever locale happens to be requested. Catch that here
// instead, across every locale file, so a typo can't ship unnoticed the way fr.json's
// "pm/user-%s-replies-to-you-%s" (missing a closing brace on {{.Message}}) once did.
func TestMessagesParseAsTemplates(t *testing.T) {
	t.Parallel()

	files, err := LocaleFS.ReadDir(".")
	require.NoError(t, err)

	for _, file := range files {
		t.Run(file.Name(), func(t *testing.T) {
			t.Parallel()

			data, err := LocaleFS.ReadFile(file.Name())
			require.NoError(t, err)

			var messages map[string]string

			require.NoError(t, json.Unmarshal(data, &messages))

			for id, value := range messages {
				_, err := template.New(id).Parse(value)
				require.NoErrorf(t, err, "message %q: %q", id, value)
			}
		})
	}
}
