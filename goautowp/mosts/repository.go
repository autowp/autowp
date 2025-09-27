package mosts

import (
	"context"
	"errors"
	"fmt"
	"slices"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const mostsLimit = 7

var (
	errRatingNotFound      = errors.New("unknown rating")
	errLanguageNotProvided = errors.New("language not provided")
	errYearsRangeNotFound  = errors.New("years range not found")
)

type Repository struct {
	db              *goqu.Database
	itemsRepository *items.Repository
	attrsRepository *attrs.Repository
}

type ItemsOptions struct {
	Language string
	Most     string
	Years    string
	CarType  string
	BrandID  int64
}

type MostData struct {
	UnitID int64
	Cars   []MostDataCar
}

type MostDataCar struct {
	ItemID    int64
	ValueHTML string
}

type ResultItem struct {
	Item      *items.Item
	ValueHTML string
}

type Adapter interface {
	Items(
		ctx context.Context, db *goqu.Database, attrsRepository *attrs.Repository, listOptions *query.ItemListOptions,
		lang string,
	) (*MostData, error)
}

func NewRepository(
	db *goqu.Database, itemsRepository *items.Repository, attrsRepository *attrs.Repository,
) *Repository {
	return &Repository{
		db:              db,
		itemsRepository: itemsRepository,
		attrsRepository: attrsRepository,
	}
}

func (s *Repository) Items(
	ctx context.Context, options ItemsOptions, fields *items.ItemFields,
) ([]ResultItem, int64, error) {
	lang := options.Language
	if len(lang) == 0 {
		return nil, 0, errLanguageNotProvided
	}

	ratingIndex := slices.IndexFunc(ratings, func(r Rating) bool {
		return options.Most == r.CatName
	})

	if ratingIndex == -1 {
		return nil, 0, fmt.Errorf("%w: `%s`", errRatingNotFound, options.Most)
	}

	rating := ratings[ratingIndex]

	var (
		carType   *schema.VehicleTypeRow
		cYear     YearsRange
		carTypeID int64
		err       error
	)

	if len(options.CarType) > 0 {
		carType, err = s.itemsRepository.VehicleType(
			ctx,
			&query.VehicleTypeListOptions{Catname: options.CarType},
		)
		if err != nil {
			return nil, 0, err
		}
	}

	yearIndex := slices.IndexFunc(years, func(r YearsRange) bool {
		return options.Years == r.Folder
	})
	if yearIndex == -1 && len(options.Years) > 0 {
		return nil, 0, fmt.Errorf("%w: %s", errYearsRangeNotFound, options.Years)
	}

	if yearIndex != -1 {
		cYear = years[yearIndex]
	}

	if carType != nil {
		carTypeID = carType.ID
	}

	listOptions := query.ItemListOptions{
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDVehicle},
		Limit:    mostsLimit,
		Language: lang,
		YearsRange: query.YearsRange{
			Min: cYear.MinYear,
			Max: cYear.MaxYear,
		},
	}

	if carTypeID > 0 {
		listOptions.VehicleTypeAncestorID = carTypeID
	}

	if options.BrandID > 0 {
		listOptions.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{
			ParentID:      options.BrandID,
			ExcludeTuning: true,
		}
	}

	data, err := rating.Adapter.Items(ctx, s.db, s.attrsRepository, &listOptions, lang)
	if err != nil {
		return nil, 0, err
	}

	itemIDs := make([]int64, 0, len(data.Cars))
	for _, car := range data.Cars {
		itemIDs = append(itemIDs, car.ItemID)
	}

	itemsMap := make(map[int64]*items.Item, len(itemIDs))

	if len(itemIDs) > 0 {
		rows, _, err := s.itemsRepository.List(ctx, &query.ItemListOptions{
			Language: lang,
			ItemIDs:  itemIDs,
			Limit:    listOptions.Limit,
		}, fields, items.OrderByNone, false)
		if err != nil {
			return nil, 0, err
		}

		for _, row := range rows {
			itemsMap[row.ID] = row
		}
	}

	res := make([]ResultItem, 0, len(data.Cars))

	for _, car := range data.Cars {
		item, ok := itemsMap[car.ItemID]
		if ok {
			res = append(res, ResultItem{
				Item:      item,
				ValueHTML: car.ValueHTML,
			})
		}
	}

	return res, data.UnitID, nil
}

func (s *Repository) YearsMenu() []YearsRange {
	yearsMenu := make([]YearsRange, len(years), len(years)+1)
	copy(yearsMenu, years)
	yearsMenu = append(yearsMenu, YearsRange{
		Name:   "mosts/period/all-time",
		Folder: "",
	})

	return yearsMenu
}

type RatingsMenuItem struct {
	Name    string
	Catname string
}

func (s *Repository) RatingsMenu() []RatingsMenuItem {
	result := make([]RatingsMenuItem, 0, len(ratings))
	for _, most := range ratings {
		result = append(result, RatingsMenuItem{
			Name:    "most/" + most.CatName,
			Catname: most.CatName,
		})
	}

	return result
}

type CarTypesItem struct {
	NameRp  string
	Catname string
	Childs  []CarTypesItem
}

func (s *Repository) VehicleTypes(ctx context.Context, brandID int64) ([]CarTypesItem, error) {
	listOptions := query.VehicleTypeListOptions{NoParent: true}

	if brandID > 0 {
		listOptions.Childs = &query.VehicleTypeParentsListOptions{
			ItemVehicleTypeByID: &query.ItemVehicleTypeListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentID: brandID,
				},
			},
		}
	}

	rows, err := s.itemsRepository.VehicleTypes(ctx, &listOptions)
	if err != nil {
		return nil, err
	}

	carTypes := make([]CarTypesItem, 0, len(rows))

	for _, row := range rows {
		listOptions = query.VehicleTypeListOptions{ParentID: row.ID}

		if brandID > 0 {
			listOptions.Childs = &query.VehicleTypeParentsListOptions{
				ItemVehicleTypeByID: &query.ItemVehicleTypeListOptions{
					ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
						ParentID: brandID,
					},
				},
			}
		}

		srows, err := s.itemsRepository.VehicleTypes(ctx, &listOptions)
		if err != nil {
			return nil, err
		}

		childs := make([]CarTypesItem, 0, len(srows))

		for _, srow := range srows {
			childs = append(childs, CarTypesItem{
				Catname: srow.Catname,
				NameRp:  srow.NameRp,
			})
		}

		carTypes = append(carTypes, CarTypesItem{
			Catname: row.Catname,
			NameRp:  row.NameRp,
			Childs:  childs,
		})
	}

	return carTypes, nil
}
