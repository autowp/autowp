package pictureextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
)

func (s *Extractor) preloadPictureItems(
	ctx context.Context, ids []int64, request *pb.PictureItemsRequest, lang string, userCtx authn.User,
) (map[int64][]*pb.PictureItem, error) {
	result := make(map[int64][]*pb.PictureItem, len(ids))
	for _, id := range ids {
		result[id] = []*pb.PictureItem{}
	}

	if len(ids) == 0 {
		return result, nil
	}

	options, err := querymapper.PictureItemListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureItemListOptions{}
	}

	options.PictureID = 0
	options.PictureIDs = ids

	repository, err := s.dependencies.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := repository.PictureItems(ctx, options, picturemapper.ItemsOrderFromProto(request.GetOrder()), 0)
	if err != nil {
		return nil, err
	}

	extractor := s.dependencies.PictureItemExtractor()

	extracted, err := extractor.ExtractRows(ctx, rows, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	for _, item := range extracted {
		result[item.GetPictureId()] = append(result[item.GetPictureId()], item)
	}

	return result, nil
}

func (s *Extractor) preloadPictureModerVotes(
	ctx context.Context, ids []int64, request *pb.PictureModerVoteRequest,
) (map[int64][]*pb.PictureModerVote, error) {
	result := make(map[int64][]*pb.PictureModerVote, len(ids))
	for _, id := range ids {
		result[id] = []*pb.PictureModerVote{}
	}

	if len(ids) == 0 {
		return result, nil
	}

	repository, err := s.dependencies.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := repository.PictureModerVotesBatch(
		ctx,
		ids,
		querymapper.PictureModerVoteListOptionsFromProto(request.GetOptions()),
	)
	if err != nil {
		return nil, err
	}

	extracted := picturemapper.ModerVotesToProto(rows)

	for _, vote := range extracted {
		result[vote.GetPictureId()] = append(result[vote.GetPictureId()], vote)
	}

	return result, nil
}
