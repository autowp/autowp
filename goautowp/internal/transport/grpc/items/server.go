package items

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"html"
	"maps"
	"net/url"
	"regexp"
	"slices"
	"strings"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/attrs"
	"github.com/autowp/goautowp/internal/audit"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/brandssprite"
	"github.com/autowp/goautowp/internal/catalogue"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/compliance"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/frontend"
	"github.com/autowp/goautowp/internal/hosts"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/index"
	"github.com/autowp/goautowp/internal/itemofdaycache"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/messaging"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/itemextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/itemparentextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"github.com/nicksnyder/go-i18n/v2/i18n"
	"github.com/redis/go-redis/v9"
	"github.com/twpayne/go-geom"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

var errNoEndpointProvided = errors.New("no endpoints provided")

const (
	itemLinkNameMaxLength = 255

	typicalPicturesInList             = 4
	topSpecsContributorsCacheDuration = time.Hour

	topSpecsContributorsValuesCountThreshold = 10
	topSpecsContributorsInDays               = 3
	topSpecsContributorsLimit                = 4

	messageItemModerURL     = "ItemModerURL"
	messageItemName         = "ItemName"
	messageUserURL          = "UserURL"
	messageChangesFromField = "From"
	messageChangesToField   = "To"
)

const (
	itemNameField               = "name"
	itemParentNameField         = "name"
	itemLinkNameField           = "name"
	itemLanguageNameField       = "name"
	itemLanguageLanguageField   = "language"
	itemParentLanguageNameField = "name"
)

func validateItemParent(parent *pb.ItemParent) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	catnameInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{
			&validation.StringTrimFilter{},
			&validation.StringSingleSpaces{},
			&validation.StringToLower{},
			&validation.StringSanitizeFilename{},
		},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Max: schema.ItemParentMaxCatname},
		},
	}

	parent.Catname, problems, err = catnameInputFilter.IsValidString(parent.GetCatname())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       itemParentNameField,
			Description: fv,
		})
	}

	return result, nil
}

type Server struct {
	pb.UnimplementedItemsServer

	repository            *items.Repository
	itemParentLanguage    *items.ItemParentLanguageRepository
	auth                  *authn.Service
	contentLanguages      []string
	textstorageRepository *textstorage.Repository
	extractor             *itemextractor.Extractor
	i18n                  *i18nbundle.I18n
	attrsRepository       *attrs.Repository
	picturesRepository    *pictures.Repository
	index                 *index.Cache
	events                *audit.Recorder
	usersRepository       *users.Repository
	messagingRepository   *messaging.Repository
	hostManager           *hosts.Manager
	itemParentExtractor   *itemparentextractor.Extractor
	redis                 *redis.Client
	catalogue             *catalogue.Service
	fileStorageConfig     config.FileStorageConfig
	itemOfDayCached       *itemofdaycache.Cache
	complianceRepository  *compliance.Repository
	commentsRepository    *comments.Repository
}

func NewServer(
	repository *items.Repository,
	itemParentLanguage *items.ItemParentLanguageRepository,
	auth *authn.Service,
	contentLanguages []string,
	textstorageRepository *textstorage.Repository,
	extractor *itemextractor.Extractor,
	i18n *i18nbundle.I18n,
	attrsRepository *attrs.Repository,
	picturesRepository *pictures.Repository,
	index *index.Cache,
	events *audit.Recorder,
	usersRepository *users.Repository,
	messagingRepository *messaging.Repository,
	hostManager *hosts.Manager,
	itemParentExtractor *itemparentextractor.Extractor,
	redis *redis.Client,
	catalogue *catalogue.Service,
	fileStorageConfig config.FileStorageConfig,
	itemOfDayCached *itemofdaycache.Cache,
	complianceRepository *compliance.Repository,
	commentsRepository *comments.Repository,
) *Server {
	return &Server{
		repository:            repository,
		itemParentLanguage:    itemParentLanguage,
		auth:                  auth,
		contentLanguages:      contentLanguages,
		textstorageRepository: textstorageRepository,
		extractor:             extractor,
		i18n:                  i18n,
		attrsRepository:       attrsRepository,
		picturesRepository:    picturesRepository,
		index:                 index,
		events:                events,
		usersRepository:       usersRepository,
		messagingRepository:   messagingRepository,
		hostManager:           hostManager,
		itemParentExtractor:   itemParentExtractor,
		redis:                 redis,
		catalogue:             catalogue,
		fileStorageConfig:     fileStorageConfig,
		itemOfDayCached:       itemOfDayCached,
		complianceRepository:  complianceRepository,
		commentsRepository:    commentsRepository,
	}
}

func (s *Server) GetBrands(
	ctx context.Context,
	in *pb.GetBrandsRequest,
) (*pb.BrandsList, error) {
	if s == nil {
		return nil, status.Error(codes.Internal, "self not initialized")
	}

	lang := in.GetLanguage()

	resultArray, err := s.index.BrandsCache(ctx, lang)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.BrandsListLine, 0, len(resultArray))

	for _, line := range resultArray {
		characters := make([]*pb.BrandsListCharacter, 0, len(line.Characters))

		for _, character := range line.Characters {
			rows := make([]*pb.BrandsListItem, 0, len(character.Items))
			for _, item := range character.Items {
				rows = append(rows, &pb.BrandsListItem{
					Id:                    item.ID,
					Catname:               item.Catname,
					Name:                  item.Name,
					ItemsCount:            item.ItemsCount,
					NewItemsCount:         item.NewItemsCount,
					AcceptedPicturesCount: item.AcceptedPicturesCount,
				})
			}

			characters = append(characters, &pb.BrandsListCharacter{
				Id:        character.ID,
				Character: character.Character,
				Items:     rows,
			})
		}

		var category pb.BrandsListLine_Category

		switch line.Category {
		case items.BrandsListCategoryDefault:
			category = pb.BrandsListLine_DEFAULT
		case items.BrandsListCategoryNumber:
			category = pb.BrandsListLine_NUMBER
		case items.BrandsListCategoryCyrillic:
			category = pb.BrandsListLine_CYRILLIC
		case items.BrandsListCategoryLatin:
			category = pb.BrandsListLine_LATIN
		}

		result = append(result, &pb.BrandsListLine{
			Category:   category,
			Characters: characters,
		})
	}

	return &pb.BrandsList{Lines: result}, nil
}

func (s *Server) GetTopBrandsList(
	ctx context.Context,
	in *pb.GetTopBrandsListRequest,
) (*pb.TopBrandsList, error) {
	if s == nil {
		return nil, status.Error(codes.Internal, "self not initialized")
	}

	cache, err := s.index.TopBrandsCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	brands := make([]*pb.TopBrandsListItem, len(cache.Items))
	for idx, brand := range cache.Items {
		brands[idx] = &pb.TopBrandsListItem{
			Id:            brand.ID,
			Catname:       util.NullStringToString(brand.Catname),
			Name:          brand.NameOnly,
			ItemsCount:    brand.DescendantsCount,
			NewItemsCount: brand.NewDescendantsCount,
		}
	}

	return &pb.TopBrandsList{
		Brands: brands,
		Total:  int32(cache.Total), //nolint: gosec
	}, nil
}

func (s *Server) GetTopPersonsList(
	ctx context.Context,
	in *pb.GetTopPersonsListRequest,
) (*pb.TopPersonsList, error) {
	var pictureItemType schema.PictureItemType

	switch in.GetPictureItemType() { //nolint:exhaustive
	case pb.PictureItemType_PICTURE_ITEM_CONTENT:
		pictureItemType = schema.PictureItemTypeContent
	case pb.PictureItemType_PICTURE_ITEM_AUTHOR:
		pictureItemType = schema.PictureItemTypeAuthor
	default:
		return nil, status.Error(codes.InvalidArgument, "Unexpected picture_item_type")
	}

	res, err := s.index.PersonsCache(ctx, pictureItemType, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.TopPersonsListItem, len(res))
	for idx, b := range res {
		is[idx] = &pb.TopPersonsListItem{
			Id:   b.ID,
			Name: b.NameOnly,
		}
	}

	return &pb.TopPersonsList{
		Items: is,
	}, nil
}

func (s *Server) GetTopFactoriesList(
	ctx context.Context,
	in *pb.GetTopFactoriesListRequest,
) (*pb.TopFactoriesList, error) {
	res, err := s.index.FactoriesCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.TopFactoriesListItem, len(res))
	for idx, b := range res {
		is[idx] = &pb.TopFactoriesListItem{
			Id:       b.ID,
			Name:     b.NameOnly,
			Count:    b.ChildItemsCount,
			NewCount: b.NewChildItemsCount,
		}
	}

	return &pb.TopFactoriesList{
		Items: is,
	}, nil
}

func (s *Server) GetTopCategoriesList(
	ctx context.Context,
	in *pb.GetTopCategoriesListRequest,
) (*pb.TopCategoriesList, error) {
	res, err := s.index.CategoriesCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.TopCategoriesListItem, len(res))
	for idx, itm := range res {
		is[idx] = &pb.TopCategoriesListItem{
			Id:       itm.ID,
			Name:     itm.NameOnly,
			Catname:  util.NullStringToString(itm.Catname),
			Count:    itm.DescendantsCount,
			NewCount: itm.NewDescendantsCount,
		}
	}

	return &pb.TopCategoriesList{
		Items: is,
	}, nil
}

func (s *Server) GetTopTwinsBrandsList(
	ctx context.Context,
	in *pb.GetTopTwinsBrandsListRequest,
) (*pb.TopTwinsBrandsList, error) {
	twinsData, err := s.index.TwinsCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.TwinsBrandsListItem, len(twinsData.Res))
	for idx, twin := range twinsData.Res {
		is[idx] = &pb.TwinsBrandsListItem{
			Id:       twin.ID,
			Catname:  util.NullStringToString(twin.Catname),
			Name:     twin.NameOnly,
			Count:    twin.DescendantsParentsCount,
			NewCount: twin.NewDescendantsParentsCount,
		}
	}

	return &pb.TopTwinsBrandsList{
		Items: is,
		Count: int32(twinsData.Count), //nolint: gosec
	}, nil
}

func (s *Server) GetTwinsBrandsList(
	ctx context.Context,
	in *pb.GetTwinsBrandsListRequest,
) (*pb.TwinsBrandsList, error) {
	twinsData, _, err := s.repository.List(ctx, &query.ItemListOptions{
		Language: in.GetLanguage(),
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ItemParentByItemID: &query.ItemParentListOptions{
				ParentItems: &query.ItemListOptions{
					TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDTwins},
				},
			},
		},
		TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		SortByName: true,
	}, &items.ItemFields{
		NameOnly:                   true,
		DescendantsParentsCount:    true,
		NewDescendantsParentsCount: true,
	}, items.OrderByNone, false)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.TwinsBrandsListItem, len(twinsData))
	for idx, brand := range twinsData {
		is[idx] = &pb.TwinsBrandsListItem{
			Id:       brand.ID,
			Catname:  util.NullStringToString(brand.Catname),
			Name:     brand.NameOnly,
			Count:    brand.DescendantsParentsCount,
			NewCount: brand.NewDescendantsParentsCount,
		}
	}

	return &pb.TwinsBrandsList{
		Items: is,
	}, nil
}

// requestsModeratorOnlyFields reports whether fields asks for any field
// that's only meant to be visible to moderators.
func requestsModeratorOnlyFields(fields *items.ItemFields) bool {
	return fields != nil && (fields.InboxPicturesCount || fields.CommentsAttentionsCount || fields.Meta)
}

// requestsModeratorOnlyListFilter reports whether inOptions asks for a list
// filter that's only meant to be usable by moderators.
func requestsModeratorOnlyListFilter(inOptions *pb.ItemListOptions) bool {
	return inOptions.GetExcludeSelfAndChilds() > 0 || inOptions.GetAutocomplete() != "" ||
		inOptions.GetSuggestionsTo() != 0
}

func (s *Server) Item(ctx context.Context, in *pb.ItemRequest) (*pb.Item, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	fields := itemmapper.FieldsFromProto(in.GetFields())

	if requestsModeratorOnlyFields(fields) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	res, err := s.repository.Item(ctx, &query.ItemListOptions{
		ItemID:   in.GetId(),
		Language: in.GetLanguage(),
	}, fields)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.extractor.Extract(ctx, res, in.GetFields(), in.GetLanguage(), userCtx)
}

func (s *Server) List(ctx context.Context, in *pb.ItemsRequest) (*pb.ItemList, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	fields := itemmapper.FieldsFromProto(in.GetFields())
	if requestsModeratorOnlyFields(fields) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	inOptions := in.GetOptions()

	if requestsModeratorOnlyListFilter(inOptions) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := querymapper.ItemListOptionsFromProto(inOptions)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	if options == nil {
		options = &query.ItemListOptions{}
	}

	options.Language = in.GetLanguage()
	options.Limit = in.GetLimit()
	options.Page = in.GetPage()

	relatedGroupsOf := inOptions.GetRelatedGroupsOf()
	if relatedGroupsOf != 0 {
		groups, err := s.repository.RelatedCarGroups(ctx, relatedGroupsOf)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		options.ItemIDs = slices.Collect(maps.Keys(groups))
		if len(options.ItemIDs) == 0 {
			options.ItemIDs = []int64{0}
		}
	}

	var order items.OrderBy

	order, options.SortByName = itemmapper.OrderFromProto(in.GetOrder())

	res, pages, err := s.repository.List(ctx, options, fields, order, true)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*pb.Item, len(res))
	for idx, i := range res {
		is[idx], err = s.extractor.Extract(ctx, i, in.GetFields(), in.GetLanguage(), userCtx)
		if err != nil {
			return nil, err
		}
	}

	var paginator *pb.Pages
	if pages != nil {
		paginator = &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		}
	}

	return &pb.ItemList{
		Items:     is,
		Paginator: paginator,
	}, nil
}

func (s *Server) GetItemsFirstChars(
	ctx context.Context,
	in *pb.ItemsFirstCharsRequest,
) (*pb.AlphaResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	inOptions := in.GetOptions()

	if requestsModeratorOnlyListFilter(inOptions) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := querymapper.ItemListOptionsFromProto(inOptions)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	if options == nil {
		options = &query.ItemListOptions{}
	}

	options.Language = in.GetLanguage()

	chars, err := s.repository.ItemsFirstChars(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return categorizeFirstChars(chars), nil
}

func (s *Server) GetContentLanguages(
	_ context.Context,
	_ *emptypb.Empty,
) (*pb.ContentLanguages, error) {
	return &pb.ContentLanguages{
		Languages: s.contentLanguages,
	}, nil
}

func (s *Server) GetItemLink(
	ctx context.Context,
	in *pb.ItemLinksRequest,
) (*pb.ItemLink, error) {
	options, err := querymapper.LinkListOptionsFromProto(in.GetOptions())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	row, err := s.repository.Link(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return itemmapper.LinkToProto(row), nil
}

func (s *Server) GetItemLinks(
	ctx context.Context,
	in *pb.ItemLinksRequest,
) (*pb.ItemLinks, error) {
	inOptions := in.GetOptions()

	if inOptions.GetItemParentCacheDescendant() == nil && inOptions.GetId() == 0 &&
		inOptions.GetItemId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "ItemLinkOptions is almost empty")
	}

	options, err := querymapper.LinkListOptionsFromProto(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	rows, err := s.repository.Links(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.ItemLinks{
		Items: itemmapper.LinksToProto(rows),
	}, nil
}

func (s *Server) DeleteItemLink(
	ctx context.Context,
	in *pb.ItemLinkRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	err = s.repository.DeleteLink(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) CreateItemLink(
	ctx context.Context,
	in *pb.ItemLink,
) (*pb.CreateItemLinkResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemLink(in)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	id, err := s.repository.CreateLink(ctx, schema.LinkRow{
		Name: in.GetName(), URL: in.GetUrl(), Type: in.GetType(), ItemID: in.GetItemId(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.CreateItemLinkResponse{
		Id: id,
	}, nil
}

func (s *Server) UpdateItemLink(
	ctx context.Context,
	in *pb.ItemLink,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemLink(in)
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	err = s.repository.UpdateLink(ctx, schema.LinkRow{
		ID: in.GetId(), Name: in.GetName(), URL: in.GetUrl(), Type: in.GetType(), ItemID: in.GetItemId(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func validateItemLink(link *pb.ItemLink) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	nameInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{
			&validation.StringTrimFilter{},
			&validation.StringSingleSpaces{},
		},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: itemLinkNameMaxLength},
		},
	}

	link.Name, problems, err = nameInputFilter.IsValidString(link.GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       itemLinkNameField,
			Description: fv,
		})
	}

	urlInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.URL{},
			&validation.StringLength{Min: 0, Max: itemLinkNameMaxLength},
		},
	}

	link.Url, problems, err = urlInputFilter.IsValidString(link.GetUrl())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "url",
			Description: fv,
		})
	}

	typeInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.InArray{HaystackString: []string{
				"default",
				"official",
				"club",
				"helper",
			}},
		},
	}

	link.Type, problems, err = typeInputFilter.IsValidString(link.GetType())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "type",
			Description: fv,
		})
	}

	return result, nil
}

func (s *Server) GetItemVehicleTypes(
	ctx context.Context,
	in *pb.GetItemVehicleTypesRequest,
) (*pb.GetItemVehicleTypesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetItemId() == 0 && in.GetVehicleTypeId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ItemVehicleTypes(ctx, in.GetItemId(), in.GetVehicleTypeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.ItemVehicleType, 0, len(rows))
	for _, row := range rows {
		res = append(res, &pb.ItemVehicleType{ItemId: row.ItemID, VehicleTypeId: row.VehicleTypeID})
	}

	return &pb.GetItemVehicleTypesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetItemVehicleType(
	ctx context.Context,
	in *pb.ItemVehicleTypeRequest,
) (*pb.ItemVehicleType, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetItemId() == 0 && in.GetVehicleTypeId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	row, err := s.repository.ItemVehicleType(ctx, in.GetItemId(), in.GetVehicleTypeId())
	if errors.Is(err, sql.ErrNoRows) {
		return nil, status.Error(codes.NotFound, "not found")
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.ItemVehicleType{ //nolint:exhaustruct
		ItemId:        row.ItemID,
		VehicleTypeId: row.VehicleTypeID,
	}, nil
}

func (s *Server) CreateItemVehicleType(
	ctx context.Context,
	in *pb.ItemVehicleType,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	count, err := s.repository.Count(ctx, query.ItemListOptions{
		ItemID: in.GetItemId(),
		TypeID: []schema.ItemTableItemTypeID{
			schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDTwins,
		},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if count == 0 {
		return nil, status.Error(codes.NotFound, sql.ErrNoRows.Error())
	}

	err = s.repository.AddItemVehicleType(ctx, in.GetItemId(), in.GetVehicleTypeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteItemVehicleType(
	ctx context.Context,
	in *pb.ItemVehicleTypeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	err = s.repository.RemoveItemVehicleType(ctx, in.GetItemId(), in.GetVehicleTypeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetItemLanguages(
	ctx context.Context, in *pb.GetItemLanguagesRequest,
) (*pb.ItemLanguages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ItemLanguageList(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	textIDs := make(map[int32]struct{}, len(rows)*2)
	for _, row := range rows {
		if row.TextID > 0 {
			textIDs[row.TextID] = struct{}{}
		}

		if row.FullTextID > 0 {
			textIDs[row.FullTextID] = struct{}{}
		}
	}

	texts, err := s.textstorageRepository.Texts(ctx, slices.Collect(maps.Keys(textIDs)))
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.ItemLanguage, len(rows))

	for idx, row := range rows {
		// Texts omits missing IDs; preserve Text's error for dangling references.
		for _, id := range []int32{row.TextID, row.FullTextID} {
			if _, found := texts[id]; id > 0 && !found {
				return nil, status.Errorf(codes.Internal, "%s: `%v`", textstorage.ErrTextNotFound, id)
			}
		}

		result[idx] = &pb.ItemLanguage{
			ItemId:     row.ItemID,
			Name:       row.Name,
			Language:   row.Language,
			TextId:     row.TextID,
			Text:       texts[row.TextID],
			FullTextId: row.FullTextID,
			FullText:   texts[row.FullTextID],
		}
	}

	return &pb.ItemLanguages{
		Items: result,
	}, nil
}

func (s *Server) UpdateItemLanguage(
	ctx context.Context,
	in *pb.ItemLanguage,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemLanguage(in)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	itemID := in.GetItemId()

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: itemID, Language: audit.DefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	ctx = context.WithoutCancel(ctx)

	changes, err := s.repository.UpdateItemLanguage(
		ctx, itemID, in.GetLanguage(), in.GetName(), in.GetText(), in.GetFullText(), userCtx.UserID,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(changes) > 0 {
		err := s.repository.UserItemSubscribe(ctx, in.GetItemId(), userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		author, err := s.usersRepository.User(
			ctx,
			&query.UserListOptions{ID: userCtx.UserID},
			users.UserFields{},
			users.OrderByNone,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		language := in.GetLanguage()

		falseRef := false

		subscribers, _, err := s.usersRepository.Users(ctx, &query.UserListOptions{
			Deleted: &falseRef,
			ItemSubscribe: &query.UserItemSubscribeListOptions{
				ItemIDs: []int64{itemID},
			},
			ExcludeIDs: []int64{userCtx.UserID},
		}, users.UserFields{}, users.OrderByNone)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		for _, subscriber := range subscribers {
			uri, err := s.hostManager.URIByLanguage(subscriber.Language)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			itemNameText, err := s.formatItemNameText(item, subscriber.Language)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			localizer := s.i18n.Localizer(subscriber.Language)

			changesStrs := make([]string, 0, len(changes))

			for _, field := range changes {
				changesStr, err := localizer.Localize(&i18n.LocalizeConfig{
					DefaultMessage: &i18n.Message{
						ID: field,
					},
				})
				if err != nil {
					return nil, status.Error(codes.Internal, err.Error())
				}

				changesStrs = append(changesStrs, changesStr+" ("+language+")")
			}

			err = s.messagingRepository.CreateMessageFromTemplate(
				ctx, 0, subscriber.ID, "pm/user-%s-edited-item-language-%s-%s",
				map[string]interface{}{
					messageUserURL:      frontend.UserURL(uri, author.ID, author.Identity),
					messageItemName:     itemNameText,
					messageItemModerURL: frontend.ItemModerURL(uri, itemID),
					"Changes":           strings.Join(changesStrs, "\n"),
				},
				subscriber.Language,
			)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.events.Add(ctx, audit.Event{
			UserID:  userCtx.UserID,
			Message: "Редактирование языковых названия, описания и полного описания автомобиля " + itemNameText,
			Items:   []int64{itemID},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, itemID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if item.ItemTypeID == schema.ItemTableItemTypeIDPerson && in.GetName() != "" {
		if err = s.complianceRepository.RecordNameHitIfMatched(ctx, in.GetName()); err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetItemParentLanguages(
	ctx context.Context, in *pb.GetItemParentLanguagesRequest,
) (*pb.ItemParentLanguages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ParentLanguageList(ctx, in.GetItemId(), in.GetParentId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.ItemParentLanguage, len(rows))
	for idx, row := range rows {
		result[idx] = &pb.ItemParentLanguage{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
			Name:     row.Name,
			Language: row.Language,
		}
	}

	return &pb.ItemParentLanguages{
		Items: result,
	}, nil
}

func (s *Server) GetStats(ctx context.Context, _ *emptypb.Empty) (*pb.StatsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	var (
		totalBrands                        int
		totalCars                          int
		totalCarAttrs                      int32
		carAttrsValues                     int32
		carsWith4OrMorePictures            int32
		itemsWithBeginYear                 int
		itemsWithBeginAndEndYears          int
		itemsWithBeginAndEndYearsAndMonths int
		brandsWithLogo                     int
		totalPictures                      int
		picturesWithCopyrights             int
	)

	lookups := []func(context.Context) error{
		func(ctx context.Context) error {
			var err error

			totalBrands, err = s.repository.Count(ctx, query.ItemListOptions{
				TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			totalCars, err = s.repository.Count(ctx, query.ItemListOptions{
				TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDVehicle},
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			totalCarAttrs, err = s.attrsRepository.TotalZoneAttrs(ctx, 1)

			return err
		},
		func(ctx context.Context) error {
			var err error

			carAttrsValues, err = s.attrsRepository.TotalValues(ctx)

			return err
		},
		func(ctx context.Context) error {
			var err error

			carsWith4OrMorePictures, err = s.repository.ItemsWithPicturesCount(ctx, typicalPicturesInList)

			return err
		},
		func(ctx context.Context) error {
			var err error

			itemsWithBeginYear, err = s.repository.Count(ctx, query.ItemListOptions{
				HasBeginYear: true,
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			itemsWithBeginAndEndYears, err = s.repository.Count(ctx, query.ItemListOptions{
				HasBeginYear: true,
				HasEndYear:   true,
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			itemsWithBeginAndEndYearsAndMonths, err = s.repository.Count(ctx, query.ItemListOptions{
				HasBeginYear:  true,
				HasEndYear:    true,
				HasBeginMonth: true,
				HasEndMonth:   true,
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			brandsWithLogo, err = s.repository.Count(ctx, query.ItemListOptions{
				HasLogo: true,
				TypeID:  []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			totalPictures, err = s.picturesRepository.Count(ctx, &query.PictureListOptions{})

			return err
		},
		func(ctx context.Context) error {
			var err error

			picturesWithCopyrights, err = s.picturesRepository.Count(ctx, &query.PictureListOptions{
				HasCopyrights: true,
			})

			return err
		},
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.StatsResponse{
		Values: []*pb.StatsValue{
			{
				Name:  "moder/statistics/photos-with-copyrights",
				Total: int32(totalPictures),          //nolint: gosec
				Value: int32(picturesWithCopyrights), //nolint: gosec
			},
			{
				Name:  "moder/statistics/vehicles-with-4-or-more-photos",
				Total: int32(totalCars), //nolint: gosec
				Value: carsWith4OrMorePictures,
			},
			{
				Name:  "moder/statistics/specifications-values",
				Total: int32(totalCars) * totalCarAttrs, //nolint: gosec
				Value: carAttrsValues,
			},
			{
				Name:  "moder/statistics/brand-logos",
				Total: int32(totalBrands),    //nolint: gosec
				Value: int32(brandsWithLogo), //nolint: gosec
			},
			{
				Name:  "moder/statistics/from-years",
				Total: int32(totalCars),          //nolint: gosec
				Value: int32(itemsWithBeginYear), //nolint: gosec
			},
			{
				Name:  "moder/statistics/from-and-to-years",
				Total: int32(totalCars),                 //nolint: gosec
				Value: int32(itemsWithBeginAndEndYears), //nolint: gosec
			},
			{
				Name:  "moder/statistics/from-and-to-years-and-months",
				Total: int32(totalCars),                          //nolint: gosec
				Value: int32(itemsWithBeginAndEndYearsAndMonths), //nolint: gosec
			},
		},
	}, nil
}

func validateItemLanguage(language *pb.ItemLanguage) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	// language='xx' is the non-language-specific name that lives in item.name (see
	// items.Repository.setItemLanguageName, called only by CreateItem/UpdateItem alongside the
	// item.name write) - editing it here would desync the two without anything else noticing.
	if language.GetLanguage() == schema.DefaultLanguageCode {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       itemLanguageLanguageField,
			Description: "invalid language",
		})
	}

	nameInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{
			&validation.StringTrimFilter{},
			&validation.StringSingleSpaces{},
		},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: schema.ItemLanguageNameMaxLength},
		},
	}

	language.Name, problems, err = nameInputFilter.IsValidString(language.GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       itemLanguageNameField,
			Description: fv,
		})
	}

	textInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: items.ItemLanguageTextMaxLength},
		},
	}

	language.Text, problems, err = textInputFilter.IsValidString(language.GetText())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "text",
			Description: fv,
		})
	}

	fullTextInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: items.ItemLanguageFullTextMaxLength},
		},
	}

	language.FullText, problems, err = fullTextInputFilter.IsValidString(language.GetFullText())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "full_text",
			Description: fv,
		})
	}

	return result, nil
}

func validateItemParentLanguage(language *pb.ItemParentLanguage) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	nameInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{
			&validation.StringTrimFilter{},
			&validation.StringSingleSpaces{},
		},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: schema.ItemLanguageNameMaxLength},
		},
	}

	language.Name, problems, err = nameInputFilter.IsValidString(language.GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       itemParentLanguageNameField,
			Description: fv,
		})
	}

	return result, nil
}

func (s *Server) SetItemParentLanguage(
	ctx context.Context,
	in *pb.ItemParentLanguage,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemParentLanguage(in)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	err = s.itemParentLanguage.SetItemParentLanguage(
		ctx,
		in.GetParentId(),
		in.GetItemId(),
		in.GetLanguage(),
		in.GetName(),
		false,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetBrandNewItems(
	ctx context.Context,
	in *pb.NewItemsRequest,
) (*pb.NewItemsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	const (
		// The frontend popover shows at most 10 and a "and others" suffix once there's more -
		// fetching one extra row is enough to know that without pulling a whole page of rows it
		// will never display.
		newItemsLimit = 11
		daysLimit     = 7
	)

	lang := in.GetLanguage()

	brand, err := s.repository.Item(ctx, &query.ItemListOptions{
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemID:   in.GetItemId(),
		Language: lang,
	}, &items.ItemFields{Logo: true})
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	extractedBrand, err := s.extractor.Extract(
		ctx,
		brand,
		&pb.ItemFields{Brandicon: true},
		lang,
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	carList, _, err := s.repository.List(ctx, &query.ItemListOptions{
		Language: lang,
		ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
			ItemsByParentID: &query.ItemListOptions{
				Language: lang,
				ItemID:   brand.ID,
			},
		},
		CreatedInDays: daysLimit,
		Limit:         newItemsLimit,
	}, &items.ItemFields{
		NameHTML: true,
	}, items.OrderByCreatedAt, false)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	extractedItems := make([]*pb.Item, 0, len(carList))

	for _, car := range carList {
		extractedItem, err := s.extractor.Extract(
			ctx,
			car,
			&pb.ItemFields{NameHtml: true},
			lang,
			userCtx,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extractedItems = append(extractedItems, extractedItem)
	}

	return &pb.NewItemsResponse{
		Brand: extractedBrand,
		Items: extractedItems,
	}, nil
}

func (s *Server) GetNewItems(
	ctx context.Context,
	in *pb.NewItemsRequest,
) (*pb.NewItemsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	const (
		// The frontend popover shows at most 10 and a "and others" suffix once there's more -
		// fetching one extra row is enough to know that without pulling a whole page of rows it
		// will never display.
		newItemsLimit = 11
		daysLimit     = 7
	)

	lang := in.GetLanguage()

	category, err := s.repository.Item(ctx, &query.ItemListOptions{
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
		ItemID:   in.GetItemId(),
		Language: lang,
	}, nil)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	extractedBrand, err := s.extractor.Extract(ctx, category, nil, lang, userCtx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	carList, _, err := s.repository.List(ctx, &query.ItemListOptions{
		Language: lang,
		TypeID: []schema.ItemTableItemTypeID{
			schema.ItemTableItemTypeIDVehicle,
			schema.ItemTableItemTypeIDEngine,
		},
		ItemParentParent: &query.ItemParentListOptions{
			LinkedInDays: daysLimit,
			ItemParentCacheAncestorByParentID: &query.ItemParentCacheListOptions{
				ItemsByParentID: &query.ItemListOptions{
					TypeID: []schema.ItemTableItemTypeID{
						schema.ItemTableItemTypeIDCategory,
						schema.ItemTableItemTypeIDFactory,
					},
					ItemID: category.ID,
				},
			},
		},
		Limit: newItemsLimit,
	}, &items.ItemFields{
		NameHTML: true,
	}, items.OrderByItemParentParentTimestamp, false)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	extractedItems := make([]*pb.Item, 0, len(carList))

	for _, car := range carList {
		extractedItem, err := s.extractor.Extract(
			ctx,
			car,
			&pb.ItemFields{NameHtml: true},
			lang,
			userCtx,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extractedItems = append(extractedItems, extractedItem)
	}

	return &pb.NewItemsResponse{
		Brand: extractedBrand,
		Items: extractedItems,
	}, nil
}

func (s *Server) CreateItemParent(
	ctx context.Context,
	in *pb.ItemParent,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemParent(in)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: audit.DefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	parentItem, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: audit.DefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.repository.CreateItemParent(
		ctx, item.ID, parentItem.ID, itemmapper.ParentTypeFromProto(in.GetType()), in.GetCatname(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.repository.UpdateInheritance(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.repository.RefreshItemVehicleTypeInheritanceFromParents(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.attrsRepository.UpdateActualValues(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	parentNameText, err := s.formatItemNameText(parentItem, audit.DefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, audit.Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"%s выбран как родительский для %s",
			parentNameText,
			itemNameText,
		),
		Items: []int64{item.ID, parentItem.ID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.notifyItemParentSubscribers(
		ctx,
		item,
		parentItem,
		userCtx.UserID,
		"pm/user-%s-adds-item-%s-%s-to-item-%s-%s",
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) UpdateItemParent(
	ctx context.Context,
	in *pb.ItemParent,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateItemParent(in)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	_, err = s.repository.UpdateItemParent(
		ctx,
		in.GetItemId(),
		in.GetParentId(),
		itemmapper.ParentTypeFromProto(in.GetType()),
		in.GetCatname(),
		false,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteItemParent(
	ctx context.Context,
	in *pb.DeleteItemParentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: audit.DefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	parent, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: audit.DefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.RemoveItemParent(ctx, item.ID, parent.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.attrsRepository.UpdateActualValues(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	parentNameText, err := s.formatItemNameText(parent, audit.DefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, audit.Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"%s перестал быть родительским автомобилем для %s",
			parentNameText,
			itemNameText,
		),
		Items: []int64{item.ID, parent.ID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.notifyItemParentSubscribers(
		ctx,
		item,
		parent,
		userCtx.UserID,
		"pm/user-%s-removed-item-%s-%s-from-item-%s-%s",
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) MoveItemParent(
	ctx context.Context,
	in *pb.MoveItemParentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.MoveItemParent(
		ctx,
		in.GetItemId(),
		in.GetParentId(),
		in.GetDestParentId(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		item, err := s.repository.Item(
			ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: audit.DefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		oldParent, err := s.repository.Item(
			ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: audit.DefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		oldParentNameText, err := s.formatItemNameText(oldParent, audit.DefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newParent, err := s.repository.Item(
			ctx,
			&query.ItemListOptions{ItemID: in.GetDestParentId(), Language: audit.DefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newParentNameText, err := s.formatItemNameText(newParent, audit.DefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.events.Add(ctx, audit.Event{
			UserID: userCtx.UserID,
			Message: fmt.Sprintf(
				"%s перемещен из %s в %s",
				oldParentNameText,
				itemNameText,
				newParentNameText,
			),
			Items: []int64{item.ID, oldParent.ID, newParent.ID},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.repository.UpdateInheritance(ctx, item.ID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.attrsRepository.UpdateActualValues(ctx, item.ID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) RefreshInheritance(
	ctx context.Context, in *pb.RefreshInheritanceRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.UpdateInheritance(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.attrsRepository.UpdateActualValues(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) SetUserItemSubscription(
	ctx context.Context, in *pb.SetUserItemSubscriptionRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetSubscribed() {
		err = s.repository.UserItemSubscribe(ctx, in.GetItemId(), userCtx.UserID)
	} else {
		err = s.repository.UserItemUnsubscribe(ctx, in.GetItemId(), userCtx.UserID)
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetBrandSections(
	ctx context.Context, in *pb.GetBrandSectionsRequest,
) (*pb.BrandSections, error) {
	item, err := s.repository.Item(ctx, &query.ItemListOptions{
		ItemID:   in.GetItemId(),
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Language: in.GetLanguage(),
	}, nil)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	sections, err := s.brandSections(ctx, in.GetLanguage(), item.ID, item.Catname.String)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.BrandSections{
		Sections: sections,
	}, nil
}

type SectionPreset struct {
	Name       string
	CarTypeID  int64
	ItemTypeID []schema.ItemTableItemTypeID
	RouterLink []string
}

func (s *Server) GetItemParent(
	ctx context.Context,
	in *pb.ItemParentsRequest,
) (*pb.ItemParent, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	if !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	inOptions := in.GetOptions()

	options, err := querymapper.ItemParentListOptionsFromProto(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.ItemParentListOptions{}
	}

	if options.ItemID == 0 || options.ParentID == 0 {
		return nil, status.Error(codes.NotFound, "primary key is zero")
	}

	fields := itemmapper.ParentFieldsFromProto(in.GetFields())

	row, err := s.repository.ItemParent(ctx, &query.ItemParentListOptions{
		ItemID:   options.ItemID,
		ParentID: options.ParentID,
	}, fields)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res, err := s.itemParentExtractor.ExtractRow(
		ctx,
		row,
		in.GetFields(),
		in.GetLanguage(),
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return res, nil
}

func (s *Server) GetItemParents(
	ctx context.Context, in *pb.ItemParentsRequest,
) (*pb.ItemParents, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	inOptions := in.GetOptions()

	if inOptions.GetItemId() == 0 && inOptions.GetParentId() == 0 && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := querymapper.ItemParentListOptionsFromProto(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.ItemParentListOptions{}
	}

	options.Limit = in.GetLimit()
	options.Page = in.GetPage()

	order := items.ItemParentOrderByNone

	switch in.GetOrder() {
	case pb.ItemParentsRequest_NONE:
	case pb.ItemParentsRequest_CATEGORIES_FIRST:
		order = items.ItemParentOrderByCategoriesFirst
	case pb.ItemParentsRequest_AUTO:
		order = items.ItemParentOrderByAuto
	case pb.ItemParentsRequest_NAME:
		order = items.ItemParentOrderByName
	}

	fields := itemmapper.ParentFieldsFromProto(in.GetFields())

	rows, pages, err := s.repository.ItemParents(ctx, options, fields, order)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res, err := s.itemParentExtractor.ExtractRows(
		ctx,
		rows,
		in.GetFields(),
		in.GetLanguage(),
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	var paginator *pb.Pages
	if pages != nil {
		paginator = &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		}
	}

	return &pb.ItemParents{
		Items:     res,
		Paginator: paginator,
	}, nil
}

func (s *Server) GetItemOfDay(ctx context.Context, in *pb.ItemOfDayRequest) (*pb.ItemOfDay, error) {
	return s.itemOfDayCached.GetItemOfDay(ctx, in.GetLanguage())
}

func (s *Server) GetTopSpecsContributions(
	ctx context.Context, in *pb.TopSpecsContributionsRequest,
) (*pb.TopSpecsContributions, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	lang := in.GetLanguage()

	cacheKey := "API_INDEX_SPEC_CARS_8_" + lang
	success := false

	var res pb.TopSpecsContributions

	cacheItem, err := s.redis.Get(ctx, cacheKey).Bytes()
	if err != nil && !errors.Is(err, redis.Nil) {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err == nil {
		err = proto.Unmarshal(cacheItem, &res)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		success = true
	}

	if !success {
		fields := pb.ItemFields{
			NameHtml:    true,
			NameDefault: true,
			Description: true,
			HasText:     true,
			Design:      true,
			EngineVehicles: &pb.ItemsRequest{
				Fields: &pb.ItemFields{NameHtml: true, Route: true},
			},
			CanEditSpecs: true,
			SpecsRoute:   true,
			Route:        true,
			Categories: &pb.ItemsRequest{
				Fields: &pb.ItemFields{NameHtml: true},
			},
			Twins: &pb.ItemsRequest{},
			PreviewPictures: &pb.PreviewPicturesRequest{
				Pictures: &pb.PicturesRequest{
					Fields: &pb.PictureFields{ThumbMedium: true, NameText: true},
				},
				PerspectivePageId: 1,
			},
			ChildsCount:           true,
			AcceptedPicturesCount: true,
			SpecsContributors:     true,
		}

		cars, _, err := s.repository.List(ctx, &query.ItemListOptions{
			Language: lang,
			Limit:    topSpecsContributorsLimit,
			AttrsUserValues: &query.AttrsUserValueListOptions{
				UpdatedInDays: topSpecsContributorsInDays,
			},
			AttrsUserValuesCountGte: topSpecsContributorsValuesCountThreshold,
		}, itemmapper.FieldsFromProto(&fields), items.OrderByAttrsUserValuesUpdateDate, false)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extracted, err := s.extractor.ExtractRows(ctx, cars, &fields, lang, userCtx)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		res = pb.TopSpecsContributions{
			Items: extracted,
		}

		cacheBytes, err := proto.Marshal(&res)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.redis.Set(ctx, cacheKey, cacheBytes, topSpecsContributorsCacheDuration).Err()
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &res, nil
}

func (s *Server) GetPath(ctx context.Context, in *pb.PathRequest) (*pb.PathResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	lang := in.GetLanguage()

	var path []string
	if len(in.GetPath()) > 0 {
		path = strings.Split(in.GetPath(), "/")
	}

	// Only the last breadcrumb is rendered with its description (the category page prints it under
	// the heading), and whether that is the category itself or the deepest path node depends on
	// the request - hence two field sets, picked per query below.
	//
	// It has to be decided here, at the repository, and not at the extractor: the description is a
	// column the repository selects (a reference into text storage), and the extractor copies
	// row.Description through unconditionally without ever consulting its own Description flag.
	// Setting that flag on the extractor - which is what this code used to do - therefore fetched
	// nothing, and the description arrived empty on every category page.
	fields := &pb.ItemFields{NameHtml: true, NameText: true, NameOnly: true}
	fieldsWithDescription := &pb.ItemFields{Description: true, NameHtml: true, NameText: true, NameOnly: true}

	convertedFields := itemmapper.FieldsFromProto(fields)
	convertedFieldsWithDescription := itemmapper.FieldsFromProto(fieldsWithDescription)

	currentCategoryFields := convertedFields
	if len(path) == 0 {
		currentCategoryFields = convertedFieldsWithDescription
	}

	currentCategory, err := s.repository.Item(ctx, &query.ItemListOptions{
		Language: lang,
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
		Catname:  in.GetCatname(),
	}, currentCategoryFields)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, "category not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	type breadcrumb struct {
		Catname string
		Item    *items.Item
	}

	breadcrumbs := []breadcrumb{
		{
			Catname: "",
			Item:    currentCategory,
		},
	}

	parentCategory := currentCategory

	for {
		parentCategory, err = s.repository.Item(ctx, &query.ItemListOptions{
			Language: lang,
			TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
			ItemParentChild: &query.ItemParentListOptions{
				ItemID: parentCategory.ID,
			},
		}, convertedFields)
		if err != nil {
			if errors.Is(err, items.ErrItemNotFound) {
				break
			}

			return nil, status.Error(codes.Internal, err.Error())
		}

		breadcrumbs = append([]breadcrumb{{
			Catname: "",
			Item:    parentCategory,
		}}, breadcrumbs...)
	}

	currentCar := currentCategory

	for idx, pathNode := range path {
		nodeFields := convertedFields
		if idx == len(path)-1 {
			nodeFields = convertedFieldsWithDescription
		}

		currentCar, err = s.repository.Item(ctx, &query.ItemListOptions{
			Language: lang,
			ItemParentParent: &query.ItemParentListOptions{
				ParentID: currentCar.ID,
				Catname:  pathNode,
			},
		}, nodeFields)
		if err != nil {
			if errors.Is(err, items.ErrItemNotFound) {
				return nil, status.Error(codes.NotFound, "path node not found")
			}

			return nil, status.Error(codes.Internal, err.Error())
		}

		breadcrumbs = append(breadcrumbs, breadcrumb{
			Catname: pathNode,
			Item:    currentCar,
		})
	}

	// The whole path through the extractor in one call, rather than a crumb at a time. Extract is
	// a single-row wrapper around ExtractRows (see item-extractor.go), whose preloads are batched
	// per call - so extracting in a loop ran every one of those preloads once per breadcrumb, N
	// times the queries for the same answer, on a path several levels deep.
	rows := make([]*items.Item, 0, len(breadcrumbs))
	for _, crumb := range breadcrumbs {
		rows = append(rows, crumb.Item)
	}

	// One field set for every crumb: which of them carries a description was settled when the rows
	// were fetched, and the extractor passes through whatever the row holds.
	extracted, err := s.extractor.ExtractRows(ctx, rows, fieldsWithDescription, lang, userCtx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.PathItem, 0, len(breadcrumbs))

	var parentID int64

	for idx, crumb := range breadcrumbs {
		res = append(res, &pb.PathItem{
			Catname:  crumb.Catname,
			ParentId: parentID,
			Item:     extracted[idx],
		})
		parentID = crumb.Item.ID
	}

	return &pb.PathResponse{
		Path: res,
	}, nil
}

func (s *Server) GetAlpha(ctx context.Context, _ *emptypb.Empty) (*pb.AlphaResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	if !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	chars, err := s.repository.FirstCharacters(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return categorizeFirstChars(chars), nil
}

var (
	firstCharsNumbersRegexp  = regexp.MustCompile(`^[~>/§("0-9-]$`)
	firstCharsLatinRegexp    = regexp.MustCompile(`^[A-Za-z]$`)
	firstCharsCyrillicRegexp = regexp.MustCompile(`^\p{Cyrillic}$`)
	firstCharsHanRegexp      = regexp.MustCompile(`^\p{Han}$`)
)

// categorizeFirstChars buckets a set of first characters into the same
// numbers/latin/cyrillic/han/other groups used by the alphabetical filter UI.
func categorizeFirstChars(chars []string) *pb.AlphaResponse {
	res := pb.AlphaResponse{
		Numbers:  make([]string, 0),
		Latin:    make([]string, 0),
		Cyrillic: make([]string, 0),
		Han:      make([]string, 0),
		Other:    make([]string, 0),
	}

	for _, char := range chars {
		switch {
		case firstCharsHanRegexp.MatchString(char):
			res.Han = append(res.Han, char)
		case firstCharsCyrillicRegexp.MatchString(char):
			res.Cyrillic = append(res.Cyrillic, char)
		case firstCharsNumbersRegexp.MatchString(char):
			res.Numbers = append(res.Numbers, char)
		case firstCharsLatinRegexp.MatchString(char):
			res.Latin = append(res.Latin, char)
		default:
			res.Other = append(res.Other, char)
		}
	}

	return &res
}

func (s *Server) CreateItem(ctx context.Context, in *pb.Item) (*pb.ItemID, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	in.Id = 0

	InvalidParams, err := validateItem(ctx, in, s.repository, nil, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	itemTypeID := in.GetItemTypeId()

	filter := validation.StringSanitizeFilename{}

	set := schema.ItemRow{
		ItemTypeID:       itemmapper.TypeFromProto(itemTypeID),
		IsConcept:        in.GetIsConcept(),
		IsConceptInherit: in.GetIsConceptInherit(),
		SpecInherit:      in.GetSpecInherit(),
		SpecID: sql.NullInt32{
			Valid: in.GetSpecId() > 0,
			Int32: in.GetSpecId(),
		},
		CreatedAt: sql.NullTime{Valid: true, Time: time.Now()},
		FullName: sql.NullString{
			Valid:  in.GetFullName() != "",
			String: in.GetFullName(),
		},
		Body: in.GetBody(),
		BeginYear: sql.NullInt32{
			Valid: in.GetBeginYear() > 0,
			Int32: in.GetBeginYear(),
		},
		BeginMonth: sql.NullInt16{
			Valid: in.GetBeginMonth() > 0,
			Int16: int16(in.GetBeginMonth()), //nolint: gosec
		},
		EndYear: sql.NullInt32{
			Valid: in.GetEndYear() > 0,
			Int32: in.GetEndYear(),
		},
		EndMonth: sql.NullInt16{
			Valid: in.GetEndMonth() > 0,
			Int16: int16(in.GetEndMonth()), //nolint: gosec
		},
		Today: sql.NullBool{
			Valid: in.GetToday() != nil,
			Bool:  in.GetToday().GetValue(),
		},
		BeginModelYear: sql.NullInt32{
			Valid: in.GetBeginModelYear() > 0,
			Int32: in.GetBeginModelYear(),
		},
		EndModelYear: sql.NullInt32{
			Valid: in.GetEndModelYear() > 0,
			Int32: in.GetEndModelYear(),
		},
		BeginModelYearFraction: sql.NullString{
			Valid:  in.GetBeginModelYearFraction() != "",
			String: in.GetBeginModelYearFraction(),
		},
		EndModelYearFraction: sql.NullString{
			Valid:  in.GetEndModelYearFraction() != "",
			String: in.GetEndModelYearFraction(),
		},
		ProducedExactly: in.GetProducedExactly(),
		Produced: sql.NullInt32{
			Valid: in.GetProduced() != nil,
			Int32: in.GetProduced().GetValue(),
		},
		Catname: sql.NullString{
			Valid:  in.GetCatname() != "",
			String: filter.FilterString(in.GetCatname()),
		},
		IsGroup:       in.GetIsGroup(),
		EngineInherit: in.GetEngineInherit(),
		EngineItemID: sql.NullInt64{
			Valid: in.GetEngineItemId() > 0,
			Int64: in.GetEngineItemId(),
		},
	}

	ctx = context.WithoutCancel(ctx)

	itemID, err := s.repository.CreateItem(ctx, set, in.GetName(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if location := in.GetLocation(); location != nil {
		point, err := geom.NewPoint(geom.XY).SetCoords(geom.Coord{location.GetLongitude(), location.GetLatitude()})
		if err != nil {
			return nil, status.Error(codes.InvalidArgument, err.Error())
		}

		point.SetSRID(schema.SRID)

		err = s.repository.SetItemLocation(ctx, itemID, point)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.attrsRepository.UpdateInheritedValues(ctx, itemID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	item, err := s.repository.Item(
		ctx,
		&query.ItemListOptions{ItemID: itemID},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, audit.Event{
		UserID:  userCtx.UserID,
		Message: "Создан новый автомобиль " + html.EscapeString(itemNameText),
		Items:   []int64{itemID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if set.ItemTypeID == schema.ItemTableItemTypeIDPerson {
		if err = s.complianceRepository.RecordNameHitIfMatched(ctx, in.GetName()); err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &pb.ItemID{Id: itemID}, nil
}

const (
	suppressAuthorReferenceMaxLength = 255
	suppressAuthorNoteMaxLength      = 4000
)

// SuppressAuthor is the one-click GDPR erasure/objection action on a person item's moderator
// card: unlink it from every picture it is credited AUTHOR of, drop any pending EXIF author
// suggestions for it, and add it to the suppression list so the name is not silently reintroduced
// (see compliance.Repository and warnIfGdprObjection). RoleModer, same as the rest of this
// tooling: moderators are the ones who run into a suppressed name day to day.
func (s *Server) SuppressAuthor( //nolint: maintidx
	ctx context.Context, in *pb.SuppressAuthorRequest,
) (*pb.SuppressAuthorResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	if in.GetItemId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid item_id")
	}

	referenceFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Min: 0, Max: suppressAuthorReferenceMaxLength},
		},
	}

	reference, problems, err := referenceFilter.IsValidString(in.GetReference())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	fv := make([]*errdetails.BadRequest_FieldViolation, 0)
	for _, p := range problems {
		fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "reference", Description: p})
	}

	noteFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: suppressAuthorNoteMaxLength},
		},
	}

	note, problems, err := noteFilter.IsValidString(in.GetNote())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	for _, p := range problems {
		fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "note", Description: p})
	}

	if len(fv) > 0 {
		return nil, grpcerrors.FieldViolations(fv)
	}

	ctx = context.WithoutCancel(ctx)

	item, err := s.repository.Item(
		ctx,
		&query.ItemListOptions{ItemID: in.GetItemId(), Language: audit.DefaultLanguage},
		&items.ItemFields{NameOnly: true},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if item.ItemTypeID != schema.ItemTableItemTypeIDPerson {
		return nil, status.Error(codes.InvalidArgument, "item is not a person")
	}

	// Every localized spelling of the name, not just the one language.NameOnly resolves to - a
	// EXIF/IPTC/XMP blob or a freeform copyrights text may quote whichever language variant the
	// uploader/camera happened to write, and a future rename check (compliance.Repository.RecordNameHitIfMatched)
	// must catch a match in any of them too.
	itemLanguages, err := s.repository.ItemLanguageList(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	names := dedupeNames(append([]string{item.NameOnly}, itemLanguageNames(itemLanguages)...))

	// Free-text/metadata searches also need every given-name/family-name word order, not just the
	// order each name happens to be catalogued in - swapping them is common between EXIF
	// conventions, cultures, and hand-typed entries. Suppression-list rows themselves stay
	// unpermuted (compliance.FindExact already matches regardless of order via CanonicalizeName),
	// so the list doesn't fill up with redundant reordered display rows.
	searchNames := make([]string, 0, len(names)*2)
	for _, name := range names {
		searchNames = append(searchNames, compliance.NamePermutations(name)...)
	}

	searchNames = dedupeNames(searchNames)

	pictureIDs, err := s.picturesRepository.DeletePictureItemsByItemAndType(
		ctx, in.GetItemId(), schema.PictureItemTypeAuthor,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.picturesRepository.DeleteAuthorSuggestionsByItem(ctx, in.GetItemId()); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	objectionID, err := s.complianceRepository.Create(ctx, compliance.CreateOptions{
		Names:        names,
		Reference:    reference,
		ContactEmail: in.GetContactEmail(),
		Note:         note,
		SourceText:   in.GetSourceText(),
		AuthorUserID: userCtx.UserID,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.picturesRepository.SetAuthorSuppression(ctx, pictureIDs, objectionID); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	// Everything below is too slow to await inline: stripping EXIF is one S3 round trip plus an
	// ImageMagick decode per picture, and the copyrights-text/comment searches are ILIKE '%...%'
	// scans (leading wildcard, so no index helps) over tables that can hold millions of rows on a
	// busy site - either alone can already exceed any request timeout for an author credited on
	// thousands of pictures. ctx is already context.WithoutCancel'd above, so it survives this RPC
	// returning; each step logs its own progress/failures since there is no request left to report
	// them to. The trade-off: SuppressAuthorResponse can no longer report copyrights-text/comment
	// hits synchronously - a moderator finds them later on the suppression-list page.
	go func() {
		defer func() {
			if r := recover(); r != nil {
				logging.Errorf("SuppressAuthor: panic in background cleanup for objection %d: %v", objectionID, r)
			}
		}()

		if stripErr := s.picturesRepository.StripAuthorEXIF(ctx, pictureIDs, searchNames); stripErr != nil {
			logging.Warnf("SuppressAuthor: StripAuthorEXIF: %s", stripErr.Error())
		}

		copyrightsTextPictureIDs, findErr := s.picturesRepository.FindPicturesWithCopyrightsTextContaining(
			ctx,
			searchNames,
		)
		if findErr != nil {
			logging.Warnf("SuppressAuthor: FindPicturesWithCopyrightsTextContaining: %s", findErr.Error())
		} else if addErr := s.complianceRepository.AddCleanupCandidates(
			ctx,
			objectionID,
			schema.GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture,
			copyrightsTextPictureIDs,
		); addErr != nil {
			logging.Warnf("SuppressAuthor: AddCleanupCandidates(copyrights text): %s", addErr.Error())
		}

		// Comments are third-party speech (a well-meaning visitor may have credited the author in
		// a comment), not site-generated metadata - never edited/deleted automatically, only
		// surfaced as a candidate for a moderator to review via the existing report/moderation
		// tooling.
		commentIDs, findErr := s.commentsRepository.FindCommentsContaining(ctx, searchNames)
		if findErr != nil {
			logging.Warnf("SuppressAuthor: FindCommentsContaining: %s", findErr.Error())
		} else if addErr := s.complianceRepository.AddCleanupCandidates(
			ctx, objectionID, schema.GdprObjectionCleanupCandidateEntityTypeComment, commentIDs,
		); addErr != nil {
			logging.Warnf("SuppressAuthor: AddCleanupCandidates(comments): %s", addErr.Error())
		}

		logging.Infof(
			"SuppressAuthor: background cleanup for objection %d done, %d copyrights-text picture(s), %d comment(s)",
			objectionID, len(copyrightsTextPictureIDs), len(commentIDs),
		)
	}()

	err = s.events.Add(ctx, audit.Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"GDPR: автор %q (%d вариант(а) написания) отвязан от %d фото и внесён в suppression list (реф. %q)",
			item.NameOnly, len(names), len(pictureIDs), reference,
		),
		Items:    []int64{in.GetItemId()},
		Pictures: pictureIDs,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId()); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	// The item itself must go too: keeping it around, still fully catalogued and still findable
	// by name in person-search, is exactly what would let a moderator who doesn't know about this
	// case relink it as an author again. Last step, after the event log entry above, so that
	// entry's log_event_item row can still be created against a not-yet-deleted item.
	if err = s.repository.DeletePersonItem(ctx, in.GetItemId()); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.SuppressAuthorResponse{
		UnlinkedPicturesCount: int64(len(pictureIDs)),
		GdprObjectionId:       objectionID,
	}, nil
}

func itemLanguageNames(rows []items.ItemLanguage) []string {
	names := make([]string, 0, len(rows))
	for _, row := range rows {
		names = append(names, row.Name)
	}

	return names
}

// dedupeNames drops empty strings and case-insensitively duplicate names, keeping the first
// spelling seen.
func dedupeNames(names []string) []string {
	seen := make(map[string]struct{}, len(names))
	result := make([]string, 0, len(names))

	for _, name := range names {
		name = strings.TrimSpace(name)
		if name == "" {
			continue
		}

		key := strings.ToLower(name)
		if _, ok := seen[key]; ok {
			continue
		}

		seen[key] = struct{}{}

		result = append(result, name)
	}

	return result
}

func (s *Server) UpdateItem( //nolint: maintidx
	ctx context.Context, in *pb.UpdateItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetItem().GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "id is zero")
	}

	values := in.GetItem()
	mask := in.GetUpdateMask()

	item, err := s.repository.Item(
		ctx,
		&query.ItemListOptions{ItemID: values.GetId(), Language: audit.DefaultLanguage},
		&items.ItemFields{Meta: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	values.ItemTypeId = itemmapper.TypeToProto(item.ItemTypeID)
	oldData := item

	oldName, err := s.repository.LanguageName(ctx, item.ID, schema.DefaultLanguageCode)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	InvalidParams, err := validateItem(ctx, values, s.repository, mask.GetPaths(), userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	set := schema.ItemRow{
		ID: item.ID,
	}
	name := values.GetName()
	notifyMeta := util.Contains(mask.GetPaths(), itemNameField)

	if util.Contains(mask.GetPaths(), "full_name") {
		notifyMeta = true
		set.FullName = sql.NullString{
			String: values.GetFullName(),
			Valid:  len(values.GetFullName()) > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "body") {
		notifyMeta = true
		set.Body = values.GetBody()
	}

	if util.Contains(mask.GetPaths(), "begin_year") {
		notifyMeta = true
		set.BeginYear = sql.NullInt32{
			Int32: values.GetBeginYear(),
			Valid: values.GetBeginYear() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "begin_month") {
		notifyMeta = true
		set.BeginMonth = sql.NullInt16{
			Int16: int16(values.GetBeginMonth()), //nolint: gosec
			Valid: values.GetBeginMonth() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "end_year") {
		notifyMeta = true
		endYear := values.GetEndYear()
		set.EndYear = sql.NullInt32{
			Int32: endYear,
			Valid: endYear > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "end_month") {
		notifyMeta = true
		set.EndMonth = sql.NullInt16{
			Int16: int16(values.GetEndMonth()), //nolint: gosec
			Valid: values.GetEndMonth() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "today") {
		notifyMeta = true

		set.Today = sql.NullBool{
			Bool:  values.GetToday().GetValue(),
			Valid: values.GetToday() != nil,
		}
	}

	if util.Contains(mask.GetPaths(), "begin_model_year") {
		notifyMeta = true
		set.BeginModelYear = sql.NullInt32{
			Int32: values.GetBeginModelYear(),
			Valid: values.GetBeginModelYear() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "end_model_year") {
		notifyMeta = true
		set.EndModelYear = sql.NullInt32{
			Int32: values.GetEndModelYear(),
			Valid: values.GetEndModelYear() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "begin_model_year_fraction") {
		notifyMeta = true
		set.BeginModelYearFraction = sql.NullString{
			String: values.GetBeginModelYearFraction(),
			Valid:  len(values.GetBeginModelYearFraction()) > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "end_model_year_fraction") {
		notifyMeta = true
		set.EndModelYearFraction = sql.NullString{
			String: values.GetEndModelYearFraction(),
			Valid:  len(values.GetEndModelYearFraction()) > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "is_concept") {
		notifyMeta = true
		set.IsConcept = values.GetIsConcept()
	}

	if util.Contains(mask.GetPaths(), "is_concept_inherit") {
		notifyMeta = true
		set.IsConceptInherit = values.GetIsConceptInherit()
	}

	if util.Contains(mask.GetPaths(), "catname") {
		notifyMeta = true
		set.Catname = sql.NullString{
			String: values.GetCatname(),
			Valid:  len(values.GetCatname()) > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "produced") {
		notifyMeta = true
		set.Produced = sql.NullInt32{
			Int32: values.GetProduced().GetValue(),
			Valid: values.GetProduced() != nil,
		}
	}

	if util.Contains(mask.GetPaths(), "produced_exactly") {
		notifyMeta = true
		set.ProducedExactly = values.GetProducedExactly()
	}

	if util.Contains(mask.GetPaths(), "is_group") {
		notifyMeta = true
		set.IsGroup = values.GetIsGroup()
	}

	if util.Contains(mask.GetPaths(), "spec_inherit") {
		notifyMeta = true
		set.SpecInherit = values.GetSpecInherit()
	}

	if util.Contains(mask.GetPaths(), "spec_id") {
		notifyMeta = true
		set.SpecID = sql.NullInt32{
			Int32: values.GetSpecId(),
			Valid: values.GetSpecId() > 0,
		}
	}

	if util.Contains(mask.GetPaths(), "engine_inherit") {
		notifyMeta = true
		set.EngineInherit = values.GetEngineInherit()
	}

	if util.Contains(mask.GetPaths(), "engine_item_id") {
		notifyMeta = true
		set.EngineItemID = sql.NullInt64{
			Int64: values.GetEngineItemId(),
			Valid: values.GetEngineItemId() > 0,
		}
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.UpdateItem(ctx, set, name, mask.GetPaths(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if item.ItemTypeID == schema.ItemTableItemTypeIDPerson &&
		util.Contains(mask.GetPaths(), itemNameField) && name != "" {
		if err = s.complianceRepository.RecordNameHitIfMatched(ctx, name); err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if util.Contains(mask.GetPaths(), "location") {
		var point *geom.Point
		if location := values.GetLocation(); location != nil {
			point, err = geom.NewPoint(geom.XY).SetCoords(geom.Coord{location.GetLongitude(), location.GetLatitude()})
			if err != nil {
				return nil, status.Error(codes.InvalidArgument, err.Error())
			}

			point.SetSRID(schema.SRID)
		}

		err = s.repository.SetItemLocation(ctx, item.ID, point)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if notifyMeta {
		item, err = s.repository.Item(
			ctx,
			&query.ItemListOptions{ItemID: item.ID, Language: audit.DefaultLanguage},
			&items.ItemFields{NameText: true, Meta: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newData := item
		htmlChanges := make([]string, 0)

		newName, err := s.repository.LanguageName(ctx, item.ID, schema.DefaultLanguageCode)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		changes, err := s.buildChangesMessage(
			ctx,
			oldData.ItemRow,
			newData.ItemRow,
			oldName,
			newName,
			audit.DefaultLanguage,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		for _, line := range changes {
			htmlChanges = append(htmlChanges, html.EscapeString(line))
		}

		itemNameText, err := s.formatItemNameText(item, audit.DefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		message := "Редактирование мета-информации автомобиля " + itemNameText
		if len(htmlChanges) > 0 {
			message += "<p>" + strings.Join(htmlChanges, "<br />") + "</p>"
		}

		err = s.events.Add(ctx, audit.Event{
			UserID:  userCtx.UserID,
			Message: message,
			Items:   []int64{item.ID},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		user, err := s.usersRepository.User(
			ctx,
			&query.UserListOptions{ID: userCtx.UserID},
			users.UserFields{},
			users.OrderByNone,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.notifyItemSubscribers(
			ctx, []int64{item.ID}, user.ID, "pm/user-%s-edited-vehicle-meta-data-%s-%s-%s",
			func(uri *url.URL, lang string) (map[string]interface{}, error) {
				changes, err := s.buildChangesMessage(ctx, oldData.ItemRow, newData.ItemRow, oldName, newName, lang)
				if err != nil {
					return nil, err
				}

				changesStr := ""
				if len(changes) > 0 {
					changesStr = strings.Join(changes, "\n")
				}

				itemNameText, err := s.formatItemNameText(item, lang)
				if err != nil {
					return nil, err
				}

				return map[string]interface{}{
					messageUserURL:      frontend.UserURL(uri, user.ID, user.Identity),
					messageItemName:     itemNameText,
					messageItemModerURL: frontend.ItemModerURL(uri, item.ID),
					"Changes":           changesStr,
				}, nil
			},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, item.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetTree(ctx context.Context, in *pb.GetTreeRequest) (*pb.TreeItem, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "id is zero")
	}

	item, err := s.repository.Item(ctx, &query.ItemListOptions{ItemID: in.GetId()}, nil)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	res, err := s.carTreeWalk(ctx, item, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return res, nil
}

func (s *Server) GetVehicleTypes(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.VehicleTypeItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.catalogue.VehicleTypesTree(ctx, 0)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.VehicleTypeItems{
		Items: rows,
	}, nil
}

func (s *Server) GetBrandVehicleTypes(
	ctx context.Context,
	in *pb.GetBrandVehicleTypesRequest,
) (*pb.BrandVehicleTypeItems, error) {
	rows, err := s.catalogue.BrandVehicleTypes(ctx, in.GetBrandId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.BrandVehicleTypeItems{
		Items: rows,
	}, nil
}

func (s *Server) GetSpecs(ctx context.Context, _ *emptypb.Empty) (*pb.SpecsItems, error) {
	rows, err := s.catalogue.Specs(ctx, 0)
	if err != nil {
		return nil, err
	}

	return &pb.SpecsItems{ //nolint:exhaustruct
		Items: rows,
	}, nil
}

func (s *Server) GetBrandIcons(context.Context, *emptypb.Empty) (*pb.BrandIcons, error) {
	if len(s.fileStorageConfig.S3.Endpoint) == 0 {
		return nil, errNoEndpointProvided
	}

	parsedURL, err := url.Parse(s.fileStorageConfig.S3.Endpoint)
	if err != nil {
		return nil, err
	}

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandssprite.ImageFilename
	imageURL := parsedURL.String()

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandssprite.CSSFilename
	cssURL := parsedURL.String()

	return &pb.BrandIcons{ //nolint:exhaustruct
		Image: imageURL,
		Css:   cssURL,
	}, nil
}

func (s *Server) formatterOptions(row *items.Item) items.ItemNameFormatterOptions {
	return items.ItemNameFormatterOptions{
		BeginModelYear:         util.NullInt32ToScalar(row.BeginModelYear),
		EndModelYear:           util.NullInt32ToScalar(row.EndModelYear),
		BeginModelYearFraction: util.NullStringToString(row.BeginModelYearFraction),
		EndModelYearFraction:   util.NullStringToString(row.EndModelYearFraction),
		Spec:                   row.SpecShortName,
		SpecFull:               row.SpecName,
		Body:                   row.Body,
		Name:                   row.NameOnly,
		BeginYear:              util.NullInt32ToScalar(row.BeginYear),
		EndYear:                util.NullInt32ToScalar(row.EndYear),
		Today:                  util.NullBoolToBoolPtr(row.Today),
		BeginMonth:             util.NullInt16ToScalar(row.BeginMonth),
		EndMonth:               util.NullInt16ToScalar(row.EndMonth),
	}
}

func (s *Server) formatItemNameText(row *items.Item, lang string) (string, error) {
	if row == nil {
		return "", nil
	}

	nameFormatter := items.NewItemNameFormatter(s.i18n)

	return nameFormatter.FormatText(s.formatterOptions(row), lang)
}

func (s *Server) formatItemNameHTML(row *items.Item, lang string) (string, error) {
	if row == nil {
		return "", nil
	}

	nameFormatter := items.NewItemNameFormatter(s.i18n)

	return nameFormatter.FormatHTML(s.formatterOptions(row), lang)
}

func (s *Server) notifyItemParentSubscribers(
	ctx context.Context, item, parent *items.Item, userID int64, messageID string,
) error {
	falseRef := false

	subscribers, _, err := s.usersRepository.Users(ctx, &query.UserListOptions{
		Deleted: &falseRef,
		ItemSubscribe: &query.UserItemSubscribeListOptions{
			ItemIDs: []int64{item.ID, parent.ID},
		},
		ExcludeIDs: []int64{userID},
	}, users.UserFields{}, users.OrderByNone)
	if err != nil {
		return err
	}

	author, err := s.usersRepository.User(
		ctx,
		&query.UserListOptions{ID: userID},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		return err
	}

	for _, subscriber := range subscribers {
		uri, err := s.hostManager.URIByLanguage(subscriber.Language)
		if err != nil {
			return err
		}

		itemNameText, err := s.formatItemNameText(item, subscriber.Language)
		if err != nil {
			return err
		}

		parentNameText, err := s.formatItemNameText(parent, subscriber.Language)
		if err != nil {
			return err
		}

		err = s.messagingRepository.CreateMessageFromTemplate(
			ctx,
			0,
			subscriber.ID,
			messageID,
			map[string]interface{}{
				messageUserURL:       frontend.UserURL(uri, author.ID, author.Identity),
				messageItemName:      itemNameText,
				messageItemModerURL:  frontend.ItemModerURL(uri, item.ID),
				"ParentItemName":     parentNameText,
				"ParentItemModerURL": frontend.ItemModerURL(uri, parent.ID),
			},
			subscriber.Language,
		)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Server) notifyItemSubscribers(
	ctx context.Context, itemIDs []int64, excludeUserID int64, messageID string,
	templateData func(*url.URL, string) (map[string]interface{}, error),
) error {
	falseRef := false

	subscribers, _, err := s.usersRepository.Users(ctx, &query.UserListOptions{
		Deleted: &falseRef,
		ItemSubscribe: &query.UserItemSubscribeListOptions{
			ItemIDs: itemIDs,
		},
		ExcludeIDs: []int64{excludeUserID},
	}, users.UserFields{}, users.OrderByNone)
	if err != nil {
		return err
	}

	for _, subscriber := range subscribers {
		uri, err := s.hostManager.URIByLanguage(subscriber.Language)
		if err != nil {
			return err
		}

		data, err := templateData(uri, subscriber.Language)
		if err != nil {
			return err
		}

		err = s.messagingRepository.CreateMessageFromTemplate(
			ctx,
			0,
			subscriber.ID,
			messageID,
			data,
			subscriber.Language,
		)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Server) brandSections(
	ctx context.Context, lang string, brandID int64, brandCatname string,
) ([]*pb.BrandSection, error) {
	sections, lookups := s.prepareCarSections(lang, brandID, brandCatname)
	for index, lookup := range lookups {
		lookups[index] = func(ctx context.Context) error {
			if err := lookup(ctx); err != nil {
				return fmt.Errorf("carSections(): %w", err)
			}

			return nil
		}
	}

	other, otherLookups := s.prepareOtherGroupLookups(brandID)
	for _, lookup := range otherLookups {
		lookups = append(lookups, func(ctx context.Context) error {
			if err := lookup(ctx); err != nil {
				return fmt.Errorf("otherGroups(): %w", err)
			}

			return nil
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	otherGroups, err := s.formatOtherGroups(other, brandCatname, lang)
	if err != nil {
		return nil, fmt.Errorf("otherGroups(): %w", err)
	}

	return append(
		sections,
		&pb.BrandSection{
			Name:       "Other",
			RouterLink: nil,
			Groups:     otherGroups,
		},
	), nil
}

type brandOtherGroups struct {
	hasConcepts bool
	counts      pictures.BrandPictureCounts
}

func (s *Server) prepareOtherGroupLookups(brandID int64) (*brandOtherGroups, []func(context.Context) error) {
	data := &brandOtherGroups{}
	lookups := []func(context.Context) error{
		func(ctx context.Context) error {
			var err error

			data.hasConcepts, err = s.repository.Exists(ctx, query.ItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: brandID},
				IsConcept:               true,
			})

			return err
		},
		func(ctx context.Context) error {
			var err error

			data.counts, err = s.picturesRepository.BrandCounts(ctx, brandID)

			return err
		},
	}

	return data, lookups
}

func (s *Server) otherGroups(
	ctx context.Context, brandID int64, brandCatname string, lang string,
) ([]*pb.BrandSection, error) {
	data, lookups := s.prepareOtherGroupLookups(brandID)
	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	return s.formatOtherGroups(data, brandCatname, lang)
}

func (s *Server) formatOtherGroups(
	data *brandOtherGroups, brandCatname string, lang string,
) ([]*pb.BrandSection, error) {
	var groups []*pb.BrandSection

	localizer := s.i18n.Localizer(lang)

	if data.hasConcepts {
		translated, err := localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{
				ID: "concepts and prototypes",
			},
		})
		if err != nil {
			return nil, err
		}

		groups = append(groups, &pb.BrandSection{
			RouterLink: frontend.BrandConceptsRoute(brandCatname),
			Name:       translated,
		})
	}

	groupTypes := []struct {
		Count   int
		Catname string
		Name    string
	}{
		{Count: data.counts.Logotypes, Catname: frontend.BrandLogotypes, Name: "logotypes"},
		{Count: data.counts.Mixed, Catname: frontend.BrandMixed, Name: "mixed"},
		{Count: data.counts.Other, Catname: frontend.BrandOther, Name: "unsorted"},
	}

	for _, groupType := range groupTypes {
		picturesCount := groupType.Count

		if picturesCount > 0 {
			translated, err := localizer.Localize(&i18n.LocalizeConfig{
				DefaultMessage: &i18n.Message{
					ID: groupType.Name,
				},
			})
			if err != nil {
				return nil, err
			}

			groups = append(groups, &pb.BrandSection{
				RouterLink: frontend.BrandGroupRoute(brandCatname, groupType.Catname),
				Name:       translated,
				Count:      int32(picturesCount), //nolint: gosec
			})
		}
	}

	return groups, nil
}

func (s *Server) carSections(
	ctx context.Context, lang string, brandID int64, brandCatname string,
) ([]*pb.BrandSection, error) {
	sections, lookups := s.prepareCarSections(lang, brandID, brandCatname)
	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	return sections, nil
}

// Return leaf lookups so brandSections can share one limit across all sections,
// the concept check and picture counts, without creating nested parallel groups.
func (s *Server) prepareCarSections(
	lang string, brandID int64, brandCatname string,
) ([]*pb.BrandSection, []func(context.Context) error) {
	sectionsPresets := []SectionPreset{
		{
			ItemTypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDBrand,
			},
		},
		{
			Name:      "catalogue/section/moto",
			CarTypeID: items.VehicleTypeIDMoto,
			ItemTypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDBrand,
			},
		},
		{
			Name:      "catalogue/section/buses",
			CarTypeID: items.VehicleTypeIDBus,
			ItemTypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDBrand,
			},
		},
		{
			Name:      "catalogue/section/trucks",
			CarTypeID: items.VehicleTypeIDTruck,
			ItemTypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDBrand,
			},
		},
		{
			Name:      "catalogue/section/tractors",
			CarTypeID: items.VehicleTypeIDTractor,
			ItemTypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDBrand,
			},
		},
		{
			Name:       "catalogue/section/engines",
			ItemTypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDEngine},
			RouterLink: frontend.BrandEnginesRoute(brandCatname),
		},
	}

	sections := make([]*pb.BrandSection, len(sectionsPresets))
	lookups := make([]func(context.Context) error, 0, len(sectionsPresets))

	for index, preset := range sectionsPresets {
		sections[index] = &pb.BrandSection{Name: preset.Name, RouterLink: preset.RouterLink}

		lookups = append(lookups, func(ctx context.Context) error {
			sectionGroups, err := s.carSectionGroups(ctx, lang, brandID, brandCatname, preset)
			if err != nil {
				return fmt.Errorf("carSectionGroups(): %w", err)
			}

			sections[index].Groups = sectionGroups

			return nil
		})
	}

	return sections, lookups
}

func (s *Server) carSectionGroups(
	ctx context.Context,
	lang string,
	brandID int64,
	brandCatname string,
	section SectionPreset,
) ([]*pb.BrandSection, error) {
	childItems := &query.ItemListOptions{
		TypeID:                section.ItemTypeID,
		IsNotConcept:          true,
		VehicleTypeAncestorID: section.CarTypeID,
	}

	if section.CarTypeID == 0 {
		childItems.ExcludeVehicleTypeAncestorID = []int64{
			items.VehicleTypeIDMoto, items.VehicleTypeIDTractor, items.VehicleTypeIDTruck, items.VehicleTypeIDBus,
		}
	}

	rows, _, err := s.repository.ItemParents(ctx, &query.ItemParentListOptions{
		ParentID:   brandID,
		ChildItems: childItems,
		Language:   lang,
	}, items.ItemParentFields{Name: true}, items.ItemParentOrderByName)
	if err != nil {
		return nil, fmt.Errorf("ItemParents(): %w", err)
	}

	groups := make([]*pb.BrandSection, 0, len(rows))

	for _, row := range rows {
		groups = append(groups, &pb.BrandSection{
			RouterLink: frontend.BrandItemRoute(brandCatname, row.Catname),
			Name:       row.Name,
		})
	}

	return groups, nil
}

func (s *Server) translateBool(value bool, lang string) (string, error) {
	localizer := s.i18n.Localizer(lang)

	msg := "moder/vehicle/changes/boolean/false"
	if value {
		msg = "moder/vehicle/changes/boolean/true"
	}

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
	})
}

func (s *Server) translateNullBool(value sql.NullBool, lang string) (string, error) {
	if !value.Valid {
		return "", nil
	}

	return s.translateBool(value.Bool, lang)
}

func (s *Server) boolChange(
	oldValue, newValue bool,
	msg string,
	lang string,
) (string, error) {
	if oldValue == newValue {
		return "", nil
	}

	from, err := s.translateBool(oldValue, lang)
	if err != nil {
		return "", err
	}

	to, err := s.translateBool(newValue, lang)
	if err != nil {
		return "", err
	}

	localizer := s.i18n.Localizer(lang)

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
		TemplateData: map[string]interface{}{
			messageChangesFromField: from,
			messageChangesToField:   to,
		},
	})
}

func (s *Server) nullBoolChange(
	oldValue, newValue sql.NullBool,
	msg string,
	lang string,
) (string, error) {
	if oldValue.Valid != newValue.Valid ||
		oldValue.Valid && newValue.Valid && (oldValue.Bool != newValue.Bool) {
		from, err := s.translateNullBool(oldValue, lang)
		if err != nil {
			return "", err
		}

		to, err := s.translateNullBool(newValue, lang)
		if err != nil {
			return "", err
		}

		localizer := s.i18n.Localizer(lang)

		return localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{ID: msg},
			TemplateData: map[string]interface{}{
				messageChangesFromField: from,
				messageChangesToField:   to,
			},
		})
	}

	return "", nil
}

func (s *Server) nullInt16Change(
	oldValue, newValue sql.NullInt16,
	msg string,
	lang string,
) (string, error) {
	from := util.NullInt16ToScalar(oldValue)
	to := util.NullInt16ToScalar(newValue)

	if from == to {
		return "", nil
	}

	localizer := s.i18n.Localizer(lang)

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
		TemplateData: map[string]interface{}{
			messageChangesFromField: from,
			messageChangesToField:   to,
		},
	})
}

func (s *Server) nullInt32Change(
	oldValue, newValue sql.NullInt32,
	msg string,
	lang string,
) (string, error) {
	from := util.NullInt32ToScalar(oldValue)
	to := util.NullInt32ToScalar(newValue)

	if from == to {
		return "", nil
	}

	localizer := s.i18n.Localizer(lang)

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
		TemplateData: map[string]interface{}{
			messageChangesFromField: from,
			messageChangesToField:   to,
		},
	})
}

func (s *Server) nullStringChange(
	oldValue, newValue sql.NullString,
	msg string,
	lang string,
) (string, error) {
	from := util.NullStringToString(oldValue)
	to := util.NullStringToString(newValue)

	return s.stringChange(from, to, msg, lang)
}

func (s *Server) stringChange(
	oldValue, newValue string,
	msg string,
	lang string,
) (string, error) {
	if oldValue == newValue {
		return "", nil
	}

	localizer := s.i18n.Localizer(lang)

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
		TemplateData: map[string]interface{}{
			messageChangesFromField: oldValue,
			messageChangesToField:   newValue,
		},
	})
}

func (s *Server) buildChangesMessage( //nolint: maintidx
	ctx context.Context, oldData schema.ItemRow, newData schema.ItemRow, oldName, newName, lang string,
) ([]string, error) {
	changes := make([]string, 0)

	change, err := s.stringChange(
		oldName,
		newName,
		"moder/vehicle/changes/name-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullStringChange(
		oldData.Catname,
		newData.Catname,
		"moder/vehicle/changes/catname-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.stringChange(
		oldData.Body,
		newData.Body,
		"moder/vehicle/changes/body-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt32Change(oldData.BeginYear, newData.BeginYear,
		"moder/vehicle/changes/from/year-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt16Change(oldData.BeginMonth, newData.BeginMonth,
		"moder/vehicle/changes/from/month-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt32Change(
		oldData.EndYear,
		newData.EndYear,
		"moder/vehicle/changes/to/year-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt16Change(
		oldData.EndMonth,
		newData.EndMonth,
		"moder/vehicle/changes/to/month-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullBoolChange(
		oldData.Today,
		newData.Today,
		"moder/vehicle/changes/to/today-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt32Change(oldData.Produced, newData.Produced,
		"moder/vehicle/changes/produced/count-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.boolChange(oldData.ProducedExactly, newData.ProducedExactly,
		"moder/vehicle/changes/produced/exactly-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.boolChange(
		oldData.IsConcept,
		newData.IsConcept,
		"moder/vehicle/changes/is-concept-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.boolChange(oldData.IsConceptInherit, newData.IsConceptInherit,
		"moder/vehicle/changes/is-concept-inherit-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.boolChange(oldData.IsGroup, newData.IsGroup,
		"moder/vehicle/changes/is-group-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt32Change(oldData.BeginModelYear, newData.BeginModelYear,
		"moder/vehicle/changes/model-years/from-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullInt32Change(oldData.EndModelYear, newData.EndModelYear,
		"moder/vehicle/changes/model-years/to-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullStringChange(oldData.BeginModelYearFraction, newData.BeginModelYearFraction,
		"moder/vehicle/changes/model-years-fraction/from-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.nullStringChange(oldData.EndModelYearFraction, newData.EndModelYearFraction,
		"moder/vehicle/changes/model-years-fraction/to-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	if oldData.SpecID.Valid != newData.SpecID.Valid ||
		(oldData.SpecID.Valid && newData.SpecID.Valid && oldData.SpecID.Int32 != newData.SpecID.Int32) {
		from := ""
		to := ""

		if oldData.SpecID.Valid {
			spec, err := s.repository.Spec(ctx, oldData.SpecID.Int32)
			if err != nil {
				return nil, err
			}

			from = spec.ShortName
		}

		if newData.SpecID.Valid {
			spec, err := s.repository.Spec(ctx, newData.SpecID.Int32)
			if err != nil {
				return nil, err
			}

			to = spec.ShortName
		}

		localizer := s.i18n.Localizer(lang)

		change, err = localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{ID: "moder/vehicle/changes/spec-%s-%s"},
			TemplateData: map[string]interface{}{
				messageChangesFromField: from,
				messageChangesToField:   to,
			},
		})
		if err != nil {
			return nil, err
		}

		changes = append(changes, change)
	}

	change, err = s.boolChange(
		oldData.SpecInherit,
		newData.SpecInherit,
		"moder/vehicle/changes/spec-inherit-%s-%s",
		lang,
	)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	change, err = s.boolChange(oldData.EngineInherit, newData.EngineInherit,
		"moder/vehicle/changes/engine-inherit-%s-%s", lang)
	if err != nil {
		return nil, err
	}

	if len(change) > 0 {
		changes = append(changes, change)
	}

	if oldData.EngineItemID.Valid != newData.EngineItemID.Valid ||
		(oldData.EngineItemID.Valid &&
			newData.EngineItemID.Valid && oldData.EngineItemID.Int64 != newData.EngineItemID.Int64) {
		from := ""
		to := ""

		if oldData.EngineItemID.Valid && oldData.EngineItemID.Int64 > 0 {
			engine, err := s.repository.Item(ctx, &query.ItemListOptions{
				ItemID: oldData.EngineItemID.Int64,
			}, &items.ItemFields{NameOnly: true})
			if err != nil {
				return nil, err
			}

			from = engine.NameOnly
		}

		if newData.EngineItemID.Valid && newData.EngineItemID.Int64 > 0 {
			engine, err := s.repository.Item(ctx, &query.ItemListOptions{
				ItemID: newData.EngineItemID.Int64,
			}, &items.ItemFields{NameOnly: true})
			if err != nil {
				return nil, err
			}

			to = engine.NameOnly
		}

		localizer := s.i18n.Localizer(lang)

		change, err = localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{ID: "moder/vehicle/changes/engine-item-id-%s-%s"},
			TemplateData: map[string]interface{}{
				messageChangesFromField: from,
				messageChangesToField:   to,
			},
		})
		if err != nil {
			return nil, err
		}

		changes = append(changes, change)
	}

	// "vehicle_type_id": []string{"vehicle_type_id", "moder/vehicle/changes/car-type-%s-%s"},

	return changes, nil
}

func (s *Server) carTreeWalk(
	ctx context.Context, car *items.Item, lang string,
) (*pb.TreeItem, error) {
	root, err := s.carTreeNode(car, lang, schema.ItemParentTypeDefault)
	if err != nil {
		return nil, err
	}

	level := []*pb.TreeItem{root}
	for len(level) > 0 {
		if err := ctx.Err(); err != nil {
			return nil, err
		}

		parentIDs := make(map[int64]struct{}, len(level))
		for _, node := range level {
			parentIDs[node.GetId()] = struct{}{}
		}

		relations, _, err := s.repository.ItemParents(ctx, &query.ItemParentListOptions{
			ParentIDs: slices.Collect(maps.Keys(parentIDs)),
		}, items.ItemParentFields{}, items.ItemParentOrderByAuto)
		if err != nil {
			return nil, err
		}

		if len(relations) == 0 {
			break
		}

		children := make(map[int64][]*items.ItemParent)
		childIDs := make(map[int64]struct{})
		// Bucketing retains the automatic sibling order returned by ItemParents.
		for _, relation := range relations {
			children[relation.ParentID] = append(children[relation.ParentID], relation)
			childIDs[relation.ItemID] = struct{}{}
		}

		rows, _, err := s.repository.List(ctx, &query.ItemListOptions{
			ItemIDs: slices.Collect(maps.Keys(childIDs)),
		}, &items.ItemFields{NameHTML: true}, items.OrderByNone, false)
		if err != nil {
			return nil, err
		}

		byID := make(map[int64]*items.Item, len(rows))
		for _, row := range rows {
			byID[row.ID] = row
		}

		next := make([]*pb.TreeItem, 0, len(relations))

		for _, node := range level {
			for _, relation := range children[node.GetId()] {
				row, found := byID[relation.ItemID]
				if !found {
					return nil, items.ErrItemNotFound
				}

				child, err := s.carTreeNode(row, lang, relation.Type)
				if err != nil {
					return nil, err
				}
				// Each occurrence has its own subtree, even when a child has several parents.
				node.Childs = append(node.Childs, child)
				next = append(next, child)
			}
		}

		level = next
	}

	return root, nil
}

func (s *Server) carTreeNode(
	car *items.Item, lang string, parentType schema.ItemParentType,
) (*pb.TreeItem, error) {
	nameHTML, err := s.formatItemNameHTML(car, lang)
	if err != nil {
		return nil, err
	}

	return &pb.TreeItem{
		Id: car.ID, NameHtml: nameHTML, Childs: []*pb.TreeItem{}, Type: itemmapper.ParentTypeToProto(parentType),
	}, nil
}
