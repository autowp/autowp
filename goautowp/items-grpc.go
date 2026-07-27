package goautowp

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

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/index"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"github.com/doug-martin/goqu/v9"
	"github.com/nicksnyder/go-i18n/v2/i18n"
	"github.com/redis/go-redis/v9"
	"github.com/twpayne/go-geom"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

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
	itemParentLanguageNameField = "name"
)

func (s *ItemParent) Validate() ([]*errdetails.BadRequest_FieldViolation, error) {
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

	s.Catname, problems, err = catnameInputFilter.IsValidString(s.GetCatname())
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

type ItemsGRPCServer struct {
	UnimplementedItemsServer

	repository            *items.Repository
	itemParentLanguage    *items.ItemParentLanguageRepository
	db                    *goqu.Database
	auth                  *Auth
	contentLanguages      []string
	textstorageRepository *textstorage.Repository
	extractor             *ItemExtractor
	i18n                  *i18nbundle.I18n
	attrsRepository       *attrs.Repository
	picturesRepository    *pictures.Repository
	index                 *index.Index
	events                *Events
	usersRepository       *users.Repository
	messagingRepository   *messaging.Repository
	hostManager           *hosts.Manager
	itemParentExtractor   *ItemParentExtractor
	linkExtractor         *LinkExtractor
	redis                 *redis.Client
	catalogue             *Catalogue
	fileStorageConfig     config.FileStorageConfig
	itemOfDayCached       *ItemOfDayCached
}

func NewItemsGRPCServer(
	repository *items.Repository,
	itemParentLanguage *items.ItemParentLanguageRepository,
	db *goqu.Database,
	auth *Auth,
	contentLanguages []string,
	textstorageRepository *textstorage.Repository,
	extractor *ItemExtractor,
	i18n *i18nbundle.I18n,
	attrsRepository *attrs.Repository,
	picturesRepository *pictures.Repository,
	index *index.Index,
	events *Events,
	usersRepository *users.Repository,
	messagingRepository *messaging.Repository,
	hostManager *hosts.Manager,
	itemParentExtractor *ItemParentExtractor,
	linkExtractor *LinkExtractor,
	redis *redis.Client,
	catalogue *Catalogue,
	fileStorageConfig config.FileStorageConfig,
	itemOfDayCached *ItemOfDayCached,
) *ItemsGRPCServer {
	return &ItemsGRPCServer{
		repository:            repository,
		itemParentLanguage:    itemParentLanguage,
		db:                    db,
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
		linkExtractor:         linkExtractor,
		redis:                 redis,
		catalogue:             catalogue,
		fileStorageConfig:     fileStorageConfig,
		itemOfDayCached:       itemOfDayCached,
	}
}

func (s *ItemsGRPCServer) GetBrands(
	ctx context.Context,
	in *GetBrandsRequest,
) (*BrandsList, error) {
	if s == nil {
		return nil, status.Error(codes.Internal, "self not initialized")
	}

	lang := in.GetLanguage()

	resultArray, err := s.index.BrandsCache(ctx, lang)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*BrandsListLine, 0, len(resultArray))

	for _, line := range resultArray {
		characters := make([]*BrandsListCharacter, 0, len(line.Characters))

		for _, character := range line.Characters {
			rows := make([]*BrandsListItem, 0, len(character.Items))
			for _, item := range character.Items {
				rows = append(rows, &BrandsListItem{
					Id:                    item.ID,
					Catname:               item.Catname,
					Name:                  item.Name,
					ItemsCount:            item.ItemsCount,
					NewItemsCount:         item.NewItemsCount,
					AcceptedPicturesCount: item.AcceptedPicturesCount,
				})
			}

			characters = append(characters, &BrandsListCharacter{
				Id:        character.ID,
				Character: character.Character,
				Items:     rows,
			})
		}

		var category BrandsListLine_Category

		switch line.Category {
		case items.BrandsListCategoryDefault:
			category = BrandsListLine_DEFAULT
		case items.BrandsListCategoryNumber:
			category = BrandsListLine_NUMBER
		case items.BrandsListCategoryCyrillic:
			category = BrandsListLine_CYRILLIC
		case items.BrandsListCategoryLatin:
			category = BrandsListLine_LATIN
		}

		result = append(result, &BrandsListLine{
			Category:   category,
			Characters: characters,
		})
	}

	return &BrandsList{Lines: result}, nil
}

func (s *ItemsGRPCServer) GetTopBrandsList(
	ctx context.Context,
	in *GetTopBrandsListRequest,
) (*TopBrandsList, error) {
	if s == nil {
		return nil, status.Error(codes.Internal, "self not initialized")
	}

	cache, err := s.index.TopBrandsCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	brands := make([]*TopBrandsListItem, len(cache.Items))
	for idx, brand := range cache.Items {
		brands[idx] = &TopBrandsListItem{
			Id:            brand.ID,
			Catname:       util.NullStringToString(brand.Catname),
			Name:          brand.NameOnly,
			ItemsCount:    brand.DescendantsCount,
			NewItemsCount: brand.NewDescendantsCount,
		}
	}

	return &TopBrandsList{
		Brands: brands,
		Total:  int32(cache.Total), //nolint: gosec
	}, nil
}

func (s *ItemsGRPCServer) GetTopPersonsList(
	ctx context.Context,
	in *GetTopPersonsListRequest,
) (*TopPersonsList, error) {
	var pictureItemType schema.PictureItemType

	switch in.GetPictureItemType() { //nolint:exhaustive
	case PictureItemType_PICTURE_ITEM_CONTENT:
		pictureItemType = schema.PictureItemTypeContent
	case PictureItemType_PICTURE_ITEM_AUTHOR:
		pictureItemType = schema.PictureItemTypeAuthor
	default:
		return nil, status.Error(codes.InvalidArgument, "Unexpected picture_item_type")
	}

	res, err := s.index.PersonsCache(ctx, pictureItemType, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*TopPersonsListItem, len(res))
	for idx, b := range res {
		is[idx] = &TopPersonsListItem{
			Id:   b.ID,
			Name: b.NameOnly,
		}
	}

	return &TopPersonsList{
		Items: is,
	}, nil
}

func (s *ItemsGRPCServer) GetTopFactoriesList(
	ctx context.Context,
	in *GetTopFactoriesListRequest,
) (*TopFactoriesList, error) {
	res, err := s.index.FactoriesCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*TopFactoriesListItem, len(res))
	for idx, b := range res {
		is[idx] = &TopFactoriesListItem{
			Id:       b.ID,
			Name:     b.NameOnly,
			Count:    b.ChildItemsCount,
			NewCount: b.NewChildItemsCount,
		}
	}

	return &TopFactoriesList{
		Items: is,
	}, nil
}

func (s *ItemsGRPCServer) GetTopCategoriesList(
	ctx context.Context,
	in *GetTopCategoriesListRequest,
) (*TopCategoriesList, error) {
	res, err := s.index.CategoriesCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*TopCategoriesListItem, len(res))
	for idx, itm := range res {
		is[idx] = &TopCategoriesListItem{
			Id:       itm.ID,
			Name:     itm.NameOnly,
			Catname:  util.NullStringToString(itm.Catname),
			Count:    itm.DescendantsCount,
			NewCount: itm.NewDescendantsCount,
		}
	}

	return &TopCategoriesList{
		Items: is,
	}, nil
}

func (s *ItemsGRPCServer) GetTopTwinsBrandsList(
	ctx context.Context,
	in *GetTopTwinsBrandsListRequest,
) (*TopTwinsBrandsList, error) {
	twinsData, err := s.index.TwinsCache(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*TwinsBrandsListItem, len(twinsData.Res))
	for idx, twin := range twinsData.Res {
		is[idx] = &TwinsBrandsListItem{
			Id:       twin.ID,
			Catname:  util.NullStringToString(twin.Catname),
			Name:     twin.NameOnly,
			Count:    twin.DescendantsParentsCount,
			NewCount: twin.NewDescendantsParentsCount,
		}
	}

	return &TopTwinsBrandsList{
		Items: is,
		Count: int32(twinsData.Count), //nolint: gosec
	}, nil
}

func (s *ItemsGRPCServer) GetTwinsBrandsList(
	ctx context.Context,
	in *GetTwinsBrandsListRequest,
) (*TwinsBrandsList, error) {
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

	is := make([]*TwinsBrandsListItem, len(twinsData))
	for idx, brand := range twinsData {
		is[idx] = &TwinsBrandsListItem{
			Id:       brand.ID,
			Catname:  util.NullStringToString(brand.Catname),
			Name:     brand.NameOnly,
			Count:    brand.DescendantsParentsCount,
			NewCount: brand.NewDescendantsParentsCount,
		}
	}

	return &TwinsBrandsList{
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
func requestsModeratorOnlyListFilter(inOptions *ItemListOptions) bool {
	return inOptions.GetExcludeSelfAndChilds() > 0 || inOptions.GetAutocomplete() != "" ||
		inOptions.GetSuggestionsTo() != 0
}

func (s *ItemsGRPCServer) Item(ctx context.Context, in *ItemRequest) (*Item, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	fields := convertItemFields(in.GetFields())

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

func (s *ItemsGRPCServer) List(ctx context.Context, in *ItemsRequest) (*ItemList, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	fields := convertItemFields(in.GetFields())
	if requestsModeratorOnlyFields(fields) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	inOptions := in.GetOptions()

	if requestsModeratorOnlyListFilter(inOptions) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := convertItemListOptions(inOptions)
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

	order, options.SortByName = convertItemOrder(in.GetOrder())

	res, pages, err := s.repository.List(ctx, options, fields, order, true)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*Item, len(res))
	for idx, i := range res {
		is[idx], err = s.extractor.Extract(ctx, i, in.GetFields(), in.GetLanguage(), userCtx)
		if err != nil {
			return nil, err
		}
	}

	var paginator *Pages
	if pages != nil {
		paginator = &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
			Next:             pages.Next,
			Previous:         pages.Previous,
		}
	}

	return &ItemList{
		Items:     is,
		Paginator: paginator,
	}, nil
}

func (s *ItemsGRPCServer) GetItemsFirstChars(
	ctx context.Context,
	in *ItemsFirstCharsRequest,
) (*AlphaResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	inOptions := in.GetOptions()

	if requestsModeratorOnlyListFilter(inOptions) && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := convertItemListOptions(inOptions)
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

func (s *ItemsGRPCServer) GetContentLanguages(
	_ context.Context,
	_ *emptypb.Empty,
) (*ContentLanguages, error) {
	return &ContentLanguages{
		Languages: s.contentLanguages,
	}, nil
}

func (s *ItemsGRPCServer) GetItemLink(
	ctx context.Context,
	in *ItemLinksRequest,
) (*ItemLink, error) {
	options, err := convertLinkListOptions(in.GetOptions())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	row, err := s.repository.Link(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.linkExtractor.ExtractRow(row), nil
}

func (s *ItemsGRPCServer) GetItemLinks(
	ctx context.Context,
	in *ItemLinksRequest,
) (*ItemLinks, error) {
	inOptions := in.GetOptions()

	if inOptions.GetItemParentCacheDescendant() == nil && inOptions.GetId() == 0 &&
		inOptions.GetItemId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "ItemLinkOptions is almost empty")
	}

	options, err := convertLinkListOptions(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	rows, err := s.repository.Links(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &ItemLinks{
		Items: s.linkExtractor.ExtractRows(rows),
	}, nil
}

func (s *ItemsGRPCServer) DeleteItemLink(
	ctx context.Context,
	in *ItemLinkRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	_, err = s.db.Delete(schema.ItemLinkTable).
		Where(schema.ItemLinkTableIDCol.Eq(in.GetId())).
		Executor().ExecContext(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ItemsGRPCServer) CreateItemLink(
	ctx context.Context,
	in *ItemLink,
) (*CreateItemLinkResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	var id int64

	success, err := s.db.Insert(schema.ItemLinkTable).Rows(goqu.Record{
		schema.ItemLinkTableNameColName:   in.GetName(),
		schema.ItemLinkTableURLColName:    in.GetUrl(),
		schema.ItemLinkTableTypeColName:   in.GetType(),
		schema.ItemLinkTableItemIDColName: in.GetItemId(),
	}).Returning(schema.ItemLinkTableIDCol).Executor().ScanValContext(ctx, &id)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, status.Error(codes.Internal, errNoRowsReturned.Error())
	}

	return &CreateItemLinkResponse{
		Id: id,
	}, nil
}

func (s *ItemsGRPCServer) UpdateItemLink(
	ctx context.Context,
	in *ItemLink,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	_, err = s.db.Update(schema.ItemLinkTable).
		Set(goqu.Record{
			schema.ItemLinkTableNameColName:   in.GetName(),
			schema.ItemLinkTableURLColName:    in.GetUrl(),
			schema.ItemLinkTableTypeColName:   in.GetType(),
			schema.ItemLinkTableItemIDColName: in.GetItemId(),
		}).
		Where(schema.ItemLinkTableIDCol.Eq(in.GetId())).
		Executor().ExecContext(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ItemLink) Validate() ([]*errdetails.BadRequest_FieldViolation, error) {
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

	s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
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

	s.Url, problems, err = urlInputFilter.IsValidString(s.GetUrl())
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

	s.Type, problems, err = typeInputFilter.IsValidString(s.GetType())
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

func (s *ItemsGRPCServer) GetItemVehicleTypes(
	ctx context.Context,
	in *GetItemVehicleTypesRequest,
) (*GetItemVehicleTypesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetItemId() == 0 && in.GetVehicleTypeId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	sqlSelect := s.db.Select(schema.ItemVehicleTypeTableItemIDCol, schema.ItemVehicleTypeTableVehicleTypeIDCol).
		From(schema.ItemVehicleTypeTable).
		Where(schema.ItemVehicleTypeTableInheritedCol.IsFalse())

	if in.GetItemId() != 0 {
		sqlSelect = sqlSelect.Where(schema.ItemVehicleTypeTableItemIDCol.Eq(in.GetItemId()))
	}

	if in.GetVehicleTypeId() != 0 {
		sqlSelect = sqlSelect.Where(
			schema.ItemVehicleTypeTableVehicleTypeIDCol.Eq(in.GetVehicleTypeId()),
		)
	}

	rows, err := sqlSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	defer util.Close(rows)

	res := make([]*ItemVehicleType, 0)

	for rows.Next() {
		var ivt ItemVehicleType

		err = rows.Scan(&ivt.ItemId, &ivt.VehicleTypeId)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		res = append(res, &ivt)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return &GetItemVehicleTypesResponse{
		Items: res,
	}, nil
}

func (s *ItemsGRPCServer) GetItemVehicleType(
	ctx context.Context,
	in *ItemVehicleTypeRequest,
) (*ItemVehicleType, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetItemId() == 0 && in.GetVehicleTypeId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	sqlSelect := s.db.Select(schema.ItemVehicleTypeTableItemIDCol, schema.ItemVehicleTypeTableVehicleTypeIDCol).
		From(schema.ItemVehicleTypeTable).
		Where(
			schema.ItemVehicleTypeTableInheritedCol.IsFalse(),
			schema.ItemVehicleTypeTableItemIDCol.Eq(in.GetItemId()),
			schema.ItemVehicleTypeTableVehicleTypeIDCol.Eq(in.GetVehicleTypeId()),
		)

	var row schema.ItemVehicleTypeRow

	found, err := sqlSelect.ScanStructContext(ctx, &row)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !found {
		return nil, status.Error(codes.NotFound, "not found")
	}

	return &ItemVehicleType{ //nolint:exhaustruct
		ItemId:        row.ItemID,
		VehicleTypeId: row.VehicleTypeID,
	}, nil
}

func (s *ItemsGRPCServer) CreateItemVehicleType(
	ctx context.Context,
	in *ItemVehicleType,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

func (s *ItemsGRPCServer) DeleteItemVehicleType(
	ctx context.Context,
	in *ItemVehicleTypeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

func (s *ItemsGRPCServer) GetItemLanguages(
	ctx context.Context, in *GetItemLanguagesRequest,
) (*ItemLanguages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ItemLanguageList(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*ItemLanguage, len(rows))

	for idx, row := range rows {
		text := ""
		if row.TextID > 0 {
			text, err = s.textstorageRepository.Text(ctx, row.TextID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		fullText := ""
		if row.FullTextID > 0 {
			fullText, err = s.textstorageRepository.Text(ctx, row.FullTextID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		result[idx] = &ItemLanguage{
			ItemId:     row.ItemID,
			Name:       row.Name,
			Language:   row.Language,
			TextId:     row.TextID,
			Text:       text,
			FullTextId: row.FullTextID,
			FullText:   fullText,
		}
	}

	return &ItemLanguages{
		Items: result,
	}, nil
}

func (s *ItemsGRPCServer) UpdateItemLanguage(
	ctx context.Context,
	in *ItemLanguage,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	itemID := in.GetItemId()

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: itemID, Language: EventsDefaultLanguage},
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

		itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.events.Add(ctx, Event{
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

	return &emptypb.Empty{}, nil
}

func (s *ItemsGRPCServer) GetItemParentLanguages(
	ctx context.Context, in *GetItemParentLanguagesRequest,
) (*ItemParentLanguages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ParentLanguageList(ctx, in.GetItemId(), in.GetParentId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*ItemParentLanguage, len(rows))
	for idx, row := range rows {
		result[idx] = &ItemParentLanguage{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
			Name:     row.Name,
			Language: row.Language,
		}
	}

	return &ItemParentLanguages{
		Items: result,
	}, nil
}

func (s *ItemsGRPCServer) GetStats(ctx context.Context, _ *emptypb.Empty) (*StatsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	totalBrands, err := s.repository.Count(ctx, query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	totalCars, err := s.repository.Count(ctx, query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDVehicle},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	totalCarAttrs, err := s.attrsRepository.TotalZoneAttrs(ctx, 1)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	carAttrsValues, err := s.attrsRepository.TotalValues(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	carsWith4OrMorePictures, err := s.repository.ItemsWithPicturesCount(ctx, typicalPicturesInList)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemsWithBeginYear, err := s.repository.Count(ctx, query.ItemListOptions{
		HasBeginYear: true,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemsWithBeginAndEndYears, err := s.repository.Count(ctx, query.ItemListOptions{
		HasBeginYear: true,
		HasEndYear:   true,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	itemsWithBeginAndEndYearsAndMonths, err := s.repository.Count(ctx, query.ItemListOptions{
		HasBeginYear:  true,
		HasEndYear:    true,
		HasBeginMonth: true,
		HasEndMonth:   true,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	brandsWithLogo, err := s.repository.Count(ctx, query.ItemListOptions{
		HasLogo: true,
		TypeID:  []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	totalPictures, err := s.picturesRepository.Count(ctx, &query.PictureListOptions{})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	picturesWithCopyrights, err := s.picturesRepository.Count(ctx, &query.PictureListOptions{
		HasCopyrights: true,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &StatsResponse{
		Values: []*StatsValue{
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

func (s *ItemLanguage) Validate() ([]*errdetails.BadRequest_FieldViolation, error) {
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

	s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
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

	s.Text, problems, err = textInputFilter.IsValidString(s.GetText())
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

	s.FullText, problems, err = fullTextInputFilter.IsValidString(s.GetFullText())
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

func (s *ItemParentLanguage) Validate() ([]*errdetails.BadRequest_FieldViolation, error) {
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

	s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
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

func (s *ItemsGRPCServer) SetItemParentLanguage(
	ctx context.Context,
	in *ItemParentLanguage,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
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

func (s *ItemsGRPCServer) GetBrandNewItems(
	ctx context.Context,
	in *NewItemsRequest,
) (*NewItemsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	const (
		newItemsLimit = 30
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
		&ItemFields{Brandicon: true},
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

	extractedItems := make([]*Item, 0, len(carList))

	for _, car := range carList {
		extractedItem, err := s.extractor.Extract(
			ctx,
			car,
			&ItemFields{NameHtml: true},
			lang,
			userCtx,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extractedItems = append(extractedItems, extractedItem)
	}

	return &NewItemsResponse{
		Brand: extractedBrand,
		Items: extractedItems,
	}, nil
}

func (s *ItemsGRPCServer) GetNewItems(
	ctx context.Context,
	in *NewItemsRequest,
) (*NewItemsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	const (
		newItemsLimit = 20
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

	extractedItems := make([]*Item, 0, len(carList))

	for _, car := range carList {
		extractedItem, err := s.extractor.Extract(
			ctx,
			car,
			&ItemFields{NameHtml: true},
			lang,
			userCtx,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extractedItems = append(extractedItems, extractedItem)
	}

	return &NewItemsResponse{
		Brand: extractedBrand,
		Items: extractedItems,
	}, nil
}

func (s *ItemsGRPCServer) CreateItemParent(
	ctx context.Context,
	in *ItemParent,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: EventsDefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	parentItem, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: EventsDefaultLanguage},
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
		ctx, item.ID, parentItem.ID, convertItemParentType(in.GetType()), in.GetCatname(),
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

	itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	parentNameText, err := s.formatItemNameText(parentItem, EventsDefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
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

func (s *ItemsGRPCServer) UpdateItemParent(
	ctx context.Context,
	in *ItemParent,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	_, err = s.repository.UpdateItemParent(
		ctx,
		in.GetItemId(),
		in.GetParentId(),
		convertItemParentType(in.GetType()),
		in.GetCatname(),
		false,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ItemsGRPCServer) DeleteItemParent(
	ctx context.Context,
	in *DeleteItemParentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	item, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: EventsDefaultLanguage},
		&items.ItemFields{NameText: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	parent, err := s.repository.Item(
		ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: EventsDefaultLanguage},
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

	itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	parentNameText, err := s.formatItemNameText(parent, EventsDefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
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

func (s *ItemsGRPCServer) MoveItemParent(
	ctx context.Context,
	in *MoveItemParentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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
			ctx, &query.ItemListOptions{ItemID: in.GetItemId(), Language: EventsDefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		oldParent, err := s.repository.Item(
			ctx, &query.ItemListOptions{ItemID: in.GetParentId(), Language: EventsDefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		oldParentNameText, err := s.formatItemNameText(oldParent, EventsDefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newParent, err := s.repository.Item(
			ctx,
			&query.ItemListOptions{ItemID: in.GetDestParentId(), Language: EventsDefaultLanguage},
			&items.ItemFields{NameText: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newParentNameText, err := s.formatItemNameText(newParent, EventsDefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.events.Add(ctx, Event{
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

func (s *ItemsGRPCServer) RefreshInheritance(
	ctx context.Context, in *RefreshInheritanceRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

func (s *ItemsGRPCServer) SetUserItemSubscription(
	ctx context.Context, in *SetUserItemSubscriptionRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

func (s *ItemsGRPCServer) GetBrandSections(
	ctx context.Context, in *GetBrandSectionsRequest,
) (*BrandSections, error) {
	item, err := s.repository.Item(ctx, &query.ItemListOptions{
		ItemID:   in.GetItemId(),
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Language: in.GetLanguage(),
	}, nil)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	sections, err := s.brandSections(ctx, in.GetLanguage(), item.ID, item.Catname.String)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &BrandSections{
		Sections: sections,
	}, nil
}

type SectionPreset struct {
	Name       string
	CarTypeID  int64
	ItemTypeID []schema.ItemTableItemTypeID
	RouterLink []string
}

func (s *ItemsGRPCServer) GetItemParent(
	ctx context.Context,
	in *ItemParentsRequest,
) (*ItemParent, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	if !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	inOptions := in.GetOptions()

	options, err := convertItemParentListOptions(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.ItemParentListOptions{}
	}

	if options.ItemID == 0 || options.ParentID == 0 {
		return nil, status.Error(codes.NotFound, "primary key is zero")
	}

	fields := convertItemParentFields(in.GetFields())

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

func (s *ItemsGRPCServer) GetItemParents(
	ctx context.Context, in *ItemParentsRequest,
) (*ItemParents, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	inOptions := in.GetOptions()

	if inOptions.GetItemId() == 0 && inOptions.GetParentId() == 0 && !isModer {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	options, err := convertItemParentListOptions(inOptions)
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
	case ItemParentsRequest_NONE:
	case ItemParentsRequest_CATEGORIES_FIRST:
		order = items.ItemParentOrderByCategoriesFirst
	case ItemParentsRequest_AUTO:
		order = items.ItemParentOrderByAuto
	}

	fields := convertItemParentFields(in.GetFields())

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

	var paginator *Pages
	if pages != nil {
		paginator = &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
			Next:             pages.Next,
			Previous:         pages.Previous,
		}
	}

	return &ItemParents{
		Items:     res,
		Paginator: paginator,
	}, nil
}

func (s *ItemsGRPCServer) GetItemOfDay(ctx context.Context, in *ItemOfDayRequest) (*ItemOfDay, error) {
	return s.itemOfDayCached.GetItemOfDay(ctx, in.GetLanguage())
}

func (s *ItemsGRPCServer) GetTopSpecsContributions(
	ctx context.Context, in *TopSpecsContributionsRequest,
) (*TopSpecsContributions, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	lang := in.GetLanguage()

	cacheKey := "API_INDEX_SPEC_CARS_8_" + lang
	success := false

	var res TopSpecsContributions

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
		fields := ItemFields{
			NameHtml:    true,
			NameDefault: true,
			Description: true,
			HasText:     true,
			Design:      true,
			EngineVehicles: &ItemsRequest{
				Fields: &ItemFields{NameHtml: true, Route: true},
			},
			CanEditSpecs: true,
			SpecsRoute:   true,
			Route:        true,
			Categories: &ItemsRequest{
				Fields: &ItemFields{NameHtml: true},
			},
			Twins: &ItemsRequest{},
			PreviewPictures: &PreviewPicturesRequest{
				Pictures: &PicturesRequest{
					Fields: &PictureFields{ThumbMedium: true, NameText: true},
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
		}, convertItemFields(&fields), items.OrderByAttrsUserValuesUpdateDate, false)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extracted, err := s.extractor.ExtractRows(ctx, cars, &fields, lang, userCtx)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		res = TopSpecsContributions{
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

func (s *ItemsGRPCServer) GetPath(ctx context.Context, in *PathRequest) (*PathResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	lang := in.GetLanguage()

	fields := ItemFields{
		NameHtml: true,
		NameText: true,
		NameOnly: true,
	}
	convertedFields := convertItemFields(&fields)

	currentCategory, err := s.repository.Item(ctx, &query.ItemListOptions{
		Language: lang,
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
		Catname:  in.GetCatname(),
	}, convertedFields)
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

	var path []string
	if len(in.GetPath()) > 0 {
		path = strings.Split(in.GetPath(), "/")
	}

	currentCar := currentCategory
	for _, pathNode := range path {
		currentCar, err = s.repository.Item(ctx, &query.ItemListOptions{
			Language: lang,
			ItemParentParent: &query.ItemParentListOptions{
				ParentID: currentCar.ID,
				Catname:  pathNode,
			},
		}, convertedFields)
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

	res := make([]*PathItem, 0)

	var parentID int64

	for idx, item := range breadcrumbs {
		if idx == len(breadcrumbs)-1 {
			fields.Description = true
		}

		extracted, err := s.extractor.Extract(ctx, item.Item, &fields, lang, userCtx)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		res = append(res, &PathItem{
			Catname:  item.Catname,
			ParentId: parentID,
			Item:     extracted,
		})
		parentID = item.Item.ID
	}

	return &PathResponse{
		Path: res,
	}, nil
}

func (s *ItemsGRPCServer) GetAlpha(ctx context.Context, _ *emptypb.Empty) (*AlphaResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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
func categorizeFirstChars(chars []string) *AlphaResponse {
	res := AlphaResponse{
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

func (s *ItemsGRPCServer) CreateItem(ctx context.Context, in *Item) (*ItemID, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleCarsModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	in.Id = 0

	InvalidParams, err := in.Validate(ctx, s.repository, nil, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	itemTypeID := in.GetItemTypeId()

	filter := validation.StringSanitizeFilename{}

	set := schema.ItemRow{
		ItemTypeID:       convertItemTypeID(itemTypeID),
		IsConcept:        in.GetIsConcept(),
		IsConceptInherit: in.GetIsConceptInherit(),
		SpecInherit:      in.GetSpecInherit(),
		SpecID: sql.NullInt32{
			Valid: in.GetSpecId() > 0,
			Int32: in.GetSpecId(),
		},
		CreatedAt: sql.NullTime{Valid: true, Time: time.Now()},
		Name:      in.GetName(),
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

	itemID, err := s.repository.CreateItem(ctx, set, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if location := in.GetLocation(); location != nil {
		point, err := geom.NewPoint(geom.XY).SetCoords(geom.Coord{location.GetLatitude(), location.GetLongitude()})
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

	itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:  userCtx.UserID,
		Message: "Создан новый автомобиль " + html.EscapeString(itemNameText),
		Items:   []int64{itemID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &ItemID{Id: itemID}, nil
}

func (s *ItemsGRPCServer) UpdateItem( //nolint: maintidx
	ctx context.Context, in *UpdateItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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
		&query.ItemListOptions{ItemID: values.GetId(), Language: EventsDefaultLanguage},
		&items.ItemFields{Meta: true},
	)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	values.ItemTypeId = extractItemTypeID(item.ItemTypeID)
	oldData := item

	InvalidParams, err := values.Validate(ctx, s.repository, mask.GetPaths(), userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	set := schema.ItemRow{
		ID: item.ID,
	}
	notifyMeta := false

	if util.Contains(mask.GetPaths(), itemNameField) {
		notifyMeta = true
		set.Name = values.GetName()
	}

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

	err = s.repository.UpdateItem(ctx, set, mask.GetPaths(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if util.Contains(mask.GetPaths(), "location") {
		var point *geom.Point
		if location := values.GetLocation(); location != nil {
			point, err = geom.NewPoint(geom.XY).SetCoords(geom.Coord{location.GetLatitude(), location.GetLongitude()})
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
			&query.ItemListOptions{ItemID: item.ID, Language: EventsDefaultLanguage},
			&items.ItemFields{NameText: true, Meta: true},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		newData := item
		htmlChanges := make([]string, 0)

		changes, err := s.buildChangesMessage(
			ctx,
			oldData.ItemRow,
			newData.ItemRow,
			EventsDefaultLanguage,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		for _, line := range changes {
			htmlChanges = append(htmlChanges, html.EscapeString(line))
		}

		itemNameText, err := s.formatItemNameText(item, EventsDefaultLanguage)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		message := "Редактирование мета-информации автомобиля " + itemNameText
		if len(htmlChanges) > 0 {
			message += "<p>" + strings.Join(htmlChanges, "<br />") + "</p>"
		}

		err = s.events.Add(ctx, Event{
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
				changes, err := s.buildChangesMessage(ctx, oldData.ItemRow, newData.ItemRow, lang)
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

func (s *ItemsGRPCServer) GetTree(ctx context.Context, in *GetTreeRequest) (*TreeItem, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

	res, err := s.carTreeWalk(ctx, item, in.GetLanguage(), schema.ItemParentTypeDefault)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return res, nil
}

func (s *ItemsGRPCServer) GetVehicleTypes(
	ctx context.Context,
	_ *emptypb.Empty,
) (*VehicleTypeItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.catalogue.getVehicleTypesTree(ctx, 0)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &VehicleTypeItems{
		Items: rows,
	}, nil
}

func (s *ItemsGRPCServer) GetBrandVehicleTypes(
	ctx context.Context,
	in *GetBrandVehicleTypesRequest,
) (*BrandVehicleTypeItems, error) {
	rows, err := s.catalogue.getBrandVehicleTypes(ctx, in.GetBrandId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &BrandVehicleTypeItems{
		Items: rows,
	}, nil
}

func (s *ItemsGRPCServer) GetSpecs(ctx context.Context, _ *emptypb.Empty) (*SpecsItems, error) {
	rows, err := s.catalogue.getSpecs(ctx, 0)
	if err != nil {
		return nil, err
	}

	return &SpecsItems{ //nolint:exhaustruct
		Items: rows,
	}, nil
}

func (s *ItemsGRPCServer) GetBrandIcons(context.Context, *emptypb.Empty) (*BrandIcons, error) {
	if len(s.fileStorageConfig.S3.Endpoint) == 0 {
		return nil, errNoEndpointProvided
	}

	parsedURL, err := url.Parse(s.fileStorageConfig.S3.Endpoint)
	if err != nil {
		return nil, err
	}

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandsSpriteImageFilename
	imageURL := parsedURL.String()

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandsSpriteCSSFilename
	cssURL := parsedURL.String()

	return &BrandIcons{ //nolint:exhaustruct
		Image: imageURL,
		Css:   cssURL,
	}, nil
}

func (s *ItemsGRPCServer) formatterOptions(row *items.Item) items.ItemNameFormatterOptions {
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

func (s *ItemsGRPCServer) formatItemNameText(row *items.Item, lang string) (string, error) {
	if row == nil {
		return "", nil
	}

	nameFormatter := items.NewItemNameFormatter(s.i18n)

	return nameFormatter.FormatText(s.formatterOptions(row), lang)
}

func (s *ItemsGRPCServer) formatItemNameHTML(row *items.Item, lang string) (string, error) {
	if row == nil {
		return "", nil
	}

	nameFormatter := items.NewItemNameFormatter(s.i18n)

	return nameFormatter.FormatHTML(s.formatterOptions(row), lang)
}

func (s *ItemsGRPCServer) notifyItemParentSubscribers(
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

func (s *ItemsGRPCServer) notifyItemSubscribers(
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

func (s *ItemsGRPCServer) brandSections(
	ctx context.Context, lang string, brandID int64, brandCatname string,
) ([]*BrandSection, error) {
	// create groups array
	sections, err := s.carSections(ctx, lang, brandID, brandCatname)
	if err != nil {
		return nil, fmt.Errorf("carSections(): %w", err)
	}

	otherGroups, err := s.otherGroups(ctx, brandID, brandCatname, lang)
	if err != nil {
		return nil, fmt.Errorf("otherGroups(): %w", err)
	}

	return append(
		sections,
		&BrandSection{
			Name:       "Other",
			RouterLink: nil,
			Groups:     otherGroups,
		},
	), nil
}

func (s *ItemsGRPCServer) otherGroups(
	ctx context.Context, brandID int64, brandCatname string, lang string,
) ([]*BrandSection, error) {
	var groups []*BrandSection

	// concepts
	hasConcepts, err := s.repository.Exists(ctx, query.ItemListOptions{
		ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
			ParentID: brandID,
		},
		IsConcept: true,
	})
	if err != nil {
		return nil, err
	}

	localizer := s.i18n.Localizer(lang)

	if hasConcepts {
		translated, err := localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{
				ID: "concepts and prototypes",
			},
		})
		if err != nil {
			return nil, err
		}

		groups = append(groups, &BrandSection{
			RouterLink: frontend.BrandConceptsRoute(brandCatname),
			Name:       translated,
		})
	}

	groupTypes := []struct {
		PerspectiveID        int32
		ExcludePerspectiveID []int32
		Catname              string
		Name                 string
	}{
		{
			PerspectiveID: items.PerspectiveIDLogo,
			Catname:       frontend.BrandLogotypes,
			Name:          "logotypes",
		},
		{
			PerspectiveID: items.PerspectiveIDMixed,
			Catname:       frontend.BrandMixed,
			Name:          "mixed",
		},
		{
			ExcludePerspectiveID: []int32{items.PerspectiveIDLogo, items.PerspectiveIDMixed},
			Catname:              frontend.BrandOther,
			Name:                 "unsorted",
		},
	}

	for _, groupType := range groupTypes {
		picturesCount, err := s.picturesRepository.Count(ctx, &query.PictureListOptions{
			Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemID:               brandID,
				PerspectiveID:        groupType.PerspectiveID,
				ExcludePerspectiveID: groupType.ExcludePerspectiveID,
			},
		})
		if err != nil {
			return nil, err
		}

		if picturesCount > 0 {
			translated, err := localizer.Localize(&i18n.LocalizeConfig{
				DefaultMessage: &i18n.Message{
					ID: groupType.Name,
				},
			})
			if err != nil {
				return nil, err
			}

			groups = append(groups, &BrandSection{
				RouterLink: frontend.BrandGroupRoute(brandCatname, groupType.Catname),
				Name:       translated,
				Count:      int32(picturesCount), //nolint: gosec
			})
		}
	}

	return groups, nil
}

func (s *ItemsGRPCServer) carSections(
	ctx context.Context, lang string, brandID int64, brandCatname string,
) ([]*BrandSection, error) {
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

	sections := make([]*BrandSection, 0, len(sectionsPresets))

	for _, sectionsPreset := range sectionsPresets {
		sectionGroups, err := s.carSectionGroups(
			ctx,
			lang,
			brandID,
			brandCatname,
			sectionsPreset,
		)
		if err != nil {
			return nil, fmt.Errorf("carSectionGroups(): %w", err)
		}

		sections = append(sections, &BrandSection{
			Name:       sectionsPreset.Name,
			RouterLink: sectionsPreset.RouterLink,
			Groups:     sectionGroups,
		})
	}

	return sections, nil
}

func (s *ItemsGRPCServer) carSectionGroups(
	ctx context.Context,
	lang string,
	brandID int64,
	brandCatname string,
	section SectionPreset,
) ([]*BrandSection, error) {
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

	groups := make([]*BrandSection, 0, len(rows))

	for _, row := range rows {
		groups = append(groups, &BrandSection{
			RouterLink: frontend.BrandItemRoute(brandCatname, row.Catname),
			Name:       row.Name,
		})
	}

	return groups, nil
}

func (s *ItemsGRPCServer) translateBool(value bool, lang string) (string, error) {
	localizer := s.i18n.Localizer(lang)

	msg := "moder/vehicle/changes/boolean/false"
	if value {
		msg = "moder/vehicle/changes/boolean/true"
	}

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: msg},
	})
}

func (s *ItemsGRPCServer) translateNullBool(value sql.NullBool, lang string) (string, error) {
	if !value.Valid {
		return "", nil
	}

	return s.translateBool(value.Bool, lang)
}

func (s *ItemsGRPCServer) boolChange(
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

func (s *ItemsGRPCServer) nullBoolChange(
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

func (s *ItemsGRPCServer) nullInt16Change(
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

func (s *ItemsGRPCServer) nullInt32Change(
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

func (s *ItemsGRPCServer) nullStringChange(
	oldValue, newValue sql.NullString,
	msg string,
	lang string,
) (string, error) {
	from := util.NullStringToString(oldValue)
	to := util.NullStringToString(newValue)

	return s.stringChange(from, to, msg, lang)
}

func (s *ItemsGRPCServer) stringChange(
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

func (s *ItemsGRPCServer) buildChangesMessage( //nolint: maintidx
	ctx context.Context, oldData schema.ItemRow, newData schema.ItemRow, lang string,
) ([]string, error) {
	changes := make([]string, 0)

	change, err := s.stringChange(
		oldData.Name,
		newData.Name,
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

func (s *ItemsGRPCServer) carTreeWalk(
	ctx context.Context, car *items.Item, lang string, parentType schema.ItemParentType,
) (*TreeItem, error) {
	nameHTML, err := s.formatItemNameHTML(car, lang)
	if err != nil {
		return nil, err
	}

	data := TreeItem{
		Id:       car.ID,
		NameHtml: nameHTML,
		Childs:   []*TreeItem{},
		Type:     extractItemParentType(parentType),
	}

	itemParentRows, _, err := s.repository.ItemParents(ctx, &query.ItemParentListOptions{
		ParentID: car.ID,
	}, items.ItemParentFields{}, items.ItemParentOrderByAuto)
	if err != nil {
		return nil, err
	}

	for _, itemParentRow := range itemParentRows {
		carRow, err := s.repository.Item(ctx, &query.ItemListOptions{
			ItemID: itemParentRow.ItemID,
		}, &items.ItemFields{NameHTML: true})
		if err != nil {
			return nil, err
		}

		child, err := s.carTreeWalk(ctx, carRow, lang, itemParentRow.Type)
		if err != nil {
			return nil, err
		}

		data.Childs = append(data.Childs, child)
	}

	return &data, nil
}
