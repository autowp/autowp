package goautowp

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
)

// itemBatchFields belongs to one extraction, including its field selection and user.
// Nothing is cached across requests, so private subscriptions and moderator fields stay isolated.
type itemBatchFields struct {
	names          map[int64]string
	comments       map[int64]int32
	specifications map[int64]int32
	languages      map[int64]int32
	links          map[int64]int32
	subscriptions  map[int64]bool
	fullText       map[int64]bool
}

func (s *ItemExtractor) preloadFields(
	ctx context.Context, ids []int64, fields *ItemFields, userCtx UserContext,
) (*itemBatchFields, error) {
	result := &itemBatchFields{}
	if len(ids) == 0 {
		return result, nil
	}

	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	if util.Contains(userCtx.Roles, users.RoleModer) {
		if fields.GetMeta() {
			result.names, err = repository.LanguageNames(ctx, ids, schema.DefaultLanguageCode)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetLinksCount() {
			result.links, err = repository.LinksCounts(ctx, ids)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetItemLanguageCount() {
			result.languages, err = repository.ItemLanguageCounts(ctx, ids)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetSpecificationsCount() {
			attrsRepository, err := s.container.AttrsRepository(ctx)
			if err != nil {
				return nil, err
			}

			result.specifications, err = attrsRepository.ValuesCountsByItemIDs(ctx, ids)
			if err != nil {
				return nil, err
			}
		}
	}

	if fields.GetHasText() {
		result.fullText, err = repository.ItemsWithFullText(ctx, ids)
		if err != nil {
			return nil, err
		}
	}

	if fields.GetSubscription() && userCtx.UserID != 0 {
		result.subscriptions, err = repository.SubscribedItems(ctx, ids, userCtx.UserID)
		if err != nil {
			return nil, err
		}
	}

	if fields.GetCommentsCount() {
		commentsRepository, err := s.container.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		result.comments, err = commentsRepository.TopicStats(ctx, ids, schema.CommentMessageTypeIDItems)
		if err != nil {
			return nil, err
		}
	}

	return result, nil
}
