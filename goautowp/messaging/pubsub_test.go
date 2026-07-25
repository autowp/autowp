package messaging

import (
	"context"
	"encoding/json"
	"testing"

	"github.com/stretchr/testify/require"
)

func TestWsEventJSONEnvelope(t *testing.T) {
	t.Parallel()

	payload, err := json.Marshal(wsEvent{UserIDs: []int64{1, 2}})
	require.NoError(t, err)
	require.JSONEq(t, `{"user_ids":[1,2]}`, string(payload))

	var decoded wsEvent

	require.NoError(t, json.Unmarshal(payload, &decoded))
	require.Equal(t, []int64{1, 2}, decoded.UserIDs)
}

func TestPublishEventNoopWhenEmpty(t *testing.T) {
	t.Parallel()

	require.NoError(t, PublishEvent(context.Background(), nil, nil))
}
