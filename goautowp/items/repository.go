package items

import (
	"cmp"
	"context"
	"database/sql"
	"errors"
	"fmt"
	"io"
	"regexp"
	"slices"
	"sort"
	"strconv"
	"strings"
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/filter"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
	"github.com/mozillazg/go-unidecode"
	"github.com/twpayne/go-geom"
	"github.com/twpayne/go-geom/encoding/wkt"
	"golang.org/x/text/collate"
	"golang.org/x/text/language"
)

var (
	errUnexpectedID                    = errors.New("unexpected `id`")
	ErrItemNotFound                    = errors.New("item not found")
	errLangNotFound                    = errors.New("language not found")
	errFieldsIsRequired                = errors.New("fields is required")
	errFieldRequires                   = errors.New("fields requires")
	errItemParentCycle                 = errors.New("cycle detected")
	errFailedToCreateItemParentCatname = errors.New("failed to create catname")
	errInvalidItemParentCombination    = errors.New(
		"that type of parent is not allowed for this type",
	)
	errGroupRequired  = errors.New("only groups can have children")
	errSelfParent     = errors.New("self parent forbidden")
	errNoRowsReturned = errors.New("no rows returned")

	CyrillicRegexp = regexp.MustCompile(`^\p{Cyrillic}`)
	HanRegexp      = regexp.MustCompile(`^\p{Han}`)
	LatinRegexp    = regexp.MustCompile("^[A-Za-z]$")
	NumberRegexp   = regexp.MustCompile("^[0-9]$")
)

const (
	NewDays                       = 7
	ItemLanguageTextMaxLength     = 4096
	ItemLanguageFullTextMaxLength = 65536
	ItemLogoMaxFileSize           = 10 * 1024 * 1024
	ItemLogoMinWidth              = 50
	ItemLogoMinHeight             = 50

	colNameOnly                   = "name_only"
	colNameDefault                = "name_default"
	colSpecName                   = "spec_name"
	colSpecShortName              = "spec_short_name"
	colDescription                = "description"
	colFullText                   = "full_text"
	colDescendantsParentsCount    = "descendants_parents_count"
	colNewDescendantsParentsCount = "new_descendants_parents_count"
	colDescendantsCount           = "descendants_count"
	colNewDescendantsCount        = "new_descendants_count"
	colChildItemsCount            = "child_items_count"
	colNewChildItemsCount         = "new_child_items_count"
	colDescendantPicturesCount    = "descendant_pictures_count"
	colChildsCount                = "childs_count"
	colParentsCount               = "parents_count"
	colDescendantTwinsGroupsCount = "descendant_twins_groups_count"
	colInboxPicturesCount         = "inbox_pictures_count"
	colMostsActive                = "mosts_active"
	colCommentsAttentionsCount    = "comments_attentions_count"
	colAcceptedPicturesCount      = "accepted_pictures_count"
	colExactPicturesCount         = "exact_pictures_count"
	colStarCount                  = "star_count"
	colItemParentParentTimestamp  = "item_parent_parent_timestamp"
	colHasChildSpecs              = "has_child_specs"
	colHasSpecs                   = "has_specs"
	colAttrsUserValuesUpdateDate  = "attrs_user_values_update_date"
)

const (
	fractionDefaultMonth       = 10
	fractionQuarterMonths      = 3
	fractionTwoQuarterMonths   = 6
	fractionThreeQuarterMonths = 9
)

const (
	VehicleTypeIDCar     int64 = 29
	VehicleTypeIDMoto    int64 = 43
	VehicleTypeIDTractor int64 = 44
	VehicleTypeIDTruck   int64 = 17
	VehicleTypeIDBus     int64 = 19
)

const (
	PerspectiveIDMixed int32 = 25
	PerspectiveIDLogo  int32 = 22
)

type BrandsListCategory int

const (
	BrandsListCategoryDefault BrandsListCategory = iota
	BrandsListCategoryNumber
	BrandsListCategoryCyrillic
	BrandsListCategoryLatin
)

type BrandsListLine struct {
	Category   BrandsListCategory
	Characters []*BrandsListCharacter
}

type BrandsListCharacter struct {
	Character string
	ID        string
	Items     []*BrandsListItem
}

type BrandsListItem struct {
	ID                    int64
	Catname               string
	Name                  string
	ItemsCount            int32
	NewItemsCount         int32
	AcceptedPicturesCount int32
}

type OrderBy int

const (
	OrderByNone OrderBy = iota
	OrderByDescendantsCount
	OrderByDescendantPicturesCount
	OrderByCreatedAt
	OrderByName
	OrderByDescendantsParentsCount
	OrderByStarCount
	OrderByItemParentParentTimestamp
	OrderByChildsCount
	OrderByAge
	OrderByIDDesc
	OrderByIDAsc
	OrderByAttrsUserValuesUpdateDate
)

type ItemParentOrderBy int

const (
	ItemParentOrderByNone ItemParentOrderBy = iota
	ItemParentOrderByAuto
	ItemParentOrderByCategoriesFirst
	ItemParentOrderByStockFirst
	ItemParentOrderByName
)

var catnameBlacklist = []string{"sport", "tuning", "related", "pictures", "specifications"}

type CataloguePathOptions struct {
	ToBrand      bool
	ToBrandID    int64
	BreakOnFirst bool
	StockFirst   bool
}

type CataloguePathResult struct {
	Type            CataloguePathResultType
	BrandCatname    string
	CarCatname      string
	Path            []string
	Stock           bool
	CategoryCatname string
	ID              int64
}

type CataloguePathResultType = int

const (
	CataloguePathResultTypeBrand CataloguePathResultType = iota
	CataloguePathResultTypeCategory
	CataloguePathResultTypePerson
	CataloguePathResultTypeBrandItem
)

// Repository Main Object.
type Repository struct {
	db                               *goqu.Database
	mostsMinCarsCount                int
	descendantsCountColumn           *DescendantsCountColumn
	newDescendantsCountColumn        *NewDescendantsCountColumn
	descendantTwinsGroupsCountColumn *DescendantTwinsGroupsCountColumn
	childsCountColumn                *ChildsCountColumn
	parentsCountColumn               *ParentsCountColumn
	descriptionColumn                *TextstorageRefColumn
	fullTextColumn                   *TextstorageRefColumn
	nameOnlyColumn                   *NameOnlyColumn
	nameDefaultColumn                *NameDefaultColumn
	commentsAttentionsCountColumn    *CommentsAttentionsCountColumn
	descendantPicturesCountColumn    *DescendantPicturesCountColumn
	acceptedPicturesCountColumn      *StatusPicturesCountColumn
	inboxPicturesCountColumn         *StatusPicturesCountColumn
	exactPicturesCountColumn         *ExactPicturesCountColumn
	mostsActiveColumn                *MostsActiveColumn
	descendantsParentsCountColumn    *DescendantsParentsCountColumn
	newDescendantsParentsCountColumn *NewDescendantsParentsCountColumn
	childItemsCountColumn            *ChildItemsCountColumn
	newChildItemsCountColumn         *NewChildItemsCountColumn
	hasChildSpecsColumn              *HasChildSpecsColumn
	hasSpecsColumn                   *HasSpecsColumn
	logoColumn                       *SimpleColumn
	fullNameColumn                   *SimpleColumn
	idColumn                         *SimpleColumn
	catnameColumn                    *SimpleColumn
	engineItemIDColumn               *SimpleColumn
	engineInheritColumn              *SimpleColumn
	itemTypeIDColumn                 *SimpleColumn
	isGroupColumn                    *SimpleColumn
	isConceptColumn                  *SimpleColumn
	isConceptInheritColumn           *SimpleColumn
	specIDColumn                     *SimpleColumn
	specInheritColumn                *SimpleColumn
	beginYearColumn                  *SimpleColumn
	endYearColumn                    *SimpleColumn
	beginMonthColumn                 *SimpleColumn
	endMonthColumn                   *SimpleColumn
	beginModelYearColumn             *SimpleColumn
	endModelYearColumn               *SimpleColumn
	beginModelYearFractionColumn     *SimpleColumn
	endModelYearFractionColumn       *SimpleColumn
	todayColumn                      *SimpleColumn
	bodyColumn                       *SimpleColumn
	createdAtColumn                  *SimpleColumn
	beginOrderCacheColumn            *SimpleColumn
	endOrderCacheColumn              *SimpleColumn
	nameColumn                       Column
	specNameColumn                   *SpecNameColumn
	specShortNameColumn              *SpecShortNameColumn
	starCountColumn                  *StarCountColumn
	itemParentParentTimestampColumn  *ItemParentParentTimestampColumn
	producedColumn                   *SimpleColumn
	producedExactlyColumn            *SimpleColumn
	attrsUserValuesUpdateDateColumn  *AttrsUserValuesUpdateDateColumn
	textStorageRepository            *textstorage.Repository
	imageStorage                     *storage.Storage
	itemParentCache                  *ItemParentCacheRepository
	itemParentLanguage               *ItemParentLanguageRepository
}

type ItemParent struct {
	schema.ItemParentRow

	Name string `db:"name"`
}

type Item struct {
	schema.ItemRow

	NameOnly                   string
	NameDefault                string
	DescendantsParentsCount    int32
	NewDescendantsParentsCount int32
	ChildItemsCount            int32
	NewChildItemsCount         int32
	DescendantsCount           int32
	NewDescendantsCount        int32
	SpecName                   string
	SpecShortName              string
	Description                string
	FullText                   string
	DescendantPicturesCount    int32
	ChildsCount                int32
	ParentsCount               int32
	DescendantTwinsGroupsCount int32
	InboxPicturesCount         int32
	FullName                   string
	MostsActive                bool
	CommentsAttentionsCount    int32
	AcceptedPicturesCount      int32
	ExactPicturesCount         int32
	HasChildSpecs              bool
	HasSpecs                   bool
}

type ItemLanguage struct {
	ItemID     int64
	Language   string
	Name       string
	TextID     int32
	FullTextID int32
}

type ItemParentLanguage struct {
	ItemID   int64
	ParentID int64
	Language string
	Name     string
}

// NewRepository constructor.
func NewRepository(
	db *goqu.Database,
	mostsMinCarsCount int,
	itemParentLanguageRepository *ItemParentLanguageRepository,
	textStorageRepository *textstorage.Repository,
	imageStorage *storage.Storage,
) *Repository {
	return &Repository{
		db:                               db,
		mostsMinCarsCount:                mostsMinCarsCount,
		descendantsCountColumn:           &DescendantsCountColumn{db: db},
		newDescendantsCountColumn:        &NewDescendantsCountColumn{db: db},
		descendantTwinsGroupsCountColumn: &DescendantTwinsGroupsCountColumn{db: db},
		descendantPicturesCountColumn:    &DescendantPicturesCountColumn{},
		childsCountColumn:                &ChildsCountColumn{db: db},
		parentsCountColumn:               &ParentsCountColumn{db: db},
		descriptionColumn: &TextstorageRefColumn{
			db:  db,
			col: schema.ItemLanguageTableTextIDColName,
		},
		fullTextColumn: &TextstorageRefColumn{
			db:  db,
			col: schema.ItemLanguageTableFullTextIDColName,
		},
		nameOnlyColumn:                &NameOnlyColumn{DB: db},
		nameDefaultColumn:             &NameDefaultColumn{db: db},
		commentsAttentionsCountColumn: &CommentsAttentionsCountColumn{db: db},
		acceptedPicturesCountColumn: &StatusPicturesCountColumn{
			db:     db,
			status: schema.PictureStatusAccepted,
		},
		inboxPicturesCountColumn: &StatusPicturesCountColumn{
			db:     db,
			status: schema.PictureStatusInbox,
		},
		exactPicturesCountColumn: &ExactPicturesCountColumn{db: db},
		mostsActiveColumn: &MostsActiveColumn{
			db:                db,
			mostsMinCarsCount: mostsMinCarsCount,
		},
		descendantsParentsCountColumn:    &DescendantsParentsCountColumn{},
		newDescendantsParentsCountColumn: &NewDescendantsParentsCountColumn{},
		childItemsCountColumn:            &ChildItemsCountColumn{},
		newChildItemsCountColumn:         &NewChildItemsCountColumn{},
		hasChildSpecsColumn: &HasChildSpecsColumn{
			db: db,
		},
		hasSpecsColumn: &HasSpecsColumn{
			db: db,
		},
		idColumn:            &SimpleColumn{col: schema.ItemTableIDColName},
		logoColumn:          &SimpleColumn{col: schema.ItemTableLogoIDColName},
		fullNameColumn:      &SimpleColumn{col: schema.ItemTableFullNameColName},
		catnameColumn:       &SimpleColumn{col: schema.ItemTableCatnameColName},
		engineItemIDColumn:  &SimpleColumn{col: schema.ItemTableEngineItemIDColName},
		engineInheritColumn: &SimpleColumn{col: schema.ItemTableEngineInheritColName},
		itemTypeIDColumn:    &SimpleColumn{col: schema.ItemTableItemTypeIDColName},
		isGroupColumn:       &SimpleColumn{col: schema.ItemTableIsGroupColName},
		isConceptColumn:     &SimpleColumn{col: schema.ItemTableIsConceptColName},
		isConceptInheritColumn: &SimpleColumn{
			col: schema.ItemTableIsConceptInheritColName,
		},
		specIDColumn:         &SimpleColumn{col: schema.ItemTableSpecIDColName},
		specInheritColumn:    &SimpleColumn{col: schema.ItemTableSpecInheritColName},
		beginYearColumn:      &SimpleColumn{col: schema.ItemTableBeginYearColName},
		endYearColumn:        &SimpleColumn{col: schema.ItemTableEndYearColName},
		beginMonthColumn:     &SimpleColumn{col: schema.ItemTableBeginMonthColName},
		endMonthColumn:       &SimpleColumn{col: schema.ItemTableEndMonthColName},
		beginModelYearColumn: &SimpleColumn{col: schema.ItemTableBeginModelYearColName},
		endModelYearColumn:   &SimpleColumn{col: schema.ItemTableEndModelYearColName},
		beginModelYearFractionColumn: &SimpleColumn{
			col: schema.ItemTableBeginModelYearFractionColName,
		},
		endModelYearFractionColumn: &SimpleColumn{
			col: schema.ItemTableEndModelYearFractionColName,
		},
		todayColumn:                     &SimpleColumn{col: schema.ItemTableTodayColName},
		bodyColumn:                      &SimpleColumn{col: schema.ItemTableBodyColName},
		createdAtColumn:                 &SimpleColumn{col: schema.ItemTableCreatedAtColName},
		beginOrderCacheColumn:           &SimpleColumn{col: schema.ItemTableBeginOrderCacheColName},
		endOrderCacheColumn:             &SimpleColumn{col: schema.ItemTableEndOrderCacheColName},
		nameColumn:                      &NameOnlyColumn{DB: db},
		producedColumn:                  &SimpleColumn{col: schema.ItemTableProducedColName},
		producedExactlyColumn:           &SimpleColumn{col: schema.ItemTableProducedExactlyColName},
		specNameColumn:                  &SpecNameColumn{},
		specShortNameColumn:             &SpecShortNameColumn{},
		starCountColumn:                 &StarCountColumn{},
		itemParentParentTimestampColumn: &ItemParentParentTimestampColumn{},
		attrsUserValuesUpdateDateColumn: &AttrsUserValuesUpdateDateColumn{},
		textStorageRepository:           textStorageRepository,
		imageStorage:                    imageStorage,
		itemParentCache:                 NewItemParentCacheRepository(db),
		itemParentLanguage:              itemParentLanguageRepository,
	}
}

type ItemParentFields struct {
	Name bool
}

type ItemFields struct {
	NameOnly                   bool
	NameHTML                   bool
	NameDefault                bool
	Description                bool
	FullText                   bool
	HasText                    bool
	AcceptedPicturesCount      bool
	ExactPicturesCount         bool
	ChildItemsCount            bool
	NewChildItemsCount         bool
	DescendantsCount           bool
	NewDescendantsCount        bool
	NameText                   bool
	DescendantPicturesCount    bool
	ChildsCount                bool
	ParentsCount               bool
	DescendantTwinsGroupsCount bool
	InboxPicturesCount         bool
	FullName                   bool
	Logo                       bool
	MostsActive                bool
	CommentsAttentionsCount    bool
	DescendantsParentsCount    bool
	NewDescendantsParentsCount bool
	HasChildSpecs              bool
	HasSpecs                   bool
	OtherNames                 bool
	Meta                       bool
}

func (s *Repository) LanguageName(ctx context.Context, itemID int64, lang string) (string, error) {
	var res string

	success, err := s.db.Select(schema.ItemLanguageTableNameCol).
		From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(itemID),
			schema.ItemLanguageTableLanguageCol.Eq(lang),
		).
		ScanValContext(ctx, &res)
	if err != nil {
		return "", err
	}

	if !success {
		return "", nil
	}

	return res, nil
}

func (s *Repository) IDsSelect(options query.ItemListOptions) (*goqu.SelectDataset, error) {
	var (
		err   error
		alias = query.ItemAlias
	)

	if options.Alias != "" {
		alias = options.Alias
	}

	sqSelect, err := options.Select(s.db, alias)
	if err != nil {
		return nil, err
	}

	return sqSelect.Select(goqu.I(alias).Col(schema.ItemTableIDColName)), nil
}

func (s *Repository) IDs(ctx context.Context, options query.ItemListOptions) ([]int64, error) {
	var err error

	sqSelect, err := s.IDsSelect(options)
	if err != nil {
		return nil, err
	}

	var ids []int64

	err = sqSelect.Executor().ScanValsContext(ctx, &ids)
	if err != nil {
		return nil, err
	}

	return ids, nil
}

func (s *Repository) Exists(ctx context.Context, options query.ItemListOptions) (bool, error) {
	var exists bool

	sqSelect, err := options.ExistsSelect(s.db, query.ItemAlias)
	if err != nil {
		return false, err
	}

	success, err := sqSelect.Executor().ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return exists && success, nil
}

func (s *Repository) Count(ctx context.Context, options query.ItemListOptions) (int, error) {
	var count int

	sqSelect, err := options.CountSelect(s.db, query.ItemAlias)
	if err != nil {
		return 0, err
	}

	success, err := sqSelect.Executor().ScanValContext(ctx, &count)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return count, nil
}

func (s *Repository) CountDistinct(
	ctx context.Context,
	options query.ItemListOptions,
) (int, error) {
	var count int

	sqSelect, err := options.CountDistinctSelect(s.db, query.ItemAlias)
	if err != nil {
		return 0, err
	}

	success, err := sqSelect.Executor().ScanValContext(ctx, &count)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return count, nil
}

func (s *Repository) Item(
	ctx context.Context,
	options *query.ItemListOptions,
	fields *ItemFields,
) (*Item, error) {
	options.Limit = 1

	res, _, err := s.List(ctx, options, fields, OrderByNone, false)
	if err != nil {
		return nil, err
	}

	if len(res) == 0 {
		return nil, ErrItemNotFound
	}

	return res[0], nil
}

func (s *Repository) List( //nolint:maintidx
	ctx context.Context, options *query.ItemListOptions, fields *ItemFields, orderBy OrderBy,
	pagination bool,
) ([]*Item, *util.Pages, error) {
	var err error

	alias := query.ItemAlias
	aliasTable := goqu.T(alias)

	err = s.isFieldsValid(options, fields, orderBy)
	if err != nil {
		return nil, nil, err
	}

	if options.SortByName && (fields == nil || !fields.NameOnly) {
		return nil, nil, fmt.Errorf("%w: NameOnly for SortByName", errFieldsIsRequired)
	}

	sqSelect, err := options.Select(s.db, alias)
	if err != nil {
		return nil, nil, err
	}

	sqSelect = sqSelect.GroupBy(aliasTable.Col(schema.ItemTableIDColName))

	var pages *util.Pages

	outAlias := query.ItemAlias

	if options.Limit > 0 {
		wrappedOrderBy := s.wrappedOrderBy(query.ItemAlias, orderBy)

		if len(wrappedOrderBy) > 0 {
			sqSelect = sqSelect.Order(wrappedOrderBy...)
		}

		paginator := util.Paginator{
			SQLSelect:         sqSelect,
			ItemCountPerPage:  int32(options.Limit), //nolint: gosec
			CurrentPageNumber: int32(options.Page),  //nolint: gosec
		}

		if pagination {
			pages, err = paginator.GetPages(ctx)
			if err != nil {
				return nil, nil, err
			}
		}

		sqSelect, err = paginator.GetCurrentItems(ctx)
		if err != nil {
			return nil, nil, err
		}

		// implements deferred join pattern
		wrappedAlias := "wrapped"
		wrappedIDCol := goqu.T(wrappedAlias).Col(schema.ItemTableIDColName)
		wrappedColumns := s.wrappedSelectColumns(orderBy)
		wrappedColumnsExpr := make([]interface{}, 0, len(wrappedColumns))

		for _, mapItem := range toSortedColumns(wrappedColumns) {
			expr, err := mapItem.col.SelectExpr(query.ItemAlias, options.Language)
			if err != nil {
				return nil, nil, err
			}

			wrappedColumnsExpr = append(wrappedColumnsExpr, expr.As(mapItem.key))
		}

		wrapperColumns := s.columnsByFields(fields)
		wrapperColumnsExpr := make([]interface{}, 0, len(wrapperColumns))
		groupByColumnsExpr := make([]interface{}, 0, len(wrapperColumns))

		for _, mapItem := range toSortedColumns(wrapperColumns) {
			var expr AliaseableExpression

			_, isWrapped := wrappedColumns[mapItem.key]
			if isWrapped && mapItem.key != schema.ItemTableIDColName {
				expr = goqu.T(wrappedAlias).Col(mapItem.key)
			} else {
				expr, err = mapItem.col.SelectExpr(schema.ItemTableName, options.Language)
				if err != nil {
					return nil, nil, err
				}
			}

			groupByExpr := mapItem.col.GroupByExpr()

			if groupByExpr != nil {
				groupByColumnsExpr = append(groupByColumnsExpr, groupByExpr)
			}

			wrapperColumnsExpr = append(wrapperColumnsExpr, expr.As(mapItem.key))
		}

		options.Alias = schema.ItemTableName

		wrappedSqSelect := sqSelect

		sqSelect, err = options.Select(s.db, options.Alias)
		if err != nil {
			return nil, nil, err
		}

		sqSelect = sqSelect.Select(wrapperColumnsExpr...).
			From(schema.ItemTable).
			Join(wrappedSqSelect.Select(wrappedColumnsExpr...).As(wrappedAlias), goqu.On(
				schema.ItemTableIDCol.Eq(wrappedIDCol),
			)).
			GroupBy(schema.ItemTableIDCol).
			GroupByAppend(groupByColumnsExpr...)

		wrapperOrderBy, groupBy := s.wrapperOrderBy(schema.ItemTableName, wrappedAlias, orderBy)

		if len(wrapperOrderBy) > 0 {
			sqSelect = sqSelect.Order(wrapperOrderBy...)
			if len(groupBy) > 0 {
				sqSelect = sqSelect.GroupByAppend(groupBy...)
			}
		}

		outAlias = schema.ItemTableName
	} else {
		orderByExpr, err := s.orderBy(query.ItemAlias, orderBy, options.Language)
		if err != nil {
			return nil, nil, err
		}

		if len(orderByExpr) > 0 {
			sqSelect = sqSelect.Order(orderByExpr...)
		}

		columns := s.columnsByFields(fields)

		res := make([]interface{}, 0, len(columns))

		for _, mapItem := range toSortedColumns(columns) {
			expr, err := mapItem.col.SelectExpr(query.ItemAlias, options.Language)
			if err != nil {
				return nil, nil, err
			}

			res = append(res, expr.As(mapItem.key))

			groupByExpr := mapItem.col.GroupByExpr()
			if groupByExpr != nil {
				sqSelect = sqSelect.GroupByAppend(groupByExpr)
			}
		}

		sqSelect = sqSelect.Select(res...)
	}

	outTable := goqu.T(outAlias)

	if fields != nil && (fields.NameText || fields.NameHTML) {
		sqSelect = sqSelect.LeftJoin(
			schema.SpecTable,
			goqu.On(outTable.Col(schema.ItemTableSpecIDColName).Eq(schema.SpecTableIDCol)),
		)
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, nil, err
	}
	defer util.Close(rows)

	columnNames, err := rows.Columns()
	if err != nil {
		return nil, nil, err
	}

	var result []*Item

	for rows.Next() {
		var row Item

		var (
			specName      sql.NullString
			specShortName sql.NullString
			description   sql.NullString
			fullText      sql.NullString
			fullName      sql.NullString
		)

		pointers := make([]interface{}, len(columnNames))

		for i, colName := range columnNames {
			switch colName {
			case schema.ItemTableIDColName:
				pointers[i] = &row.ID
			case schema.ItemTableNameColName:
				pointers[i] = &row.Name
			case colNameOnly:
				pointers[i] = &row.NameOnly
			case colNameDefault:
				pointers[i] = &row.NameDefault
			case schema.ItemTableCatnameColName:
				pointers[i] = &row.Catname
			case schema.ItemTableFullNameColName:
				pointers[i] = &fullName
			case schema.ItemTableEngineItemIDColName:
				pointers[i] = &row.EngineItemID
			case schema.ItemTableEngineInheritColName:
				pointers[i] = &row.EngineInherit
			case schema.ItemTableItemTypeIDColName:
				pointers[i] = &row.ItemTypeID
			case schema.ItemTableIsGroupColName:
				pointers[i] = &row.IsGroup
			case schema.ItemTableIsConceptColName:
				pointers[i] = &row.IsConcept
			case schema.ItemTableIsConceptInheritColName:
				pointers[i] = &row.IsConceptInherit
			case schema.ItemTableSpecIDColName:
				pointers[i] = &row.SpecID
			case schema.ItemTableSpecInheritColName:
				pointers[i] = &row.SpecInherit
			case colDescription:
				pointers[i] = &description
			case colFullText:
				pointers[i] = &fullText
			case colDescendantsParentsCount:
				pointers[i] = &row.DescendantsParentsCount
			case colNewDescendantsParentsCount:
				pointers[i] = &row.NewDescendantsParentsCount
			case colDescendantsCount:
				pointers[i] = &row.DescendantsCount
			case colNewDescendantsCount:
				pointers[i] = &row.NewDescendantsCount
			case colChildItemsCount:
				pointers[i] = &row.ChildItemsCount
			case colNewChildItemsCount:
				pointers[i] = &row.NewChildItemsCount
			case schema.ItemTableBeginYearColName:
				pointers[i] = &row.BeginYear
			case schema.ItemTableEndYearColName:
				pointers[i] = &row.EndYear
			case schema.ItemTableBeginMonthColName:
				pointers[i] = &row.BeginMonth
			case schema.ItemTableEndMonthColName:
				pointers[i] = &row.EndMonth
			case schema.ItemTableBeginModelYearColName:
				pointers[i] = &row.BeginModelYear
			case schema.ItemTableEndModelYearColName:
				pointers[i] = &row.EndModelYear
			case schema.ItemTableBeginModelYearFractionColName:
				pointers[i] = &row.BeginModelYearFraction
			case schema.ItemTableEndModelYearFractionColName:
				pointers[i] = &row.EndModelYearFraction
			case schema.ItemTableTodayColName:
				pointers[i] = &row.Today
			case schema.ItemTableBodyColName:
				pointers[i] = &row.Body
			case colSpecName:
				pointers[i] = &specName
			case colSpecShortName:
				pointers[i] = &specShortName
			case colDescendantPicturesCount:
				pointers[i] = &row.DescendantPicturesCount
			case colChildsCount:
				pointers[i] = &row.ChildsCount
			case colParentsCount:
				pointers[i] = &row.ParentsCount
			case colDescendantTwinsGroupsCount:
				pointers[i] = &row.DescendantTwinsGroupsCount
			case colInboxPicturesCount:
				pointers[i] = &row.InboxPicturesCount
			case schema.ItemTableLogoIDColName:
				pointers[i] = &row.LogoID
			case colMostsActive:
				pointers[i] = &row.MostsActive
			case colCommentsAttentionsCount:
				pointers[i] = &row.CommentsAttentionsCount
			case colAcceptedPicturesCount:
				pointers[i] = &row.AcceptedPicturesCount
			case colExactPicturesCount:
				pointers[i] = &row.ExactPicturesCount
			case colHasChildSpecs:
				pointers[i] = &row.HasChildSpecs
			case colHasSpecs:
				pointers[i] = &row.HasSpecs
			case schema.ItemTableProducedColName:
				pointers[i] = &row.Produced
			case schema.ItemTableProducedExactlyColName:
				pointers[i] = &row.ProducedExactly
			default:
				pointers[i] = nil
			}
		}

		err = rows.Scan(pointers...)
		if err != nil {
			return nil, nil, err
		}

		if specName.Valid {
			row.SpecName = specName.String
		}

		if specShortName.Valid {
			row.SpecShortName = specShortName.String
		}

		if description.Valid {
			row.Description = description.String
		}

		if fullText.Valid {
			row.FullText = fullText.String
		}

		if fullName.Valid {
			row.FullName = fullName.String
		}

		result = append(result, &row)
	}

	if err = rows.Err(); err != nil {
		return nil, nil, err
	}

	if options.SortByName {
		tag := language.English

		switch options.Language {
		case schema.RussianLanguageCode:
			tag = language.Russian
		case schema.SimplifiedChineseLanguageCode:
			tag = language.SimplifiedChinese
		case schema.FrenchLanguageCode:
			tag = language.French
		case schema.SpanishLanguageCode:
			tag = language.Spanish
		case schema.UkrainianLanguageCode:
			tag = language.Ukrainian
		case schema.BelarusianLanguageCode:
			tag = language.Russian
		case schema.PortugueseBrazilianLanguageCode:
			tag = language.BrazilianPortuguese
		case schema.HebrewLanguageCode:
			tag = language.Hebrew
		}

		cl := collate.New(tag, collate.IgnoreCase, collate.IgnoreDiacritics)

		sort.SliceStable(result, func(i, j int) bool {
			iName := result[i].NameOnly
			jName := result[j].NameOnly

			switch options.Language {
			case schema.RussianLanguageCode, schema.UkrainianLanguageCode, schema.BelarusianLanguageCode:
				aIsCyrillic := CyrillicRegexp.MatchString(iName)
				bIsCyrillic := CyrillicRegexp.MatchString(jName)

				if aIsCyrillic && !bIsCyrillic {
					return true
				}

				if bIsCyrillic && !aIsCyrillic {
					return false
				}
			case schema.SimplifiedChineseLanguageCode:
				aIsHan := HanRegexp.MatchString(iName)
				bIsHan := HanRegexp.MatchString(jName)

				if aIsHan && !bIsHan {
					return true
				}

				if bIsHan && !aIsHan {
					return false
				}
			}

			return cl.CompareString(iName, jName) == -1
		})
	}

	return result, pages, nil
}

func (s *Repository) AddItemVehicleType(
	ctx context.Context,
	itemID int64,
	vehicleTypeID int64,
) error {
	ctx = context.WithoutCancel(ctx)

	changed, err := s.setItemVehicleTypeRow(ctx, itemID, vehicleTypeID, false)
	if err != nil {
		return err
	}

	if changed {
		err = s.RefreshItemVehicleTypeInheritanceFromParents(ctx, itemID)
		if err != nil {
			return err
		}

		err = s.refreshItemVehicleTypeInheritance(ctx, itemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) RemoveItemVehicleType(
	ctx context.Context,
	itemID int64,
	vehicleTypeID int64,
) error {
	ctx = context.WithoutCancel(ctx)

	res, err := s.db.From(schema.ItemVehicleTypeTable).Delete().
		Where(
			schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
			schema.ItemVehicleTypeTableVehicleTypeIDCol.Eq(vehicleTypeID),
			schema.ItemVehicleTypeTableInheritedCol.IsFalse(),
		).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	deleted, err := res.RowsAffected()
	if err != nil {
		return err
	}

	if deleted > 0 {
		err = s.RefreshItemVehicleTypeInheritanceFromParents(ctx, itemID)
		if err != nil {
			return err
		}

		err = s.refreshItemVehicleTypeInheritance(ctx, itemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) RefreshItemVehicleTypeInheritanceFromParents(
	ctx context.Context,
	itemID int64,
) error {
	typeIDs, err := s.getItemVehicleTypeIDs(ctx, itemID, false)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	if len(typeIDs) > 0 {
		// do not inherit when own value
		res, err := s.db.Delete(schema.ItemVehicleTypeTable).Where(
			schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
			schema.ItemVehicleTypeTableInheritedCol.IsTrue(),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}

		if affected > 0 {
			err = s.refreshItemVehicleTypeInheritance(ctx, itemID)
			if err != nil {
				return err
			}
		}

		return nil
	}

	types, err := s.getItemVehicleTypeInheritedIDs(ctx, itemID)
	if err != nil {
		return err
	}

	changed, err := s.setItemVehicleTypeRows(ctx, itemID, types, true)
	if err != nil {
		return err
	}

	if changed {
		err := s.refreshItemVehicleTypeInheritance(ctx, itemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) ItemLanguageCount(
	ctx context.Context,
	options *query.ItemLanguageListOptions,
) (int32, error) {
	var count int

	success, err := options.CountSelect(s.db, query.ItemLanguageAlias).
		Executor().
		ScanValContext(ctx, &count)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return int32(count), nil //nolint: gosec
}

func (s *Repository) ItemLanguageList(ctx context.Context, itemID int64) ([]ItemLanguage, error) {
	sqSelect := s.db.Select(
		schema.ItemLanguageTableItemIDCol,
		schema.ItemLanguageTableLanguageCol,
		schema.ItemLanguageTableNameCol,
		schema.ItemLanguageTableTextIDCol,
		schema.ItemLanguageTableFullTextIDCol,
	).
		From(schema.ItemLanguageTable).Where(
		schema.ItemLanguageTableItemIDCol.Eq(itemID),
		schema.ItemLanguageTableLanguageCol.Neq(schema.DefaultLanguageCode),
	)

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var result []ItemLanguage

	for rows.Next() {
		var (
			row            ItemLanguage
			nullName       sql.NullString
			nullTextID     sql.NullInt32
			nullFullTextID sql.NullInt32
		)

		err = rows.Scan(&row.ItemID, &row.Language, &nullName, &nullTextID, &nullFullTextID)
		if err != nil {
			return nil, err
		}

		row.Name = ""
		if nullName.Valid {
			row.Name = nullName.String
		}

		row.TextID = 0
		if nullTextID.Valid {
			row.TextID = nullTextID.Int32
		}

		row.FullTextID = 0
		if nullFullTextID.Valid {
			row.FullTextID = nullFullTextID.Int32
		}

		result = append(result, row)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}

func (s *Repository) UpdateItemLanguage(
	ctx context.Context, itemID int64, lang, name, text, fullText string, userID int64,
) ([]string, error) {
	var row schema.ItemLanguageRow

	success, err := s.db.Select(
		schema.ItemLanguageTableNameCol,
		schema.ItemLanguageTableTextIDCol,
		schema.ItemLanguageTableFullTextIDCol,
	).
		From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(itemID),
			schema.ItemLanguageTableLanguageCol.Eq(lang),
		).ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !success {
		row = schema.ItemLanguageRow{}
	}

	set := goqu.Record{}

	changes := make([]string, 0)

	oldName := ""
	if row.Name.Valid {
		oldName = row.Name.String
	}

	nameChanged := oldName != name

	if nameChanged {
		set[schema.ItemLanguageTableNameColName] = name

		changes = append(changes, "moder/vehicle/name")
	}

	textChanged := false
	ctx = context.WithoutCancel(ctx)

	if row.TextID.Valid {
		oldText, err := s.textStorageRepository.Text(ctx, row.TextID.Int32)
		if err != nil {
			return nil, err
		}

		textChanged = text != oldText

		err = s.textStorageRepository.SetText(ctx, row.TextID.Int32, text, userID)
		if err != nil {
			return nil, err
		}
	} else if len(text) > 0 {
		textChanged = true

		textID, err := s.textStorageRepository.CreateText(ctx, text, userID)
		if err != nil {
			return nil, err
		}

		set[schema.ItemLanguageTableTextIDColName] = textID
	}

	if textChanged {
		changes = append(changes, "moder/item/short-description")
	}

	fullTextChanged := false

	if row.FullTextID.Valid {
		oldFullText, err := s.textStorageRepository.Text(ctx, row.FullTextID.Int32)
		if err != nil {
			return nil, err
		}

		fullTextChanged = fullText != oldFullText

		err = s.textStorageRepository.SetText(ctx, row.FullTextID.Int32, fullText, userID)
		if err != nil {
			return nil, err
		}
	} else if len(fullText) > 0 {
		fullTextChanged = true

		fullTextID, err := s.textStorageRepository.CreateText(ctx, fullText, userID)
		if err != nil {
			return nil, err
		}

		set[schema.ItemLanguageTableFullTextIDColName] = fullTextID
	}

	if fullTextChanged {
		changes = append(changes, "moder/item/full-description")
	}

	if len(set) > 0 {
		onConflict := goqu.Record{}
		for col := range set {
			onConflict[col] = schema.Excluded(col)
		}

		set[schema.ItemLanguageTableItemIDColName] = itemID
		set[schema.ItemLanguageTableLanguageColName] = lang

		_, err = s.db.Insert(schema.ItemLanguageTable).Rows(set).OnConflict(goqu.DoUpdate(
			schema.ItemLanguageTableItemIDColName+","+schema.ItemLanguageTableLanguageColName,
			onConflict,
		)).Executor().ExecContext(ctx)
		if err != nil {
			return nil, err
		}

		_, err = s.RefreshAutoByVehicle(ctx, itemID)
		if err != nil {
			return nil, err
		}
	}

	if nameChanged {
		err = s.itemParentLanguage.UpdateAutoNamesForParentsAndChilds(ctx, itemID)
		if err != nil {
			return nil, err
		}
	}

	return changes, nil
}

func (s *Repository) ParentLanguageList(
	ctx context.Context, itemID int64, parentID int64,
) ([]ItemParentLanguage, error) {
	sqSelect := s.db.Select(schema.ItemParentLanguageTableItemIDCol, schema.ItemParentLanguageTableParentIDCol,
		schema.ItemParentLanguageTableLanguageCol, schema.ItemParentLanguageTableNameCol).
		From(schema.ItemParentLanguageTable).
		Where(
			schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
			schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
		)

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var result []ItemParentLanguage

	for rows.Next() {
		var row ItemParentLanguage

		err = rows.Scan(&row.ItemID, &row.ParentID, &row.Language, &row.Name)
		if err != nil {
			return nil, err
		}

		result = append(result, row)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}

func (s *Repository) ItemsWithPicturesCount(
	ctx context.Context, nPictures int,
) (int32, error) {
	const countAlias = "c"

	col := goqu.COUNT(schema.PictureTableIDCol)

	sqSelect := s.db.From(
		s.db.Select(schema.ItemTableIDCol, col.As(countAlias)).
			From(schema.ItemTable).
			Join(schema.PictureItemTable, goqu.On(schema.ItemTableIDCol.Eq(schema.PictureItemTable.Col("item_id")))).
			Join(schema.PictureTable, goqu.On(schema.PictureItemTable.Col("picture_id").Eq(schema.PictureTableIDCol))).
			GroupBy(schema.ItemTableIDCol).
			Having(col.Gte(nPictures)).
			As("T1"),
	)

	result, err := sqSelect.CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return int32(result), nil //nolint: gosec
}

func (s *Repository) CreateItemParent(
	ctx context.Context, itemID, parentID int64, typeID schema.ItemParentType, catname string,
) (bool, error) {
	if itemID == parentID {
		return false, errSelfParent
	}

	parentRow, err := s.Item(ctx, &query.ItemListOptions{ItemID: parentID}, nil)
	if err != nil {
		return false, err
	}

	itemRow, err := s.Item(ctx, &query.ItemListOptions{ItemID: itemID}, nil)
	if err != nil {
		return false, err
	}

	if !parentRow.IsGroup {
		return false, errGroupRequired
	}

	if !s.isAllowedCombination(itemRow.ItemTypeID, parentRow.ItemTypeID) {
		return false, fmt.Errorf(
			"%w: %d/%d",
			errInvalidItemParentCombination,
			itemRow.ItemTypeID,
			parentRow.ItemTypeID,
		)
	}

	if len(catname) > 0 {
		allowed, err := s.isAllowedCatname(ctx, itemID, parentID, catname)
		if err != nil {
			return false, err
		}

		if !allowed {
			catname = ""
		}
	}

	manualCatname := len(catname) > 0 && catname != "_"

	if !manualCatname {
		catname, err = s.extractCatname(ctx, parentRow.ItemRow, itemRow.ItemRow)
		if err != nil {
			return false, err
		}

		if len(catname) == 0 {
			return false, errFailedToCreateItemParentCatname
		}
	}

	parentIDs, err := s.collectAncestorsIDs(ctx, parentID)
	if err != nil {
		return false, err
	}

	if util.Contains(parentIDs, itemID) {
		return false, errItemParentCycle
	}

	exists := false

	success, err := s.db.Select(goqu.V(true)).From(schema.ItemParentTable).Where(
		schema.ItemParentTableItemIDCol.Eq(itemID),
		schema.ItemParentTableParentIDCol.Eq(parentID),
	).ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	if success && exists {
		return false, nil
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Insert(schema.ItemParentTable).Rows(goqu.Record{
		schema.ItemParentTableParentIDColName:      parentID,
		schema.ItemParentTableItemIDColName:        itemID,
		schema.ItemParentTableTypeColName:          typeID,
		schema.ItemParentTableCatnameColName:       catname,
		schema.ItemParentTableManualCatnameColName: manualCatname,
		schema.ItemParentTableTimestampColName:     goqu.Func("NOW"),
	}).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	err = s.itemParentLanguage.AfterItemParentCreated(ctx, parentRow, itemRow)
	if err != nil {
		return false, err
	}

	_, err = s.itemParentCache.RebuildCache(ctx, itemID)

	return err == nil, err
}

func (s *Repository) UpdateItemParent(
	ctx context.Context,
	itemID, parentID int64,
	typeID schema.ItemParentType,
	catname string,
	forceIsAuto bool,
) (bool, error) {
	var itemParentRow schema.ItemParentRow

	success, err := s.db.Select(
		schema.ItemParentTableCatnameCol,
		schema.ItemParentTableManualCatnameCol,
	).
		From(schema.ItemParentTable).Where(
		schema.ItemParentTableParentIDCol.Eq(parentID),
		schema.ItemParentTableItemIDCol.Eq(itemID),
	).ScanStructContext(ctx, &itemParentRow)
	if err != nil {
		return false, err
	}

	if !success {
		return false, nil
	}

	var isAuto bool

	if forceIsAuto {
		isAuto = true
	} else {
		isAuto = !itemParentRow.ManualCatname
		if itemParentRow.Catname != catname {
			isAuto = false
		}
	}

	if len(catname) == 0 || catname == "_" || util.Contains(catnameBlacklist, catname) {
		parentRow, err := s.Item(
			ctx,
			&query.ItemListOptions{ItemID: parentID},
			&ItemFields{NameText: true},
		)
		if err != nil {
			return false, err
		}

		itemRow, err := s.Item(
			ctx,
			&query.ItemListOptions{ItemID: itemID},
			&ItemFields{NameText: true},
		)
		if err != nil {
			return false, err
		}

		catname, err = s.extractCatname(ctx, parentRow.ItemRow, itemRow.ItemRow)
		if err != nil {
			return false, err
		}

		isAuto = true
	}

	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Update(schema.ItemParentTable).Set(goqu.Record{
		schema.ItemParentTableTypeColName:          typeID,
		schema.ItemParentTableCatnameColName:       catname,
		schema.ItemParentTableManualCatnameColName: !isAuto,
	}).Where(
		schema.ItemParentTableParentIDCol.Eq(parentID),
		schema.ItemParentTableItemIDCol.Eq(itemID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	if affected > 0 {
		_, err = s.itemParentCache.RebuildCache(ctx, itemID)

		return true, err
	}

	return false, nil
}

func (s *Repository) RemoveItemParent(ctx context.Context, itemID, parentID int64) error {
	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Delete(schema.ItemParentTable).Where(
		schema.ItemParentTableItemIDCol.Eq(itemID),
		schema.ItemParentTableParentIDCol.Eq(parentID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	affectedItemParentRows, err := res.RowsAffected()
	if err != nil {
		return err
	}

	res, err = s.db.Delete(schema.ItemParentLanguageTable).Where(
		schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
		schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	affectedItemParentLanguageRows, err := res.RowsAffected()
	if err != nil {
		return err
	}

	if affectedItemParentRows > 0 || affectedItemParentLanguageRows > 0 {
		_, err = s.itemParentCache.RebuildCache(ctx, itemID)
		if err != nil {
			return err
		}

		err = s.UpdateInheritance(ctx, itemID)
		if err != nil {
			return err
		}

		err = s.RefreshItemVehicleTypeInheritanceFromParents(ctx, itemID)
		if err != nil {
			return err
		}
	}

	return err
}

func (s *Repository) UpdateInheritance(ctx context.Context, itemID int64) error {
	var item schema.ItemRow

	success, err := s.db.Select(
		schema.ItemTableIDCol,
		schema.ItemTableIsConceptCol,
		schema.ItemTableIsConceptInheritCol,
		schema.ItemTableEngineInheritCol,
		schema.ItemTableVehicleTypeInheritCol,
		schema.ItemTableSpecInheritCol,
		schema.ItemTableSpecIDCol,
		schema.ItemTableEngineItemIDCol,
	).
		From(schema.ItemTable).
		Where(schema.ItemTableIDCol.Eq(itemID)).
		ScanStructContext(ctx, &item)
	if err != nil {
		return err
	}

	if !success {
		return ErrItemNotFound
	}

	return s.updateItemInheritance(ctx, item)
}

func (s *Repository) MoveItemParent(
	ctx context.Context,
	itemID, parentID, newParentID int64,
) (bool, error) {
	oldParentRow, err := s.Item(ctx, &query.ItemListOptions{ItemID: parentID}, nil)
	if err != nil {
		return false, err
	}

	itemRow, err := s.Item(ctx, &query.ItemListOptions{ItemID: itemID}, nil)
	if err != nil {
		return false, err
	}

	newParentRow, err := s.Item(ctx, &query.ItemListOptions{ItemID: newParentID}, nil)
	if err != nil {
		return false, err
	}

	if oldParentRow.ID == newParentRow.ID {
		return false, nil
	}

	if !oldParentRow.IsGroup {
		return false, errGroupRequired
	}

	if !newParentRow.IsGroup {
		return false, errGroupRequired
	}

	if !s.isAllowedCombination(itemRow.ItemTypeID, newParentRow.ItemTypeID) {
		return false, fmt.Errorf(
			"%w: %d/%d",
			errInvalidItemParentCombination,
			itemRow.ItemTypeID,
			newParentRow.ItemTypeID,
		)
	}

	parentIDs, err := s.collectAncestorsIDs(ctx, newParentRow.ID)
	if err != nil {
		return false, err
	}

	if util.Contains(parentIDs, itemID) {
		return false, errItemParentCycle
	}

	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Update(schema.ItemParentTable).Set(goqu.Record{
		schema.ItemParentTableParentIDColName: newParentID,
	}).Where(
		schema.ItemParentTableItemIDCol.Eq(itemID),
		schema.ItemParentTableParentIDCol.Eq(parentID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	if affected <= 0 {
		return false, nil
	}

	_, err = s.db.Update(schema.ItemParentLanguageTable).Set(goqu.Record{
		schema.ItemParentLanguageTableParentIDColName: newParentID,
	}).Where(
		schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
		schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	_, err = s.itemParentCache.RebuildCache(ctx, itemRow.ID)
	if err != nil {
		return false, err
	}

	_, err = s.refreshAuto(ctx, newParentID, itemID)
	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Repository) RefreshAutoByVehicle(ctx context.Context, itemID int64) (bool, error) {
	rows, err := s.getParentRows(ctx, itemID, false)
	if err != nil {
		return false, err
	}

	somethingChanged := false

	for _, itemParentRow := range rows {
		changed, err := s.refreshAuto(ctx, itemParentRow.ParentID, itemID)
		if err != nil {
			return false, err
		}

		if changed {
			somethingChanged = true
		}
	}

	return somethingChanged, nil
}

func (s *Repository) ItemParentSelect(
	listOptions *query.ItemParentListOptions, fields ItemParentFields, orderBy ItemParentOrderBy,
) (*goqu.SelectDataset, error) {
	alias := query.ItemParentAlias
	aliasTable := goqu.T(alias)

	sqSelect, groupBy, err := listOptions.Select(s.db, alias)
	if err != nil {
		return nil, err
	}

	sqSelect = sqSelect.Select(
		aliasTable.Col(schema.ItemParentTableItemIDColName),
		aliasTable.Col(schema.ItemParentTableParentIDColName),
		aliasTable.Col(schema.ItemParentTableCatnameColName),
		aliasTable.Col(schema.ItemParentTableTypeColName),
		aliasTable.Col(schema.ItemParentTableManualCatnameColName),
	)

	const itemParentLangNameAlias = "name"

	fetchItemParentLangName := fields.Name
	itemOrderAlias := "io"
	itemOrderAliasTable := goqu.T(itemOrderAlias)

	if groupBy {
		sqSelect = sqSelect.GroupByAppend(
			aliasTable.Col(schema.ItemParentTableItemIDColName),
			aliasTable.Col(schema.ItemParentTableParentIDColName),
		)
	}

	joinItem := false
	nameOnlyColumn := NameOnlyColumn{DB: s.db}

	switch orderBy {
	case ItemParentOrderByNone:
	case ItemParentOrderByAuto:
		joinItem = true

		nameExpr, err := nameOnlyColumn.SelectExpr(itemOrderAlias, listOptions.Language)
		if err != nil {
			return nil, err
		}

		sqSelect = sqSelect.Order(
			aliasTable.Col(schema.ItemParentTableTypeColName).Asc(),
			itemOrderAliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			itemOrderAliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
			nameExpr.Asc(),
			itemOrderAliasTable.Col(schema.ItemTableBodyColName).Asc(),
			itemOrderAliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
		)
		if groupBy {
			sqSelect = sqSelect.GroupByAppend(
				aliasTable.Col(schema.ItemParentTableTypeColName),
				itemOrderAliasTable.Col(schema.ItemTableIDColName),
				itemOrderAliasTable.Col(schema.ItemTableBeginOrderCacheColName),
				itemOrderAliasTable.Col(schema.ItemTableEndOrderCacheColName),
				itemOrderAliasTable.Col(schema.ItemTableBodyColName),
				itemOrderAliasTable.Col(schema.ItemTableSpecIDColName),
			)
		}
	case ItemParentOrderByCategoriesFirst:
		joinItem = true

		nameExpr, err := nameOnlyColumn.SelectExpr(itemOrderAlias, listOptions.Language)
		if err != nil {
			return nil, err
		}

		sqSelect = sqSelect.Order(
			aliasTable.Col(schema.ItemParentTableTypeColName).Asc(),
			goqu.L(
				"?",
				itemOrderAliasTable.Col(schema.ItemTableItemTypeIDColName).
					Eq(schema.ItemTableItemTypeIDCategory),
			).Desc(),
			itemOrderAliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			itemOrderAliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
			nameExpr.Asc(),
			itemOrderAliasTable.Col(schema.ItemTableBodyColName).Asc(),
			itemOrderAliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
		)
	case ItemParentOrderByStockFirst:
		sqSelect = sqSelect.Order(
			goqu.L("?",
				aliasTable.Col(schema.ItemParentTableTypeColName).Eq(schema.ItemParentTypeDefault),
			).Desc(),
		)
	case ItemParentOrderByName:
		fetchItemParentLangName = true
		sqSelect = sqSelect.Order(goqu.C(itemParentLangNameAlias).Asc())
	}

	if fetchItemParentLangName {
		orderExpr, err := langPriorityOrderExpr(
			schema.ItemParentLanguageTableLanguageCol,
			listOptions.Language,
		)
		if err != nil {
			return nil, err
		}

		// No fallback to the legacy item.name column: item_parent_language is the single source
		// of truth for this display name (same as NameOnlyColumn/NameDefaultColumn), so a
		// missing item_parent_language row reads as "" rather than silently reviving a possibly
		// stale item.name value.
		sqSelect = sqSelect.SelectAppend(
			goqu.Func(
				"COALESCE",
				s.db.Select(schema.ItemParentLanguageTableNameCol).
					From(schema.ItemParentLanguageTable).
					Where(
						schema.ItemParentLanguageTableItemIDCol.Eq(aliasTable.Col(schema.ItemParentTableItemIDColName)),
						schema.ItemParentLanguageTableParentIDCol.Eq(
							aliasTable.Col(schema.ItemParentTableParentIDColName),
						),
						goqu.Func("LENGTH", schema.ItemParentLanguageTableNameCol).Gt(0),
					).
					Order(orderExpr).
					Limit(1),
				goqu.V(""),
			).As(itemParentLangNameAlias),
		)
	}

	if joinItem {
		sqSelect = sqSelect.Join(schema.ItemTable.As(itemOrderAlias), goqu.On(
			aliasTable.Col(schema.ItemParentTableItemIDColName).
				Eq(itemOrderAliasTable.Col(schema.ItemTableIDColName)),
		))
	}

	return sqSelect, nil
}

func (s *Repository) ItemParents(
	ctx context.Context,
	listOptions *query.ItemParentListOptions,
	fields ItemParentFields,
	orderBy ItemParentOrderBy,
) ([]*ItemParent, *util.Pages, error) {
	sqSelect, err := s.ItemParentSelect(listOptions, fields, orderBy)
	if err != nil {
		return nil, nil, fmt.Errorf("ItemParentSelect(): %w", err)
	}

	var pages *util.Pages

	if listOptions.Limit > 0 {
		paginator := util.Paginator{
			SQLSelect:         sqSelect,
			ItemCountPerPage:  int32(listOptions.Limit), //nolint: gosec
			CurrentPageNumber: int32(listOptions.Page),  //nolint: gosec
		}

		pages, err = paginator.GetPages(ctx)
		if err != nil {
			return nil, nil, err
		}

		sqSelect, err = paginator.GetCurrentItems(ctx)
		if err != nil {
			return nil, nil, err
		}
	}

	var res []*ItemParent

	err = sqSelect.ScanStructsContext(ctx, &res)

	return res, pages, err
}

func (s *Repository) ItemParent(
	ctx context.Context, listOptions *query.ItemParentListOptions, fields ItemParentFields,
) (*ItemParent, error) {
	sqSelect, err := s.ItemParentSelect(listOptions, fields, ItemParentOrderByNone)
	if err != nil {
		return nil, err
	}

	var res ItemParent

	success, err := sqSelect.ScanStructContext(ctx, &res)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrItemNotFound
	}

	return &res, nil
}

func (s *Repository) UserItemSubscribed(ctx context.Context, itemID, userID int64) (bool, error) {
	var exists bool

	success, err := s.db.Select(goqu.V(true)).From(schema.UserItemSubscribeTable).Where(
		schema.UserItemSubscribeTableUserIDCol.Eq(userID),
		schema.UserItemSubscribeTableItemIDCol.Eq(itemID),
	).ScanValContext(ctx, &exists)

	return success && exists, err
}

func (s *Repository) UserItemSubscribe(ctx context.Context, itemID, userID int64) error {
	if userID == 0 {
		return nil
	}

	_, err := s.db.Insert(schema.UserItemSubscribeTable).Rows(goqu.Record{
		schema.UserItemSubscribeTableUserIDColName: userID,
		schema.UserItemSubscribeTableItemIDColName: itemID,
	}).OnConflict(goqu.DoNothing()).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UserItemUnsubscribe(ctx context.Context, itemID, userID int64) error {
	_, err := s.db.Delete(schema.UserItemSubscribeTable).Where(
		schema.UserItemSubscribeTableUserIDCol.Eq(userID),
		schema.UserItemSubscribeTableItemIDCol.Eq(itemID),
	).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) VehicleType(
	ctx context.Context, options *query.VehicleTypeListOptions,
) (*schema.VehicleTypeRow, error) {
	var st schema.VehicleTypeRow

	aliasTable := goqu.T(query.VehicleTypeTableAlias)

	sqSelect, err := options.Select(s.db, query.VehicleTypeTableAlias)
	if err != nil {
		return nil, err
	}

	success, err := sqSelect.Select(
		aliasTable.Col(schema.VehicleTypeTableIDColName),
		aliasTable.Col(schema.VehicleTypeTableCatnameColName),
		aliasTable.Col(schema.VehicleTypeTableNameRpColName),
	).Limit(1).ScanStructContext(ctx, &st)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	return &st, nil
}

func (s *Repository) VehicleTypes(
	ctx context.Context, options *query.VehicleTypeListOptions,
) ([]*schema.VehicleTypeRow, error) {
	var sts []*schema.VehicleTypeRow

	sqSelect, err := options.Select(s.db, query.VehicleTypeTableAlias)
	if err != nil {
		return nil, err
	}

	aliasTable := goqu.T(query.VehicleTypeTableAlias)

	err = sqSelect.
		Select(
			aliasTable.Col(schema.VehicleTypeTableIDColName),
			aliasTable.Col(schema.VehicleTypeTableCatnameColName),
			aliasTable.Col(schema.VehicleTypeTableNameRpColName),
		).
		Order(aliasTable.Col(schema.VehicleTypeTablePositionColName).Asc()).
		ScanStructsContext(ctx, &sts)
	if err != nil {
		return nil, err
	}

	return sts, nil
}

func (s *Repository) VehicleTypeIDs(
	ctx context.Context,
	vehicleID int64,
	inherited bool,
) ([]int64, error) {
	sqSelect := s.db.Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).
		From(schema.ItemVehicleTypeTable).
		Where(schema.ItemVehicleTypeTableItemIDCol.Eq(vehicleID))

	if inherited {
		sqSelect = sqSelect.Where(schema.ItemVehicleTypeTableInheritedCol.IsTrue())
	} else {
		sqSelect = sqSelect.Where(schema.ItemVehicleTypeTableInheritedCol.IsFalse())
	}

	var res []int64

	err := sqSelect.ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) SetItemLocation(ctx context.Context, itemID int64, point *geom.Point) error {
	if point == nil {
		_, err := s.db.Delete(schema.ItemPointTable).
			Where(schema.ItemPointTableItemIDCol.Eq(itemID)).
			Executor().ExecContext(ctx)

		return err
	}

	wktPoint, err := wkt.Marshal(point)
	if err != nil {
		return err
	}

	_, err = s.db.Insert(schema.ItemPointTable).Rows(goqu.Record{
		schema.ItemPointTableItemIDColName: itemID,
		schema.ItemPointTablePointColName:  goqu.Func("ST_GeogFromText", wktPoint),
	}).OnConflict(goqu.DoUpdate(
		schema.ItemPointTableItemIDColName,
		goqu.Record{
			schema.ItemPointTablePointColName: schema.Excluded(schema.ItemPointTablePointColName),
		},
	)).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) ItemLocation(ctx context.Context, itemID int64) (schema.NullPoint, error) {
	var res schema.NullPoint

	success, err := s.db.Select(schema.ItemPointTablePointCol).
		From(schema.ItemPointTable).
		Where(schema.ItemPointTableItemIDCol.Eq(itemID)).
		ScanValContext(ctx, &res)
	if err != nil {
		return res, err
	}

	if !success {
		return res, ErrItemNotFound
	}

	return res, nil
}

func (s *Repository) Brands(ctx context.Context, lang string) ([]*BrandsListLine, error) {
	options := query.ItemListOptions{
		Language:   lang,
		TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		SortByName: true,
	}

	rows, _, err := s.List(ctx, &options, &ItemFields{
		NameOnly:              true,
		DescendantsCount:      true,
		NewDescendantsCount:   true,
		AcceptedPicturesCount: true,
	}, OrderByNone, false)
	if err != nil {
		return nil, err
	}

	result := make(map[BrandsListCategory]map[string][]*BrandsListItem)

	for _, row := range rows {
		name := row.NameOnly

		char := ""
		if len(name) > 0 {
			char = string([]rune(name)[0:1])
		}

		isNumber := NumberRegexp.MatchString(char)
		isCyrillic := false
		isLatin := false

		if !isNumber {
			isHan := HanRegexp.MatchString(char)
			if isHan {
				char = unidecode.Unidecode(char)
				if len(char) > 1 {
					char = char[0:1]
				}

				isLatin = true
			} else {
				isCyrillic = CyrillicRegexp.MatchString(char)
				if !isCyrillic {
					char = unidecode.Unidecode(char)
					if len(char) > 1 {
						char = char[0:1]
					}

					isLatin = LatinRegexp.MatchString(char)
				}
			}

			char = strings.ToUpper(char)
		}

		line := BrandsListCategoryDefault

		switch {
		case isNumber:
			line = BrandsListCategoryNumber
		case isCyrillic:
			line = BrandsListCategoryCyrillic
		case isLatin:
			line = BrandsListCategoryLatin
		}

		if _, ok := result[line]; !ok {
			result[line] = make(map[string][]*BrandsListItem)
		}

		if _, ok := result[line][char]; !ok {
			result[line][char] = make([]*BrandsListItem, 0)
		}

		catname := ""
		if row.Catname.Valid {
			catname = row.Catname.String
		}

		result[line][char] = append(result[line][char], &BrandsListItem{
			ID:                    row.ID,
			Name:                  row.NameOnly,
			Catname:               catname,
			AcceptedPicturesCount: row.AcceptedPicturesCount,
			NewItemsCount:         row.NewDescendantsCount,
			ItemsCount:            row.DescendantsCount,
		})
	}

	resultArray := make([]*BrandsListLine, 0)

	for category, line := range result {
		charsArray := make([]*BrandsListCharacter, 0)

		for char, list := range line {
			id := ""
			if len(char) > 0 {
				id = strconv.Itoa(int([]rune(char)[0]))
			}

			charsArray = append(charsArray, &BrandsListCharacter{
				ID:        id,
				Character: char,
				Items:     list,
			})
		}

		slices.SortFunc(charsArray, func(i, j *BrandsListCharacter) int {
			return cmp.Compare(i.Character, j.Character)
		})

		resultArray = append(resultArray, &BrandsListLine{
			Category:   category,
			Characters: charsArray,
		})
	}

	slices.SortFunc(resultArray, func(i, j *BrandsListLine) int {
		return cmp.Compare(i.Category, j.Category)
	})

	return resultArray, nil
}

// ItemsFirstChars returns the sorted, deduplicated set of first characters (uppercased) of
// the NameOnly (locale-priority resolved name, with fallback) of every item matching options.
func (s *Repository) ItemsFirstChars(ctx context.Context, options *query.ItemListOptions) ([]string, error) {
	rows, _, err := s.List(ctx, options, &ItemFields{NameOnly: true}, OrderByNone, false)
	if err != nil {
		return nil, err
	}

	charsSet := make(map[string]struct{})

	for _, row := range rows {
		name := row.NameOnly
		if len(name) == 0 {
			continue
		}

		char := strings.ToUpper(string([]rune(name)[0:1]))
		charsSet[char] = struct{}{}
	}

	chars := make([]string, 0, len(charsSet))
	for char := range charsSet {
		chars = append(chars, char)
	}

	slices.Sort(chars)

	return chars, nil
}

func (s *Repository) RefreshItemParentAllAuto(ctx context.Context) error {
	logging.Infof("RefreshItemParentAllAuto()")

	itemParentRows, _, err := s.ItemParents(ctx, &query.ItemParentListOptions{
		NotManualCatname: true,
	}, ItemParentFields{}, ItemParentOrderByNone)
	if err != nil {
		return err
	}

	for _, itemParentRow := range itemParentRows {
		_, err = s.refreshAuto(ctx, itemParentRow.ParentID, itemParentRow.ItemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) Names(ctx context.Context, id int64) (map[string]string, error) {
	var sts []struct {
		Language string `db:"language"`
		Name     string `db:"name"`
	}

	err := s.db.Select(schema.ItemLanguageTableLanguageCol, schema.ItemLanguageTableNameCol).
		From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(id),
			goqu.Func("length", schema.ItemLanguageTableNameCol).Gt(0),
		).
		ScanStructsContext(ctx, &sts)
	if err != nil {
		return nil, err
	}

	res := make(map[string]string, 0)

	for _, st := range sts {
		res[st.Language] = st.Name
	}

	return res, nil
}

type DesignInfo struct {
	Name  string
	Route []string
}

func (s *Repository) DesignInfo(ctx context.Context, id int64, lang string) (*DesignInfo, error) {
	row := struct {
		Name             string `db:"name"`
		Catname          string `db:"catname"`
		BrandItemCatname string `db:"brand_item_catname"`
	}{}

	nameColumn := NameOnlyColumn{
		DB: s.db,
	}

	expr, err := nameColumn.SelectExpr(schema.ItemTableName, lang)
	if err != nil {
		return nil, err
	}

	sqSelect := s.db.Select(
		schema.ItemTableCatnameCol, expr.As("name"), schema.ItemParentTableCatnameCol.As("brand_item_catname"),
	).
		From(schema.ItemTable).
		Join(schema.ItemParentTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentTableParentIDCol))).
		Join(schema.ItemParentCacheTable, goqu.On(
			schema.ItemParentTableItemIDCol.Eq(schema.ItemParentCacheTableParentIDCol),
		)).
		Where(
			schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand),
			schema.ItemParentCacheTableItemIDCol.Eq(id),
		).
		Order(schema.ItemParentCacheTableDiffCol.Asc()).
		Limit(1)

	success, err := sqSelect.Where(schema.ItemParentTableTypeCol.Eq(schema.ItemParentTypeDesign)).
		ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if success {
		return &DesignInfo{
			Name:  row.Name,
			Route: frontend.BrandItemRoute(row.Catname, row.BrandItemCatname),
		}, nil
	}

	success, err = sqSelect.Where(schema.ItemParentCacheTableDesignCol.IsTrue()).
		ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if success {
		return &DesignInfo{
			Name:  row.Name,
			Route: frontend.BrandItemRoute(row.Catname, row.BrandItemCatname),
		}, nil
	}

	return nil, nil //nolint: nilnil
}

func (s *Repository) SpecsRoute(ctx context.Context, id int64) ([]string, error) {
	cataloguePaths, err := s.CataloguePath(ctx, id, CataloguePathOptions{
		ToBrand:      true,
		BreakOnFirst: true,
	})
	if err != nil {
		return nil, err
	}

	for _, path := range cataloguePaths {
		return frontend.BrandItemPathSpecificationsRoute(
			path.BrandCatname,
			path.CarCatname,
			path.Path,
		), nil
	}

	return nil, nil
}

func (s *Repository) CataloguePath(
	ctx context.Context, id int64, options CataloguePathOptions,
) ([]CataloguePathResult, error) {
	paths, err := s.CataloguePaths(ctx, id, options)
	if err != nil {
		return nil, err
	}

	return paths, nil
}

func (s *Repository) CataloguePaths(
	ctx context.Context, id int64, options CataloguePathOptions,
) ([]CataloguePathResult, error) {
	if id <= 0 {
		return nil, errUnexpectedID
	}

	breakOnFirst := options.BreakOnFirst
	stockFirst := options.StockFirst
	toBrand := options.ToBrand

	result := make([]CataloguePathResult, 0)

	if options.ToBrandID == 0 || id == options.ToBrandID {
		var brandCatname sql.NullString

		success, err := s.db.Select(schema.ItemTableCatnameCol).From(schema.ItemTable).Where(
			schema.ItemTableIDCol.Eq(id),
			schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand),
		).ScanValContext(ctx, &brandCatname)
		if err != nil {
			return nil, err
		}

		if success {
			result = append(result, CataloguePathResult{
				Type:         CataloguePathResultTypeBrand,
				BrandCatname: util.NullStringToString(brandCatname),
				CarCatname:   "",
				Path:         []string{},
				Stock:        true,
			})

			if breakOnFirst {
				return result, nil
			}
		}
	}

	if !toBrand {
		var category schema.ItemRow

		success, err := s.db.Select(schema.ItemTableIDCol, schema.ItemTableCatnameCol, schema.ItemTableItemTypeIDCol).
			From(schema.ItemTable).
			Where(
				schema.ItemTableIDCol.Eq(id),
				schema.ItemTableItemTypeIDCol.In(
					[]schema.ItemTableItemTypeID{
						schema.ItemTableItemTypeIDCategory,
						schema.ItemTableItemTypeIDPerson,
					},
				),
			).
			ScanStructContext(ctx, &category)
		if err != nil {
			return nil, err
		}

		if success {
			switch category.ItemTypeID {
			case schema.ItemTableItemTypeIDCategory:
				result = append(result, CataloguePathResult{
					Type:            CataloguePathResultTypeCategory,
					CategoryCatname: util.NullStringToString(category.Catname),
				})

				if breakOnFirst {
					return result, nil
				}

			case schema.ItemTableItemTypeIDPerson:
				result = append(result, CataloguePathResult{
					Type: CataloguePathResultTypePerson,
					ID:   category.ID,
				})

				if breakOnFirst {
					return result, nil
				}

			case schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDTwins,
				schema.ItemTableItemTypeIDBrand,
				schema.ItemTableItemTypeIDFactory,
				schema.ItemTableItemTypeIDMuseum,
				schema.ItemTableItemTypeIDCopyright:
			}
		}
	}

	parentRows, _, err := s.ItemParents(ctx, &query.ItemParentListOptions{
		ItemID: id,
	}, ItemParentFields{}, ItemParentOrderByStockFirst)
	if err != nil {
		return nil, err
	}

	for _, parentRow := range parentRows {
		paths, err := s.CataloguePaths(ctx, parentRow.ParentID, options)
		if err != nil {
			return nil, err
		}

		for _, path := range paths {
			switch path.Type {
			case CataloguePathResultTypeBrand:
				result = append(result, CataloguePathResult{
					Type:         CataloguePathResultTypeBrandItem,
					BrandCatname: path.BrandCatname,
					CarCatname:   parentRow.Catname,
					Path:         []string{},
					Stock:        parentRow.Type == schema.ItemParentTypeDefault,
				})

			case CataloguePathResultTypeBrandItem:
				isStock := path.Stock && (parentRow.Type == schema.ItemParentTypeDefault)
				result = append(result, CataloguePathResult{
					Type:         path.Type,
					BrandCatname: path.BrandCatname,
					CarCatname:   path.CarCatname,
					Path:         append(path.Path, parentRow.Catname),
					Stock:        isStock,
				})
			default:
			}
		}

		if stockFirst {
			slices.SortFunc(result, func(aItem, bItem CataloguePathResult) int {
				if aItem.Stock {
					if bItem.Stock {
						return 0
					}

					return -1
				}

				if bItem.Stock {
					return 1
				}

				return 0
			})
		}

		if breakOnFirst && len(result) > 0 {
			result = []CataloguePathResult{result[0]} // truncate to first
			if stockFirst {
				if result[0].Stock {
					return result, nil
				}
			} else {
				return []CataloguePathResult{result[0]}, nil
			}
		}
	}

	if breakOnFirst && len(result) > 1 {
		result = []CataloguePathResult{result[0]} // truncate to first
	}

	return result, nil
}

type ChildCount struct {
	Type  schema.ItemParentType `db:"type"`
	Count int32                 `db:"count"`
}

func (s *Repository) ChildsCounts(ctx context.Context, id int64) ([]ChildCount, error) {
	res := make([]ChildCount, 0)

	err := s.db.Select(schema.ItemParentTableTypeCol, goqu.COUNT(goqu.Star()).As("count")).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(id)).
		GroupBy(schema.ItemParentTableTypeCol).ScanStructsContext(ctx, &res)

	return res, err
}

func (s *Repository) ItemParentCacheSelect(
	options *query.ItemParentCacheListOptions,
) (*goqu.SelectDataset, error) {
	alias := query.ItemParentCacheAlias
	aliasTable := goqu.T(alias)

	sqSelect, err := options.Select(s.db, alias)
	if err != nil {
		return nil, err
	}

	return sqSelect.Select(
		aliasTable.Col(schema.ItemParentCacheTableItemIDColName),
		aliasTable.Col(schema.ItemParentCacheTableParentIDColName),
	), nil
}

func (s *Repository) ItemParentCaches(
	ctx context.Context, options *query.ItemParentCacheListOptions,
) ([]*schema.ItemParentCacheRow, error) {
	var rows []*schema.ItemParentCacheRow

	sqSelect, err := s.ItemParentCacheSelect(options)
	if err != nil {
		return nil, err
	}

	err = sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) LinksSelect(options *query.LinkListOptions) (*goqu.SelectDataset, error) {
	aliasTable := goqu.T(query.LinkAlias)

	sqSelect, err := options.Select(s.db, query.LinkAlias)
	if err != nil {
		return nil, err
	}

	sqSelect = sqSelect.Select(
		aliasTable.Col(
			schema.ItemLinkTableIDColName,
		),
		aliasTable.Col(schema.ItemLinkTableNameColName),
		aliasTable.Col(
			schema.ItemLinkTableURLColName,
		),
		aliasTable.Col(schema.ItemLinkTableTypeColName),
		aliasTable.Col(schema.ItemLinkTableItemIDColName),
	)

	if !options.IsIDUnique() {
		sqSelect = sqSelect.GroupBy(aliasTable.Col(schema.ItemLinkTableIDColName))
	}

	return sqSelect, nil
}

func (s *Repository) LinksCount(
	ctx context.Context,
	options *query.LinkListOptions,
) (int32, error) {
	var count int

	sqSelect, err := options.CountSelect(s.db, query.LinkAlias)
	if err != nil {
		return 0, err
	}

	success, err := sqSelect.Executor().ScanValContext(ctx, &count)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return int32(count), nil //nolint: gosec
}

func (s *Repository) Links(
	ctx context.Context,
	options *query.LinkListOptions,
) ([]*schema.LinkRow, error) {
	var rows []*schema.LinkRow

	sqSelect, err := s.LinksSelect(options)
	if err != nil {
		return nil, err
	}

	err = sqSelect.Executor().ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	return rows, nil
}

func (s *Repository) Link(
	ctx context.Context,
	options *query.LinkListOptions,
) (*schema.LinkRow, error) {
	var row schema.LinkRow

	sqSelect, err := s.LinksSelect(options)
	if err != nil {
		return nil, err
	}

	success, err := sqSelect.Limit(1).Executor().ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	return &row, nil
}

func (s *Repository) HasFullText(ctx context.Context, itemID int64) (bool, error) {
	var res bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.ItemLanguageTable).
		Join(schema.TextstorageTextTable, goqu.On(
			schema.ItemLanguageTableFullTextIDCol.Eq(schema.TextstorageTextTableIDCol)),
		).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(itemID),
			goqu.L("? > 0", goqu.Func("LENGTH", schema.TextstorageTextTableTextCol)),
		).ScanValContext(ctx, &res)

	return success && res, err
}

func (s *Repository) ChildItemsID(ctx context.Context, itemID int64) ([]int64, error) {
	var res []int64

	err := s.db.Select(schema.ItemParentTableItemIDCol).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(itemID)).
		ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) AncestorsID(
	ctx context.Context, itemID int64, itemTypes []schema.ItemTableItemTypeID,
) ([]int64, error) {
	var res []int64

	err := s.db.Select(schema.ItemParentCacheTableParentIDCol).
		From(schema.ItemParentCacheTable).
		Join(schema.ItemTable, goqu.On(schema.ItemParentCacheTableParentIDCol.Eq(schema.ItemTableIDCol))).
		Where(
			schema.ItemParentCacheTableItemIDCol.Eq(itemID),
			schema.ItemParentCacheTableItemIDCol.Neq(schema.ItemParentCacheTableParentIDCol),
			schema.ItemTableItemTypeIDCol.In(itemTypes),
		).
		Order(schema.ItemParentCacheTableDiffCol.Desc()).
		ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) RelatedCarGroups(
	ctx context.Context,
	itemID int64,
) (map[int64][]int64, error) {
	type Vector struct {
		Parents []int64
		Childs  []int64
	}

	carIDs, err := s.ChildItemsID(ctx, itemID)
	if err != nil {
		return nil, err
	}

	vectors := make([]Vector, 0, len(carIDs))

	for _, carID := range carIDs {
		parentIDs, err := s.AncestorsID(ctx, carID, []schema.ItemTableItemTypeID{
			schema.ItemTableItemTypeIDVehicle,
			schema.ItemTableItemTypeIDEngine,
		})
		if err != nil {
			return nil, err
		}

		// remove parents
		for _, parentID := range parentIDs {
			index := slices.Index(carIDs, parentID)
			if index != -1 {
				// remove element `index` by replacing it with last element
				carIDs[index] = carIDs[len(carIDs)-1]
				carIDs = carIDs[:len(carIDs)-1]
			}
		}

		vector := parentIDs
		vector = append(vector, carID)

		vectors = append(vectors, Vector{
			Parents: vector,
			Childs:  []int64{carID},
		})
	}

	for {
		// look for same root
		matched := false
		for i := 0; (i < len(vectors)-1) && !matched; i++ {
			for j := i + 1; j < len(vectors) && !matched; j++ {
				if vectors[i].Parents[0] == vectors[j].Parents[0] {
					matched = true
					// matched root
					length := min(len(vectors[i].Parents), len(vectors[j].Parents))
					newVector := make([]int64, 0, length)

					for k := 0; k < length && vectors[i].Parents[k] == vectors[j].Parents[k]; k++ {
						newVector = append(newVector, vectors[i].Parents[k])
					}

					vectors[i] = Vector{
						Parents: newVector,
						Childs:  append(vectors[i].Childs, vectors[j].Childs...),
					}

					// remove element j by replacing it with last element
					vectors[j] = vectors[len(vectors)-1]
					vectors = vectors[:len(vectors)-1]
				}
			}
		}

		if !matched {
			break
		}
	}

	result := make(map[int64][]int64, len(vectors))

	for _, vector := range vectors {
		carID := vector.Parents[len(vector.Parents)-1]
		result[carID] = vector.Childs
	}

	return result, nil
}

func (s *Repository) EngineVehiclesGroups(
	ctx context.Context, engineID int64, groupJoinLimit int,
) ([]int64, error) {
	var vehicleIDs []int64

	err := s.db.Select(schema.ItemTableIDCol).
		From(schema.ItemTable).
		Join(schema.ItemParentCacheTable, goqu.On(
			schema.ItemTableEngineItemIDCol.Eq(schema.ItemParentCacheTableItemIDCol)),
		).
		Where(schema.ItemParentCacheTableParentIDCol.Eq(engineID)).
		ScanValsContext(ctx, &vehicleIDs)
	if err != nil {
		return nil, err
	}

	vectors := make([][]int64, 0, len(vehicleIDs))

	for _, vehicleID := range vehicleIDs {
		var parentIDs []int64

		err = s.db.Select(schema.ItemParentCacheTableParentIDCol).
			From(schema.ItemParentCacheTable).
			Join(schema.ItemTable, goqu.On(schema.ItemParentCacheTableParentIDCol.Eq(schema.ItemTableIDCol))).
			Where(
				schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDVehicle),
				schema.ItemParentCacheTableItemIDCol.Eq(vehicleID),
				schema.ItemParentCacheTableItemIDCol.Neq(schema.ItemParentCacheTableParentIDCol),
			).
			Order(schema.ItemParentCacheTableDiffCol.Desc()).
			ScanValsContext(ctx, &parentIDs)
		if err != nil {
			return nil, err
		}

		// remove parents
		for _, parentID := range parentIDs {
			index := slices.Index(vehicleIDs, parentID)
			if index != -1 {
				vehicleIDs = append(vehicleIDs[:index], vehicleIDs[index+1:]...)
			}
		}

		vector := parentIDs
		vector = append(vector, vehicleID)

		vectors = append(vectors, vector)
	}

	if groupJoinLimit > 0 && len(vehicleIDs) <= groupJoinLimit {
		return vehicleIDs, nil
	}

	for {
		matched := false
		// look for same root
		for i := 0; i < (len(vectors)-1) && !matched; i++ {
			for j := i + 1; j < len(vectors) && !matched; j++ {
				if vectors[i][0] == vectors[j][0] {
					matched = true
					// matched root
					newVector := make([]int64, 0)
					length := min(len(vectors[i]), len(vectors[j]))

					for k := 0; k < length && vectors[i][k] == vectors[j][k]; k++ {
						newVector = append(newVector, vectors[i][k])
					}

					vectors[i] = newVector
					vectors = append(vectors[:j], vectors[j+1:]...)
				}
			}
		}

		if !matched {
			break
		}
	}

	resultIDs := make([]int64, 0, len(vectors))
	for _, vector := range vectors {
		resultIDs = append(resultIDs, vector[len(vector)-1])
	}

	return resultIDs, nil
}

func fractionToMonth(fraction sql.NullString) time.Month {
	if !fraction.Valid {
		return fractionDefaultMonth
	}

	switch fraction.String {
	case "¼":
		return fractionDefaultMonth + fractionQuarterMonths
	case "½":
		return fractionDefaultMonth + fractionTwoQuarterMonths
	case "¾":
		return fractionDefaultMonth + fractionThreeQuarterMonths
	}

	return fractionDefaultMonth
}

func (s *Repository) RebuildItemOrderCache(ctx context.Context) error {
	logging.Infoln("RebuildItemOrderCache()")

	const batchSize = 100

	for page := 1; ; page++ {
		list, pages, err := s.List(ctx, &query.ItemListOptions{
			Limit: batchSize,
			Page:  uint32(page),
		}, nil, OrderByIDAsc, true)
		if err != nil {
			return err
		}

		for _, i := range list {
			logging.Infof("UpdateOrderCache(%d)", i.ID)

			_, err = s.UpdateOrderCache(ctx, i.ID)
			if err != nil {
				return err
			}
		}

		if pages.PageCount == int32(page) {
			break
		}
	}

	return nil
}

func (s *Repository) UpdateOrderCache(ctx context.Context, itemID int64) (bool, error) {
	var row schema.ItemRow

	success, err := s.db.Select(
		schema.ItemTableBeginYearCol, schema.ItemTableBeginMonthCol,
		schema.ItemTableBeginModelYearCol, schema.ItemTableBeginModelYearFractionCol,
		schema.ItemTableEndYearCol, schema.ItemTableEndMonthCol,
		schema.ItemTableEndModelYearCol, schema.ItemTableEndModelYearFractionCol,
	).
		From(schema.ItemTable).
		Where(schema.ItemTableIDCol.Eq(itemID)).
		ScanStructContext(ctx, &row)
	if err != nil {
		return false, err
	}

	if !success {
		return false, nil
	}

	begin := civil.Date{
		Day: 1,
	}

	switch {
	case row.BeginYear.Valid && row.BeginYear.Int32 > 0:
		begin.Year = int(row.BeginYear.Int32)
		begin.Month = time.January

		if row.BeginMonth.Valid && row.BeginMonth.Int16 > 0 {
			begin.Month = time.Month(row.BeginMonth.Int16)
		}
	case row.BeginModelYear.Valid && row.BeginModelYear.Int32 > 0:
		begin.Year = int(row.BeginModelYear.Int32) - 1
		begin.Month = fractionToMonth(row.BeginModelYearFraction)
	default:
		begin.Year = 2100
		begin.Month = time.January
	}

	end := civil.Date{
		Day: 1,
	}

	switch {
	case row.EndYear.Valid && row.EndYear.Int32 > 0:
		end.Year = int(row.EndYear.Int32)
		end.Month = time.December

		if row.EndMonth.Valid && row.EndMonth.Int16 > 0 {
			end.Month = time.Month(row.EndMonth.Int16)
		}

		end = end.AddMonths(1).AddDays(-1)
	case row.EndModelYear.Valid && row.EndModelYear.Int32 > 0:
		end.Year = int(row.EndModelYear.Int32)
		end.Month = fractionToMonth(row.EndModelYearFraction) - 1

		if row.EndMonth.Valid && row.EndMonth.Int16 > 0 {
			end.Month = time.Month(row.EndMonth.Int16)
		}

		end = end.AddMonths(1).AddDays(-1)
	default:
		end = begin
	}

	// normalize
	begin = begin.AddDays(0)
	end = end.AddDays(0)

	_, err = s.db.Update(schema.ItemTable).Set(goqu.Record{
		schema.ItemTableBeginOrderCacheColName: begin.String(),
		schema.ItemTableEndOrderCacheColName:   end.String(),
	}).Where(schema.ItemTableIDCol.Eq(itemID)).Executor().ExecContext(ctx)

	return true, err
}

func (s *Repository) FirstCharacters(ctx context.Context) ([]string, error) {
	var res []string

	err := s.db.Select(goqu.Func("UPPER", goqu.Func("LEFT", schema.ItemLanguageTableNameCol, 1)).As("char")).
		Distinct().
		From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableNameCol.IsNotNull(),
			schema.ItemLanguageTableNameCol.Neq(""),
		).
		Order(goqu.C("char").Asc()).
		ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) CreateItem(
	ctx context.Context,
	row schema.ItemRow,
	userID int64,
) (int64, error) {
	var itemID int64

	success, err := s.db.Insert(schema.ItemTable).
		Rows(row).
		Returning(schema.ItemTableIDCol).
		Executor().
		ScanValContext(ctx, &itemID)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, errNoRowsReturned
	}

	if len(row.Name) > 0 {
		err = s.setItemLanguageName(ctx, itemID, schema.DefaultLanguageCode, row.Name)
		if err != nil {
			return 0, err
		}
	}

	_, err = s.UpdateOrderCache(ctx, itemID)
	if err != nil {
		return 0, err
	}

	_, err = s.itemParentCache.RebuildCache(ctx, itemID)
	if err != nil {
		return 0, err
	}

	err = s.RefreshItemVehicleTypeInheritanceFromParents(ctx, itemID)
	if err != nil {
		return 0, err
	}

	err = s.UserItemSubscribe(ctx, itemID, userID)
	if err != nil {
		return 0, err
	}

	err = s.UpdateInheritance(ctx, itemID)
	if err != nil {
		return 0, err
	}

	return itemID, nil
}

func (s *Repository) SpecExists(ctx context.Context, specID int32) (bool, error) {
	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.SpecTable).
		Where(schema.SpecTableIDCol.Eq(specID)).
		ScanValContext(ctx, &exists)

	return success && exists, err
}

func (s *Repository) UpdateItem(
	ctx context.Context,
	row schema.ItemRow,
	mask []string,
	userID int64,
) error {
	set := goqu.Record{}
	subscribe := false

	if util.Contains(mask, "name") {
		subscribe = true
		set[schema.ItemTableNameColName] = row.Name
	}

	if util.Contains(mask, "full_name") {
		subscribe = true
		set[schema.ItemTableFullNameColName] = row.FullName
	}

	if util.Contains(mask, "body") {
		subscribe = true
		set[schema.ItemTableBodyColName] = row.Body
	}

	if util.Contains(mask, "begin_year") {
		subscribe = true
		set[schema.ItemTableBeginYearColName] = row.BeginYear
	}

	if util.Contains(mask, "begin_month") {
		subscribe = true
		set[schema.ItemTableBeginMonthColName] = row.BeginMonth
	}

	if util.Contains(mask, "end_year") {
		subscribe = true
		set[schema.ItemTableEndYearColName] = row.EndYear
	}

	if util.Contains(mask, "end_month") {
		subscribe = true
		set[schema.ItemTableEndMonthColName] = row.EndMonth
	}

	if util.Contains(mask, "today") {
		subscribe = true

		set[schema.ItemTableTodayColName] = row.Today
	}

	if util.Contains(mask, "begin_model_year") {
		subscribe = true
		set[schema.ItemTableBeginModelYearColName] = row.BeginModelYear
	}

	if util.Contains(mask, "end_model_year") {
		subscribe = true
		set[schema.ItemTableEndModelYearColName] = row.EndModelYear
	}

	if util.Contains(mask, "begin_model_year_fraction") {
		subscribe = true
		set[schema.ItemTableBeginModelYearFractionColName] = row.BeginModelYearFraction
	}

	if util.Contains(mask, "end_model_year_fraction") {
		subscribe = true
		set[schema.ItemTableEndModelYearFractionColName] = row.EndModelYearFraction
	}

	if util.Contains(mask, "is_concept") {
		subscribe = true
		set[schema.ItemTableIsConceptColName] = row.IsConcept
	}

	if util.Contains(mask, "is_concept_inherit") {
		subscribe = true
		set[schema.ItemTableIsConceptInheritColName] = row.IsConceptInherit
	}

	if util.Contains(mask, "catname") {
		subscribe = true
		set[schema.ItemTableCatnameColName] = row.Catname
	}

	if util.Contains(mask, "produced") {
		subscribe = true
		set[schema.ItemTableProducedColName] = row.Produced
	}

	if util.Contains(mask, "produced_exactly") {
		subscribe = true
		set[schema.ItemTableProducedExactlyColName] = row.ProducedExactly
	}

	if util.Contains(mask, "is_group") {
		subscribe = true
		set[schema.ItemTableIsGroupColName] = row.IsGroup
	}

	if util.Contains(mask, "spec_inherit") {
		subscribe = true
		set[schema.ItemTableSpecInheritColName] = row.SpecInherit
	}

	if util.Contains(mask, "spec_id") {
		subscribe = true
		set[schema.ItemTableSpecIDColName] = row.SpecID
	}

	if util.Contains(mask, "engine_inherit") {
		subscribe = true
		set[schema.ItemTableEngineInheritColName] = row.EngineInherit
	}

	if util.Contains(mask, "engine_item_id") {
		subscribe = true
		set[schema.ItemTableEngineItemIDColName] = row.EngineItemID
	}

	ctx = context.WithoutCancel(ctx)

	if len(set) > 0 {
		_, err := s.db.Update(schema.ItemTable).
			Set(set).
			Where(schema.ItemTableIDCol.Eq(row.ID)).
			Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	if util.Contains(mask, "name") {
		err := s.setItemLanguageName(ctx, row.ID, schema.DefaultLanguageCode, row.Name)
		if err != nil {
			return err
		}
	}

	err := s.UpdateInheritance(ctx, row.ID)
	if err != nil {
		return err
	}

	_, err = s.UpdateOrderCache(ctx, row.ID)
	if err != nil {
		return err
	}

	_, err = s.RefreshAutoByVehicle(ctx, row.ID)
	if err != nil {
		return err
	}

	if subscribe {
		err = s.UserItemSubscribe(ctx, row.ID, userID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) Spec(ctx context.Context, id int32) (*schema.SpecRow, error) {
	var st schema.SpecRow

	success, err := s.db.Select(schema.SpecTableIDCol, schema.SpecTableNameCol, schema.SpecTableShortNameCol).
		From(schema.SpecTable).
		Where(schema.SpecTableIDCol.Eq(id)).
		ScanStructContext(ctx, &st)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrItemNotFound
	}

	return &st, nil
}

func (s *Repository) SetItemLogo(ctx context.Context, itemID int64, file io.ReadSeeker) error {
	if itemID <= 0 {
		return ErrItemNotFound
	}

	item, err := s.Item(ctx, &query.ItemListOptions{
		ItemID: itemID,
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
	}, nil)
	if err != nil {
		return err
	}

	oldImageID := item.LogoID

	ctx = context.WithoutCancel(ctx)

	imageID, err := s.imageStorage.AddImageFromReader(ctx, file, "brand", storage.GenerateOptions{
		Pattern: item.Catname.String,
	})
	if err != nil {
		return err
	}

	_, err = s.db.Update(schema.ItemTable).
		Set(goqu.Record{
			schema.ItemTableLogoIDColName: imageID,
		}).
		Where(schema.ItemTableIDCol.Eq(item.ID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	if oldImageID.Valid {
		err = s.imageStorage.RemoveImage(ctx, int(oldImageID.Int64))
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) columnsByFields(fields *ItemFields) map[string]Column {
	columns := map[string]Column{
		schema.ItemTableIDColName:               s.idColumn,
		schema.ItemTableCatnameColName:          s.catnameColumn,
		schema.ItemTableEngineItemIDColName:     s.engineItemIDColumn,
		schema.ItemTableEngineInheritColName:    s.engineInheritColumn,
		schema.ItemTableItemTypeIDColName:       s.itemTypeIDColumn,
		schema.ItemTableIsConceptColName:        s.isConceptColumn,
		schema.ItemTableIsConceptInheritColName: s.isConceptInheritColumn,
		schema.ItemTableSpecIDColName:           s.specIDColumn,
		schema.ItemTableSpecInheritColName:      s.specInheritColumn,
		schema.ItemTableIsGroupColName:          s.isGroupColumn,
		schema.ItemTableProducedColName:         s.producedColumn,
		schema.ItemTableProducedExactlyColName:  s.producedExactlyColumn,
		schema.ItemTableLogoIDColName:           s.logoColumn,
	}

	if fields == nil {
		return columns
	}

	if fields.FullName || fields.Meta {
		columns[schema.ItemTableFullNameColName] = s.fullNameColumn
	}

	if fields.Logo {
		columns[schema.ItemTableLogoIDColName] = s.logoColumn
	}

	if fields.Meta {
		columns[schema.ItemTableNameColName] = s.nameColumn
	}

	if fields.NameText || fields.NameHTML || fields.Meta {
		columns[schema.ItemTableBeginYearColName] = s.beginYearColumn
		columns[schema.ItemTableEndYearColName] = s.endYearColumn
		columns[schema.ItemTableBeginMonthColName] = s.beginMonthColumn
		columns[schema.ItemTableEndMonthColName] = s.endMonthColumn
		columns[schema.ItemTableBeginModelYearColName] = s.beginModelYearColumn
		columns[schema.ItemTableEndModelYearColName] = s.endModelYearColumn
		columns[schema.ItemTableBeginModelYearFractionColName] = s.beginModelYearFractionColumn
		columns[schema.ItemTableEndModelYearFractionColName] = s.endModelYearFractionColumn
		columns[schema.ItemTableTodayColName] = s.todayColumn
		columns[schema.ItemTableBodyColName] = s.bodyColumn

		if fields.NameText || fields.NameHTML {
			columns[colSpecShortName] = s.specShortNameColumn
		}
	}

	if fields.NameHTML {
		columns[colSpecName] = s.specNameColumn
	}

	if fields.Description {
		columns[colDescription] = s.descriptionColumn
	}

	if fields.FullText {
		columns[colFullText] = s.fullTextColumn
	}

	if fields.NameOnly || fields.NameText || fields.NameHTML || fields.Meta {
		columns[colNameOnly] = s.nameOnlyColumn
	}

	if fields.NameDefault {
		columns[colNameDefault] = s.nameDefaultColumn
	}

	if fields.ChildItemsCount {
		columns[colChildItemsCount] = s.childItemsCountColumn
	}

	if fields.NewChildItemsCount {
		columns[colNewChildItemsCount] = s.newChildItemsCountColumn
	}

	if fields.DescendantsParentsCount {
		columns[colDescendantsParentsCount] = s.descendantsParentsCountColumn
	}

	if fields.NewDescendantsParentsCount {
		columns[colNewDescendantsParentsCount] = s.newDescendantsParentsCountColumn
	}

	if fields.ChildsCount {
		columns[colChildsCount] = s.childsCountColumn
	}

	if fields.ParentsCount {
		columns[colParentsCount] = s.parentsCountColumn
	}

	if fields.DescendantsCount {
		columns[colDescendantsCount] = s.descendantsCountColumn
	}

	if fields.NewDescendantsCount {
		columns[colNewDescendantsCount] = s.newDescendantsCountColumn
	}

	if fields.DescendantTwinsGroupsCount {
		columns[colDescendantTwinsGroupsCount] = s.descendantTwinsGroupsCountColumn
	}

	if fields.MostsActive {
		columns[colMostsActive] = s.mostsActiveColumn
	}

	if fields.DescendantPicturesCount {
		columns[colDescendantPicturesCount] = s.descendantPicturesCountColumn
	}

	if fields.InboxPicturesCount {
		columns[colInboxPicturesCount] = s.inboxPicturesCountColumn
	}

	if fields.AcceptedPicturesCount {
		columns[colAcceptedPicturesCount] = s.acceptedPicturesCountColumn
	}

	if fields.ExactPicturesCount {
		columns[colExactPicturesCount] = s.exactPicturesCountColumn
	}

	if fields.CommentsAttentionsCount {
		columns[colCommentsAttentionsCount] = s.commentsAttentionsCountColumn
	}

	if fields.HasChildSpecs {
		columns[colHasChildSpecs] = s.hasChildSpecsColumn
	}

	if fields.HasSpecs {
		columns[colHasSpecs] = s.hasSpecsColumn
	}

	return columns
}

func (s *Repository) isFieldsValid(
	options *query.ItemListOptions,
	fields *ItemFields,
	orderBy OrderBy,
) error {
	if fields == nil {
		return nil
	}

	if (fields.ChildItemsCount || fields.NewChildItemsCount) && options.ItemParentChild == nil {
		return fmt.Errorf(
			"%w: ChildItemsCount, NewChildItemsCount requires ItemParentChild",
			errFieldRequires,
		)
	}

	if fields.DescendantPicturesCount && (options.ItemParentCacheDescendant == nil ||
		options.ItemParentCacheDescendant.PictureItemsByItemID == nil) {
		return fmt.Errorf(
			"%w: DescendantPicturesCount requires ItemParentCacheDescendant.PictureItemsByItemID",
			errFieldRequires,
		)
	}

	if (fields.DescendantsParentsCount || fields.NewDescendantsParentsCount) &&
		(options.ItemParentCacheDescendant == nil || options.ItemParentCacheDescendant.ItemParentByItemID == nil) {
		return fmt.Errorf(
			"%w: (New)DescendantsParentsCount requires ItemParentCacheDescendant.ItemParentByItemID",
			errFieldRequires,
		)
	}

	if orderBy == OrderByAttrsUserValuesUpdateDate && options.AttrsUserValues == nil {
		return fmt.Errorf(
			"%w: OrderByAttrsUserValuesUpdateDate requires AttrsUserValues",
			errFieldRequires,
		)
	}

	return nil
}

func (s *Repository) orderBy(
	alias string,
	orderBy OrderBy,
	lang string,
) ([]exp.OrderedExpression, error) {
	type columnOrder struct {
		col Column
		asc bool
	}

	var columns []columnOrder

	switch orderBy {
	case OrderByDescendantsCount:
		columns = []columnOrder{{col: s.descendantsCountColumn, asc: false}}
	case OrderByChildsCount:
		columns = []columnOrder{{col: s.childsCountColumn, asc: false}}
	case OrderByDescendantPicturesCount:
		columns = []columnOrder{{col: s.descendantPicturesCountColumn, asc: false}}
	case OrderByCreatedAt:
		columns = []columnOrder{{col: s.createdAtColumn, asc: false}, {col: s.idColumn, asc: false}}
	case OrderByName:
		columns = []columnOrder{
			{col: s.nameColumn, asc: true},
			{col: s.bodyColumn, asc: true},
			{col: s.specIDColumn, asc: true},
			{col: s.beginOrderCacheColumn, asc: true},
			{col: s.endOrderCacheColumn, asc: true},
		}
	case OrderByDescendantsParentsCount:
		columns = []columnOrder{{col: s.descendantsParentsCountColumn, asc: false}}
	case OrderByStarCount:
		columns = []columnOrder{{col: s.starCountColumn, asc: false}}
	case OrderByItemParentParentTimestamp:
		columns = []columnOrder{{col: s.itemParentParentTimestampColumn, asc: false}}
	case OrderByAge:
		columns = []columnOrder{
			{col: s.beginOrderCacheColumn, asc: true},
			{col: s.endOrderCacheColumn, asc: true},
			{col: s.nameColumn, asc: true},
			{col: s.bodyColumn, asc: true},
			{col: s.specIDColumn, asc: true},
		}
	case OrderByIDDesc:
		columns = []columnOrder{{col: s.idColumn, asc: false}}
	case OrderByIDAsc:
		columns = []columnOrder{{col: s.idColumn, asc: true}}
	case OrderByAttrsUserValuesUpdateDate:
		columns = []columnOrder{{col: s.attrsUserValuesUpdateDateColumn, asc: false}}
	case OrderByNone:
	}

	orderByExp := make([]exp.OrderedExpression, 0, len(columns))

	for _, column := range columns {
		expr, err := column.col.SelectExpr(alias, lang)
		if err != nil {
			return nil, err
		}

		ordExpr := expr.Desc()
		if column.asc {
			ordExpr = expr.Asc()
		}

		orderByExp = append(orderByExp, ordExpr)
	}

	return orderByExp, nil
}

func (s *Repository) wrapperOrderBy(
	wrapperAlias string,
	wrappedAlias string,
	orderBy OrderBy,
) ([]exp.OrderedExpression, []interface{}) {
	wrapperAliasTable := goqu.T(wrapperAlias)
	wrappedAliasTable := goqu.T(wrappedAlias)

	switch orderBy {
	case OrderByDescendantsCount:
		col := wrappedAliasTable.Col(colDescendantsCount)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByChildsCount:
		col := wrappedAliasTable.Col(colChildsCount)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByDescendantPicturesCount:
		col := wrappedAliasTable.Col(colDescendantPicturesCount)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByCreatedAt:
		return []exp.OrderedExpression{
			wrapperAliasTable.Col(schema.ItemTableCreatedAtColName).Desc(),
			wrapperAliasTable.Col(schema.ItemTableIDColName).Desc(),
		}, []interface{}{}
	case OrderByName:
		return []exp.OrderedExpression{
			wrapperAliasTable.Col(schema.ItemTableNameColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableBodyColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
		}, []interface{}{}
	case OrderByDescendantsParentsCount:
		col := wrappedAliasTable.Col(colDescendantsParentsCount)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByStarCount:
		col := wrappedAliasTable.Col(colStarCount)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByItemParentParentTimestamp:
		col := wrappedAliasTable.Col(colItemParentParentTimestamp)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByAge:
		return []exp.OrderedExpression{
			wrapperAliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableNameColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableBodyColName).Asc(),
			wrapperAliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
		}, []interface{}{}
	case OrderByIDDesc:
		col := wrappedAliasTable.Col(schema.ItemTableIDColName)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByIDAsc:
		col := wrappedAliasTable.Col(schema.ItemTableIDColName)

		return []exp.OrderedExpression{col.Asc()}, []interface{}{col}
	case OrderByAttrsUserValuesUpdateDate:
		col := wrappedAliasTable.Col(colAttrsUserValuesUpdateDate)

		return []exp.OrderedExpression{col.Desc()}, []interface{}{col}
	case OrderByNone:
	}

	return nil, []interface{}{}
}

func (s *Repository) wrappedOrderBy(alias string, orderBy OrderBy) []exp.OrderedExpression {
	aliasTable := goqu.T(alias)

	var orderByExp []exp.OrderedExpression

	switch orderBy {
	case OrderByDescendantsCount:
		orderByExp = []exp.OrderedExpression{goqu.C(colDescendantsCount).Desc()}
	case OrderByChildsCount:
		orderByExp = []exp.OrderedExpression{goqu.C(colChildsCount).Desc()}
	case OrderByDescendantPicturesCount:
		orderByExp = []exp.OrderedExpression{goqu.C(colDescendantPicturesCount).Desc()}
	case OrderByCreatedAt:
		orderByExp = []exp.OrderedExpression{
			aliasTable.Col(schema.ItemTableCreatedAtColName).Desc(),
			aliasTable.Col(schema.ItemTableIDColName).Desc(),
		}
	case OrderByName:
		orderByExp = []exp.OrderedExpression{
			aliasTable.Col(schema.ItemTableNameColName).Asc(),
			aliasTable.Col(schema.ItemTableBodyColName).Asc(),
			aliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
			aliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			aliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
		}
	case OrderByDescendantsParentsCount:
		orderByExp = []exp.OrderedExpression{goqu.C(colDescendantsParentsCount).Desc()}
	case OrderByStarCount:
		orderByExp = []exp.OrderedExpression{goqu.C(colStarCount).Desc()}
	case OrderByItemParentParentTimestamp:
		orderByExp = []exp.OrderedExpression{goqu.C(colItemParentParentTimestamp).Desc()}
	case OrderByAge:
		orderByExp = []exp.OrderedExpression{
			aliasTable.Col(schema.ItemTableBeginOrderCacheColName).Asc(),
			aliasTable.Col(schema.ItemTableEndOrderCacheColName).Asc(),
			aliasTable.Col(schema.ItemTableNameColName).Asc(),
			aliasTable.Col(schema.ItemTableBodyColName).Asc(),
			aliasTable.Col(schema.ItemTableSpecIDColName).Asc(),
		}
	case OrderByIDDesc:
		orderByExp = []exp.OrderedExpression{aliasTable.Col(schema.ItemTableIDColName).Desc()}
	case OrderByIDAsc:
		orderByExp = []exp.OrderedExpression{aliasTable.Col(schema.ItemTableIDColName).Asc()}
	case OrderByAttrsUserValuesUpdateDate:
		orderByExp = []exp.OrderedExpression{goqu.C(colAttrsUserValuesUpdateDate).Desc()}
	case OrderByNone:
	}

	return orderByExp
}

func (s *Repository) wrappedSelectColumns(orderBy OrderBy) map[string]Column {
	columns := map[string]Column{
		schema.ItemTableIDColName: s.idColumn,
	}

	switch orderBy {
	case OrderByDescendantsCount:
		columns[colDescendantsCount] = s.descendantsCountColumn
	case OrderByChildsCount:
		columns[colChildsCount] = s.childsCountColumn
	case OrderByDescendantPicturesCount:
		columns[colDescendantPicturesCount] = s.descendantPicturesCountColumn
	case OrderByDescendantsParentsCount:
		columns[colDescendantsParentsCount] = s.descendantsParentsCountColumn
	case OrderByStarCount:
		columns[colStarCount] = s.starCountColumn
	case OrderByItemParentParentTimestamp:
		columns[colItemParentParentTimestamp] = s.itemParentParentTimestampColumn
	case OrderByAttrsUserValuesUpdateDate:
		columns[colAttrsUserValuesUpdateDate] = s.attrsUserValuesUpdateDateColumn
	case OrderByName, OrderByCreatedAt, OrderByAge, OrderByIDDesc, OrderByIDAsc, OrderByNone:
	}

	return columns
}

func (s *Repository) setItemVehicleTypeRow(
	ctx context.Context,
	itemID int64,
	vehicleTypeID int64,
	inherited bool,
) (bool, error) {
	res, err := s.db.Insert(schema.ItemVehicleTypeTable).Rows(goqu.Record{
		schema.ItemVehicleTypeTableItemIDColName:        itemID,
		schema.ItemVehicleTypeTableVehicleTypeIDColName: vehicleTypeID,
		schema.ItemVehicleTypeTableInheritedColName:     inherited,
	}).OnConflict(goqu.DoUpdate(
		schema.ItemVehicleTypeTableItemIDColName+","+schema.ItemVehicleTypeTableVehicleTypeIDColName,
		goqu.Record{
			schema.ItemVehicleTypeTableInheritedColName: schema.Excluded(schema.ItemVehicleTypeTableInheritedColName),
		},
	)).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, nil
}

func (s *Repository) refreshItemVehicleTypeInheritance(ctx context.Context, itemID int64) error {
	var ids []int64

	err := s.db.Select(schema.ItemParentTableItemIDCol).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(itemID)).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, childID := range ids {
		err = s.RefreshItemVehicleTypeInheritanceFromParents(ctx, childID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) getItemVehicleTypeIDs(
	ctx context.Context,
	itemID int64,
	inherited bool,
) ([]int64, error) {
	sqlSelect := s.db.From(schema.ItemVehicleTypeTable).
		Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).
		Where(schema.ItemVehicleTypeTableItemIDCol.Eq(itemID))
	if inherited {
		sqlSelect = sqlSelect.Where(schema.ItemVehicleTypeTableInheritedCol.IsTrue())
	} else {
		sqlSelect = sqlSelect.Where(schema.ItemVehicleTypeTableInheritedCol.IsFalse())
	}

	res := make([]int64, 0)

	err := sqlSelect.ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) getItemVehicleTypeInheritedIDs(
	ctx context.Context,
	itemID int64,
) ([]int64, error) {
	sqlSelect := s.db.From(schema.ItemVehicleTypeTable).
		Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).Distinct().
		Join(
			schema.ItemParentTable,
			goqu.On(schema.ItemVehicleTypeTableItemIDCol.Eq(schema.ItemParentTableParentIDCol)),
		).
		Where(schema.ItemParentTableItemIDCol.Eq(itemID))

	res := make([]int64, 0)

	err := sqlSelect.ScanValsContext(ctx, &res)

	return res, err
}

func (s *Repository) setItemVehicleTypeRows(
	ctx context.Context,
	itemID int64,
	types []int64,
	inherited bool,
) (bool, error) {
	changed := false
	ctx = context.WithoutCancel(ctx)

	for _, t := range types {
		rowChanged, err := s.setItemVehicleTypeRow(ctx, itemID, t, inherited)
		if err != nil {
			return false, err
		}

		if rowChanged {
			changed = true
		}
	}

	sqlDelete := s.db.From(schema.ItemVehicleTypeTable).Delete().
		Where(schema.ItemVehicleTypeTableItemIDCol.Eq(itemID))

	if len(types) > 0 {
		sqlDelete = sqlDelete.Where(schema.ItemVehicleTypeTableVehicleTypeIDCol.NotIn(types))
	}

	res, err := sqlDelete.Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	deleted, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	if deleted > 0 {
		changed = true
	}

	return changed, nil
}

type parentInfo struct {
	Diff   int64
	Tuning bool
	Sport  bool
	Design bool
}

func (s *Repository) namePreferLanguage(
	ctx context.Context,
	parentID, itemID int64,
	lang string,
) (string, error) {
	res := ""

	success, err := s.db.Select(schema.ItemParentLanguageTableNameCol).
		From(schema.ItemParentLanguageTable).
		Where(
			schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
			schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
			goqu.Func("LENGTH", schema.ItemParentLanguageTableNameCol).Gt(0),
		).
		Order(goqu.L("?", schema.ItemParentLanguageTableLanguageCol.Eq(lang)).Desc()).
		ScanValContext(ctx, &res)
	if err != nil {
		return "", err
	}

	if !success {
		return "", nil
	}

	return res, nil
}

func (s *Repository) extractCatname(
	ctx context.Context,
	brandRow, vehicleRow schema.ItemRow,
) (string, error) {
	var err error

	diffName, err := s.namePreferLanguage(ctx, brandRow.ID, vehicleRow.ID, schema.CatnameLanguageCode)
	if err != nil {
		return "", err
	}

	if len(diffName) == 0 {
		diffName, err = s.itemParentLanguage.ExtractName(ctx, brandRow, vehicleRow, schema.CatnameLanguageCode)
		if err != nil {
			return "", err
		}
	}

	catnameTemplate := filter.SanitizeFilename(diffName)

	i := 0
	allowed := false
	catname := ""

	for !allowed {
		catname = catnameTemplate
		if i > 0 {
			catname = catname + "_" + strconv.Itoa(i)
		}

		allowed, err = s.isAllowedCatname(ctx, vehicleRow.ID, brandRow.ID, catname)
		if err != nil {
			return "", err
		}

		i++
	}

	return catname, nil
}

type sortedColumnMapItem struct {
	key string
	col Column
}

func toSortedColumns(cols map[string]Column) []sortedColumnMapItem {
	res := make([]sortedColumnMapItem, 0, len(cols))
	for colName, col := range cols {
		res = append(res, sortedColumnMapItem{key: colName, col: col})
	}

	sort.SliceStable(res, func(i, j int) bool {
		return res[i].key < res[j].key
	})

	return res
}

func (s *Repository) isAllowedCombination(
	itemTypeID, parentItemTypeID schema.ItemTableItemTypeID,
) bool {
	itemTypes, ok := schema.AllowedTypeCombinations[parentItemTypeID]
	if !ok {
		return false
	}

	return util.Contains(itemTypes, itemTypeID)
}

func (s *Repository) isAllowedCatname(
	ctx context.Context,
	itemID, parentID int64,
	catname string,
) (bool, error) {
	if len(catname) == 0 {
		return false, nil
	}

	if util.Contains(catnameBlacklist, catname) {
		return false, nil
	}

	var exists bool

	success, err := s.db.Select(goqu.V(true)).From(schema.ItemParentTable).Where(
		schema.ItemParentTableParentIDCol.Eq(parentID),
		schema.ItemParentTableCatnameCol.Eq(catname),
		schema.ItemParentTableItemIDCol.Neq(itemID),
	).ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return !success || !exists, nil
}

func (s *Repository) collectAncestorsIDs(ctx context.Context, id int64) ([]int64, error) {
	var (
		toCheck = []int64{id}
		ids     []int64
	)

	for len(toCheck) > 0 {
		ids = append(ids, toCheck...)

		var res []int64

		err := s.db.Select(schema.ItemParentTableParentIDCol).
			From(schema.ItemParentTable).
			Where(schema.ItemParentTableItemIDCol.In(toCheck)).
			ScanValsContext(ctx, &res)
		if err != nil {
			return nil, err
		}

		toCheck = res
	}

	return util.RemoveDuplicate(ids), nil
}

func (s *Repository) updateItemInheritance(ctx context.Context, car schema.ItemRow) error {
	var parents []schema.ItemRow

	err := s.db.Select(
		schema.ItemTableIsConceptCol, schema.ItemTableEngineItemIDCol, schema.ItemTableSpecIDCol,
	).
		From(schema.ItemTable).
		Join(schema.ItemParentTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentTableParentIDCol))).
		Where(schema.ItemParentTableItemIDCol.Eq(car.ID)).
		ScanStructsContext(ctx, &parents)
	if err != nil {
		return err
	}

	somethingChanged := false

	set := goqu.Record{}

	if car.IsConceptInherit {
		isConcept := false

		for _, parent := range parents {
			if parent.IsConcept {
				isConcept = true

				break
			}
		}

		if car.IsConcept != isConcept {
			set[schema.ItemTableIsConceptColName] = isConcept
			somethingChanged = true
		}
	}

	if car.EngineInherit {
		enginesMap := make(map[int64]int)

		for _, parent := range parents {
			engineID := parent.EngineItemID
			if engineID.Valid {
				enginesMap[engineID.Int64]++
			}
		}

		// select top
		selectedID := util.KeyOfMapMaxValue(enginesMap)

		var oldEngineID int64
		if car.EngineItemID.Valid {
			oldEngineID = car.EngineItemID.Int64
		}

		if oldEngineID != selectedID {
			set[schema.ItemTableEngineItemIDColName] = sql.NullInt64{
				Int64: selectedID,
				Valid: selectedID > 0,
			}
			somethingChanged = true
		}
	}

	if car.SpecInherit {
		specsMap := make(map[int32]int)

		for _, parent := range parents {
			specID := parent.SpecID
			if specID.Valid {
				specsMap[specID.Int32]++
			}
		}

		// select top
		selectedID := util.KeyOfMapMaxValue(specsMap)

		var oldSpecID int32
		if car.SpecID.Valid {
			oldSpecID = car.SpecID.Int32
		}

		if oldSpecID != selectedID {
			set[schema.ItemTableSpecIDColName] = sql.NullInt32{
				Int32: selectedID,
				Valid: selectedID > 0,
			}
			somethingChanged = true
		}
	}

	if somethingChanged || !car.VehicleTypeInherit {
		ctx = context.WithoutCancel(ctx)

		if len(set) > 0 {
			_, err = s.db.Update(schema.ItemTable).Set(set).
				Where(schema.ItemTableIDCol.Eq(car.ID)).
				Executor().ExecContext(ctx)
			if err != nil {
				return err
			}
		}

		var childItems []schema.ItemRow

		err = s.db.Select(
			schema.ItemTableIDCol, schema.ItemTableIsConceptCol, schema.ItemTableIsConceptInheritCol,
			schema.ItemTableEngineInheritCol, schema.ItemTableEngineItemIDCol, schema.ItemTableVehicleTypeInheritCol,
			schema.ItemTableSpecInheritCol, schema.ItemTableSpecIDCol,
		).
			From(schema.ItemTable).
			Join(schema.ItemParentTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentTableItemIDCol))).
			Where(schema.ItemParentTableParentIDCol.Eq(car.ID)).
			ScanStructsContext(ctx, &childItems)
		if err != nil {
			return err
		}

		for _, child := range childItems {
			err = s.updateItemInheritance(ctx, child)
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s *Repository) getParentRows(
	ctx context.Context,
	itemID int64,
	stockFirst bool,
) ([]schema.ItemParentRow, error) {
	sqSelect := s.db.Select(schema.ItemParentTableParentIDCol).
		From(schema.ItemParentTable).
		Where(
			schema.ItemParentTableItemIDCol.Eq(itemID),
		)

	if stockFirst {
		sqSelect = sqSelect.Order(
			goqu.L("?", schema.ItemParentTableTypeCol.Eq(schema.ItemParentTypeDefault)).Desc(),
		)
	}

	var rows []schema.ItemParentRow

	err := sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) refreshAuto(ctx context.Context, parentID, itemID int64) (bool, error) {
	err := s.itemParentLanguage.RefreshItemParentLanguage2(ctx, parentID, itemID)
	if err != nil {
		return false, err
	}

	var bvRow schema.ItemParentRow

	success, err := s.db.Select(schema.ItemParentTableManualCatnameCol).
		From(schema.ItemParentTable).
		Where(
			schema.ItemParentTableItemIDCol.Eq(itemID),
			schema.ItemParentTableParentIDCol.Eq(parentID),
		).
		ScanStructContext(ctx, &bvRow)
	if err != nil {
		return false, err
	}

	if !success {
		return false, nil
	}

	if bvRow.ManualCatname {
		return true, nil
	}

	brandRow, err := s.Item(
		ctx,
		&query.ItemListOptions{ItemID: parentID},
		&ItemFields{NameText: true},
	)
	if err != nil {
		return false, err
	}

	vehicleRow, err := s.Item(
		ctx,
		&query.ItemListOptions{ItemID: itemID},
		&ItemFields{NameText: true},
	)
	if err != nil {
		return false, err
	}

	catname, err := s.extractCatname(ctx, brandRow.ItemRow, vehicleRow.ItemRow)
	if err != nil {
		return false, err
	}

	if len(catname) == 0 {
		return false, nil
	}

	_, err = s.db.Update(schema.ItemParentTable).Set(goqu.Record{
		schema.ItemParentTableCatnameColName: catname,
	}).Where(
		schema.ItemParentTableItemIDCol.Eq(itemID),
		schema.ItemParentTableParentIDCol.Eq(parentID),
	).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Repository) setItemLanguageName(
	ctx context.Context,
	itemID int64,
	lang string,
	name string,
) error {
	if itemID == 0 {
		return ErrItemNotFound
	}

	_, err := s.db.Insert(schema.ItemLanguageTable).Rows(goqu.Record{
		schema.ItemLanguageTableItemIDColName:   itemID,
		schema.ItemLanguageTableLanguageColName: lang,
		schema.ItemLanguageTableNameColName: sql.NullString{
			String: name,
			Valid:  len(name) > 0,
		},
	}).OnConflict(goqu.DoUpdate(
		schema.ItemLanguageTableItemIDColName+","+schema.ItemLanguageTableLanguageColName,
		goqu.Record{
			schema.ItemLanguageTableNameColName: schema.Excluded(schema.ItemLanguageTableNameColName),
		},
	)).Executor().ExecContext(ctx)

	return err
}
