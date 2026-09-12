package goautowp

import (
	"context"
	"errors"
	"fmt"
	"time"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/redis/go-redis/v9"
	"google.golang.org/protobuf/proto"
)

const (
	inboxBrandsCacheDuration = time.Second * 30
	inboxBrandsCacheKey      = "API_INBOX_BRANDS_%s"
)

// InboxBrandsCached caches the per-language list of brands that currently have at least one
// inbox picture. Computing it requires joining item_parent_cache (the item ancestor/descendant
// closure table) against every inbox picture, which is expensive at scale - see the /inbox
// performance investigation. A short TTL is enough to absorb repeated /inbox page loads; Flush
// lets callers drop the cache immediately after an event that can change the brand set (e.g. a
// picture entering the inbox).
type InboxBrandsCached struct {
	contentLanguages []string
	redis            *redis.Client
	itemRepository   *items.Repository
}

func NewInboxBrandsCached(
	itemRepository *items.Repository, contentLanguages []string, redisClient *redis.Client,
) *InboxBrandsCached {
	return &InboxBrandsCached{
		itemRepository:   itemRepository,
		contentLanguages: contentLanguages,
		redis:            redisClient,
	}
}

func (s *InboxBrandsCached) Flush(ctx context.Context) error {
	keys := make([]string, 0, len(s.contentLanguages))
	for _, lang := range s.contentLanguages {
		keys = append(keys, fmt.Sprintf(inboxBrandsCacheKey, lang))
	}

	return s.redis.Del(ctx, keys...).Err()
}

func (s *InboxBrandsCached) Get(ctx context.Context, lang string) ([]*InboxBrand, error) {
	key := fmt.Sprintf(inboxBrandsCacheKey, lang)

	cacheItem, err := s.redis.Get(ctx, key).Bytes()
	if err != nil && !errors.Is(err, redis.Nil) {
		return nil, err
	}

	if err == nil {
		var cached Inbox

		if err := proto.Unmarshal(cacheItem, &cached); err != nil {
			return nil, err
		}

		return cached.GetBrands(), nil
	}

	rows, _, err := s.itemRepository.List(ctx, &query.ItemListOptions{
		Language:   lang,
		SortByName: true,
		TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				Pictures: &query.PictureListOptions{
					Status: schema.PictureStatusInbox,
				},
			},
		},
	}, &items.ItemFields{NameOnly: true}, items.OrderByName, false)
	if err != nil {
		return nil, err
	}

	res := make([]*InboxBrand, 0, len(rows))
	for _, row := range rows {
		res = append(res, &InboxBrand{
			Id:   row.ID,
			Name: row.NameOnly,
		})
	}

	cacheBytes, err := proto.Marshal(&Inbox{Brands: res})
	if err != nil {
		return nil, err
	}

	if err := s.redis.Set(ctx, key, cacheBytes, inboxBrandsCacheDuration).Err(); err != nil {
		return nil, err
	}

	return res, nil
}
