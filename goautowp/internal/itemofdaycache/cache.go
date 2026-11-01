package itemofdaycache

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/itemofday"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/util"
	"github.com/redis/go-redis/v9"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

const (
	itemOfDayCacheDuration = time.Hour * 25
	itemOfDayCacheKey      = "API_ITEM_OF_DAY_124_%d_%s"
)

type ItemExtractor interface {
	Extract(
		ctx context.Context,
		row *items.Item,
		fields *pb.ItemFields,
		lang string,
		userCtx authn.User,
	) (*pb.Item, error)
}

type Cache struct {
	contentLanguages   []string
	redis              *redis.Client
	repository         *itemofday.Repository
	itemRepository     *items.Repository
	picturesRepository *pictures.Repository
	extractor          ItemExtractor
}

func New(
	repository *itemofday.Repository, itemRepository *items.Repository, picturesRepository *pictures.Repository,
	contentLanguages []string, redis *redis.Client, extractor ItemExtractor,
) *Cache {
	return &Cache{
		repository:         repository,
		itemRepository:     itemRepository,
		picturesRepository: picturesRepository,
		contentLanguages:   contentLanguages,
		redis:              redis,
		extractor:          extractor,
	}
}

func (s *Cache) FlushItemOfDayCacheByPictureID(ctx context.Context, pictureID int64) error {
	if pictureID == 0 {
		return nil
	}

	rows, err := s.picturesRepository.PictureItems(ctx, &query.PictureItemListOptions{
		PictureID: pictureID,
	}, pictures.PictureItemOrderByNone, 0)
	if err != nil {
		return err
	}

	for _, row := range rows {
		err = s.FlushItemOfDayCache(ctx, row.ItemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Cache) FlushItemOfDayCache(ctx context.Context, itemID int64) error {
	keys := make([]string, 0, len(s.contentLanguages))
	for _, lang := range s.contentLanguages {
		keys = append(keys, fmt.Sprintf(itemOfDayCacheKey, itemID, lang))
	}

	return s.redis.Del(ctx, keys...).Err()
}

func (s *Cache) GetItemOfDay(ctx context.Context, lang string) (*pb.ItemOfDay, error) {
	itemOfDay, err := s.repository.Current(ctx)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	var (
		itemOfDayInfo pb.ItemOfDay
		success       bool
	)

	if itemOfDay == nil {
		return nil, status.Error(codes.NotFound, "Item of day not found")
	}

	if itemOfDay.ItemID == 0 {
		return nil, status.Error(codes.Internal, "Invalid item_id: can't bet zero")
	}

	key := fmt.Sprintf(itemOfDayCacheKey, itemOfDay.ItemID, lang)

	cacheItem, err := s.redis.Get(ctx, key).Bytes()
	if err != nil && !errors.Is(err, redis.Nil) {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err == nil {
		err = proto.Unmarshal(cacheItem, &itemOfDayInfo)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		success = true
	}

	if !success {
		fields := pb.ItemFields{
			NameHtml:              true,
			ItemOfDayPictures:     true,
			AcceptedPicturesCount: true,
			Twins:                 &pb.ItemsRequest{},
			Categories: &pb.ItemsRequest{
				Fields: &pb.ItemFields{NameHtml: true},
			},
			Route: true,
		}
		convertedFields := itemmapper.FieldsFromProto(&fields)

		item, err := s.itemRepository.Item(ctx, &query.ItemListOptions{
			ItemID:   itemOfDay.ItemID,
			Language: lang,
		}, convertedFields)
		if err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return nil, status.Errorf(codes.Internal, "row %d not found", itemOfDay.ItemID)
			}

			return nil, status.Error(codes.Internal, err.Error())
		}

		extracted, err := s.extractor.Extract(ctx, item, &fields, lang, authn.User{})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		itemOfDayInfo = pb.ItemOfDay{
			Item:   extracted,
			UserId: util.NullInt64ToScalar(itemOfDay.UserID),
		}

		cacheBytes, err := proto.Marshal(&itemOfDayInfo)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.redis.Set(ctx, key, cacheBytes, itemOfDayCacheDuration).Err()
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &itemOfDayInfo, nil
}
