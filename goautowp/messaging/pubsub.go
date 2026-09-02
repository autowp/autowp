package messaging

import (
	"context"
	"encoding/json"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/redis/go-redis/v9"
)

// PubSubChannel is the single Redis Pub/Sub channel every server instance subscribes to
// in order to fan out "messages changed" events across all pods.
const PubSubChannel = "messaging:ws-events"

// resubscribeDelay is the backoff between resubscribe attempts after the Redis
// subscription drops.
const resubscribeDelay = time.Second

// wsEvent is the wire format published to Redis. It only ever carries the IDs of the
// users whose message list changed, never message content.
type wsEvent struct {
	UserIDs []int64 `json:"user_ids"`
}

// PublishEvent notifies every server instance that userIDs' message lists changed.
func PublishEvent(ctx context.Context, redisClient *redis.Client, userIDs []int64) error {
	if len(userIDs) == 0 {
		return nil
	}

	payload, err := json.Marshal(wsEvent{UserIDs: userIDs})
	if err != nil {
		return err
	}

	return redisClient.Publish(ctx, PubSubChannel, payload).Err()
}

// Subscribe blocks, forwarding every published event to hub.Broadcast, until quit fires.
// It resubscribes automatically if the underlying Redis connection drops.
func Subscribe(ctx context.Context, redisClient *redis.Client, hub *Hub, quit chan bool) error {
	for {
		select {
		case <-quit:
			return nil
		default:
		}

		if err := subscribeOnce(ctx, redisClient, hub, quit); err != nil {
			logging.Error(err.Error())
		}

		select {
		case <-quit:
			return nil
		case <-time.After(resubscribeDelay):
		}
	}
}

func subscribeOnce(ctx context.Context, redisClient *redis.Client, hub *Hub, quit chan bool) error {
	pubsub := redisClient.Subscribe(ctx, PubSubChannel)
	defer pubsub.Close()

	ch := pubsub.Channel()

	for {
		select {
		case <-quit:
			return nil
		case msg, ok := <-ch:
			if !ok {
				return nil
			}

			var event wsEvent

			if err := json.Unmarshal([]byte(msg.Payload), &event); err != nil {
				logging.Error(err.Error())

				continue
			}

			hub.Broadcast(event.UserIDs)
		}
	}
}
