package goautowp

import (
	"context"

	"github.com/autowp/goautowp/query"
)

func (s *PictureExtractor) preloadPictureItems(
	ctx context.Context, ids []int64, request *PictureItemsRequest, lang string, userCtx UserContext,
) (map[int64][]*PictureItem, error) {
	result := make(map[int64][]*PictureItem, len(ids))
	for _, id := range ids {
		result[id] = []*PictureItem{}
	}

	if len(ids) == 0 {
		return result, nil
	}

	options, err := convertPictureItemListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureItemListOptions{}
	}

	options.PictureID = 0
	options.PictureIDs = ids

	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := repository.PictureItems(ctx, options, convertPictureItemsOrder(request.GetOrder()), 0)
	if err != nil {
		return nil, err
	}

	extracted, err := s.container.PictureItemExtractor().ExtractRows(ctx, rows, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	for _, item := range extracted {
		result[item.GetPictureId()] = append(result[item.GetPictureId()], item)
	}

	return result, nil
}

func (s *PictureExtractor) preloadPictureModerVotes(
	ctx context.Context, ids []int64, request *PictureModerVoteRequest,
) (map[int64][]*PictureModerVote, error) {
	result := make(map[int64][]*PictureModerVote, len(ids))
	for _, id := range ids {
		result[id] = []*PictureModerVote{}
	}

	if len(ids) == 0 {
		return result, nil
	}

	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := repository.PictureModerVotesBatch(ctx, ids, convertPictureModerVoteListOptions(request.GetOptions()))
	if err != nil {
		return nil, err
	}

	extracted, err := NewPictureModerVoteExtractor().ExtractRows(rows)
	if err != nil {
		return nil, err
	}

	for _, vote := range extracted {
		result[vote.GetPictureId()] = append(result[vote.GetPictureId()], vote)
	}

	return result, nil
}
