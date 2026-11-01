package itemparentextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
)

type ItemExtractor interface {
	ExtractRows(
		ctx context.Context,
		rows []*items.Item,
		fields *pb.ItemFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Item, error)
}

type PictureExtractor interface {
	ExtractRows(
		ctx context.Context,
		rows []*schema.PictureRow,
		fields *pb.PictureFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Picture, error)
}

type Extractor struct {
	itemsRepository    func(ctx context.Context) (*items.Repository, error)
	picturesRepository func(ctx context.Context) (*pictures.Repository, error)
	itemExtractor      ItemExtractor
	pictureExtractor   PictureExtractor
}

func New(
	itemsRepository func(ctx context.Context) (*items.Repository, error),
	picturesRepository func(ctx context.Context) (*pictures.Repository, error),
	itemExtractor ItemExtractor,
	pictureExtractor PictureExtractor,
) *Extractor {
	return &Extractor{
		itemsRepository:    itemsRepository,
		picturesRepository: picturesRepository,
		itemExtractor:      itemExtractor,
		pictureExtractor:   pictureExtractor,
	}
}

func (s *Extractor) ExtractRow(
	ctx context.Context,
	row *items.ItemParent,
	fields *pb.ItemParentFields,
	lang string,
	userCtx authn.User,
) (*pb.ItemParent, error) {
	res, err := s.ExtractRows(ctx, []*items.ItemParent{row}, fields, lang, userCtx)

	return res[0], err
}

func (s *Extractor) ExtractRows(
	ctx context.Context,
	rows []*items.ItemParent,
	fields *pb.ItemParentFields,
	lang string,
	userCtx authn.User,
) ([]*pb.ItemParent, error) {
	itemFields := fields.GetItem()
	parentFields := fields.GetParent()

	var (
		itemsMap           map[int64]*pb.Item
		parentsMap         map[int64]*pb.Item
		lookups            []func(context.Context) error
		descendantPictures map[int64]*pb.PicturesList
		duplicateParents   map[int64]*pb.Item
		duplicateChildren  map[int64]*pb.Item
	)

	// Child and parent selections are independent. Share the existing lookup
	// runner so nested extraction keeps its concurrency limit and cancellation.
	if itemFields != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			ids := make([]int64, 0, len(rows))
			for _, row := range rows {
				ids = append(ids, row.ItemID)
			}

			var err error

			itemsMap, err = s.prefetchItems(ctx, ids, lang, itemFields, userCtx)

			return err
		})
	}

	if parentFields != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			ids := make([]int64, 0, len(rows))
			for _, row := range rows {
				ids = append(ids, row.ParentID)
			}

			var err error

			parentsMap, err = s.prefetchItems(ctx, ids, lang, parentFields, userCtx)

			return err
		})
	}

	if request := fields.GetChildDescendantPictures(); request != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			ctx = extraction.WithPictureParallelism(ctx, 1)
			descendantPictures, err = s.preloadChildDescendantPictures(ctx, rows, request, lang, userCtx)

			return err
		})
	}

	for _, selection := range []struct {
		fields *pb.ItemFields
		parent bool
		target *map[int64]*pb.Item
	}{
		{fields.GetDuplicateParent(), true, &duplicateParents},
		{fields.GetDuplicateChild(), false, &duplicateChildren},
	} {
		if selection.fields == nil || len(rows) == 0 {
			continue
		}

		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			ctx = extraction.WithPictureParallelism(ctx, 1)
			*selection.target, err = s.preloadDuplicates(ctx, rows, selection.fields, selection.parent, lang, userCtx)

			return err
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	res := make([]*pb.ItemParent, 0, len(rows))

	for index, row := range rows {
		resRow := &pb.ItemParent{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
			Type:     itemmapper.ParentTypeToProto(row.Type),
			Catname:  row.Catname,
		}

		resRow.Item = itemsMap[row.ItemID]
		resRow.Parent = parentsMap[row.ParentID]

		resRow.DuplicateParent = duplicateParents[int64(index)]
		resRow.DuplicateChild = duplicateChildren[int64(index)]

		if fields.GetChildDescendantPictures() != nil {
			resRow.ChildDescendantPictures = descendantPictures[row.ItemID]
		}

		res = append(res, resRow)
	}

	return res, nil
}

func (s *Extractor) prefetchItems(
	ctx context.Context, ids []int64, lang string, fields *pb.ItemFields, userCtx authn.User,
) (map[int64]*pb.Item, error) {
	itemsRepository, err := s.itemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	itemRows, _, err := itemsRepository.List(ctx, &query.ItemListOptions{
		ItemIDs:  ids,
		Language: lang,
	}, itemmapper.FieldsFromProto(fields), items.OrderByNone, false)
	if err != nil {
		return nil, err
	}

	// The two relation branches share the outer lookup budget. Nested pictures
	// stay inline rather than multiplying that budget for each branch.
	ctx = extraction.WithPictureParallelism(ctx, 1)

	extracted, err := s.itemExtractor.ExtractRows(ctx, itemRows, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemsMap := make(map[int64]*pb.Item, len(extracted))
	for _, item := range extracted {
		itemsMap[item.GetId()] = item
	}

	return itemsMap, nil
}
