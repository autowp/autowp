package pictures

import (
	"context"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/redis/go-redis/v9"
)

// PubSubChannel is the single Redis Pub/Sub channel every server instance subscribes to
// in order to fan out "picture accepted" events across all pods.
const PubSubChannel = "pictures:ws-events"

// resubscribeDelay is the backoff between resubscribe attempts after the Redis
// subscription drops.
const resubscribeDelay = time.Second

// acceptedEventPayload is an arbitrary, content-free marker: the message itself is never
// inspected, only its arrival on PubSubChannel matters.
const acceptedEventPayload = "1"

// PublishAccepted notifies every server instance that a picture was newly accepted.
func PublishAccepted(ctx context.Context, redisClient *redis.Client) error {
	return redisClient.Publish(ctx, PubSubChannel, acceptedEventPayload).Err()
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
		case _, ok := <-ch:
			if !ok {
				return nil
			}

			hub.Broadcast()
		}
	}
}
