package attrs

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"html/template"
	"maps"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
	"github.com/nicksnyder/go-i18n/v2/i18n"
	"golang.org/x/text/language"
	"golang.org/x/text/message"
	"golang.org/x/text/number"
)

type ValuesOrderBy int

var (
	errAttributeNotFound         = errors.New("attribute not found")
	errListOptionFound           = errors.New("listOption not found")
	errInvalidItemID             = errors.New("invalid itemID provided")
	errAttrTypeUnexpected        = errors.New("unexpected attribute type")
	errAttributeTypeNotSupported = errors.New("attribute type not supported")
)

const (
	defaultZoneID int64 = 1
	engineZoneID  int64 = 5
	busZoneID     int64 = 3
)

type ChartDataset struct {
	Title string
	Pairs map[int]Value
}

var (
	busVehicleTypes = []int64{19, 39, 28, 32}
	chartSpecs      = []int32{
		schema.SpecIDNorthAmerica,
		schema.SpecIDWorldwide,
	}
	ChartParameters = []int64{
		schema.LengthAttr,
		schema.WidthAttr,
		schema.HeightAttr,
		schema.MaxSpeedAttr,
	}
)

const (
	ValuesOrderByNone ValuesOrderBy = iota
	ValuesOrderByUpdateDate
)

type TopUserBrand struct {
	ID      int64  `db:"id"`
	Name    string `db:"name"`
	Catname string `db:"catname"`
	Volume  int64  `db:"volume"`
}

type AttributeRow struct {
	schema.AttrsAttributeRow

	Childs         []*AttributeRow
	Deep           int
	NameTranslated string
}

type I18nUnit struct {
	Name string
	Abbr string
}

// Repository Main Object.
type Repository struct {
	db                      *goqu.Database
	i18n                    *i18nbundle.I18n
	listOptions             map[int64]map[int64]string
	listOptionsMutex        sync.Mutex
	listOptionsChilds       map[int64]map[int64][]int64
	attributes              map[int64]*schema.AttrsAttributeRow
	attributesTreeMutex     sync.Mutex
	attributesTree          map[int64][]*schema.AttrsAttributeRow
	zoneAttributesTreeMutex sync.Mutex
	zoneAttributes          map[int64][]*schema.AttrsAttributeRow
	zoneAttributesTree      map[int64]map[int64][]*schema.AttrsAttributeRow
	engineAttributes        []int64
	itemsRepository         *items.Repository
	picturesRepository      *pictures.Repository
	imageStorage            *storage.Storage
	unitsMutex              sync.Mutex
	units                   map[int64]schema.AttrsUnitRow
	i18nUnits               map[string]map[int64]I18nUnit
	i18nUnitsMutex          sync.Mutex
	nameFormatter           *items.ItemNameFormatter
}

// NewRepository constructor.
func NewRepository(
	db *goqu.Database,
	i18n *i18nbundle.I18n,
	itemsRepository *items.Repository,
	picturesRepository *pictures.Repository,
	imageStorage *storage.Storage,
) *Repository {
	return &Repository{
		db:                      db,
		i18n:                    i18n,
		listOptions:             make(map[int64]map[int64]string),
		listOptionsMutex:        sync.Mutex{},
		listOptionsChilds:       make(map[int64]map[int64][]int64),
		engineAttributes:        make([]int64, 0),
		itemsRepository:         itemsRepository,
		picturesRepository:      picturesRepository,
		imageStorage:            imageStorage,
		units:                   make(map[int64]schema.AttrsUnitRow),
		unitsMutex:              sync.Mutex{},
		i18nUnits:               make(map[string]map[int64]I18nUnit),
		i18nUnitsMutex:          sync.Mutex{},
		attributes:              nil,
		attributesTreeMutex:     sync.Mutex{},
		attributesTree:          nil,
		zoneAttributesTreeMutex: sync.Mutex{},
		zoneAttributes:          make(map[int64][]*schema.AttrsAttributeRow),
		zoneAttributesTree:      make(map[int64]map[int64][]*schema.AttrsAttributeRow),
		nameFormatter:           items.NewItemNameFormatter(i18n),
	}
}

func (s *Repository) Attribute(ctx context.Context, id int64) (*schema.AttrsAttributeRow, error) {
	err := s.loadAttributesTree(ctx)
	if err != nil {
		return nil, err
	}

	r, success := s.attributes[id]
	if !success {
		r = nil
	}

	return r, err
}

func (s *Repository) Attributes(
	ctx context.Context, options *query.AttrsListOptions,
) ([]*schema.AttrsAttributeRow, error) {
	if options == nil {
		options = &query.AttrsListOptions{}
	}

	var rows []*schema.AttrsAttributeRow

	if options.ZoneID > 0 {
		err := s.loadZoneAttributesTree(ctx, options.ZoneID)
		if err != nil {
			return nil, err
		}

		if options.ParentID > 0 {
			rows = s.zoneAttributesTree[options.ZoneID][options.ParentID]
		} else {
			rows = s.zoneAttributes[options.ZoneID]
		}
	} else {
		err := s.loadAttributesTree(ctx)
		if err != nil {
			return nil, err
		}

		if options.ParentID > 0 {
			rows = s.attributesTree[options.ParentID]
		} else {
			rows = slices.Collect(maps.Values(s.attributes))
		}
	}

	if len(options.IDs) > 0 {
		res := make([]*schema.AttrsAttributeRow, 0, len(options.IDs))

		for _, row := range rows {
			if util.Contains(options.IDs, row.ID) {
				res = append(res, row)
			}
		}

		rows = res
	}

	return rows, nil
}

func (s *Repository) AttributeTypes(ctx context.Context) ([]schema.AttrsAttributeTypeRow, error) {
	r := make([]schema.AttrsAttributeTypeRow, 0)
	err := s.db.Select(schema.AttrsTypesTableIDCol, schema.AttrsTypesTableNameCol).
		From(schema.AttrsTypesTable).
		ScanStructsContext(ctx, &r)

	return r, err
}

func (s *Repository) ListOptions(
	ctx context.Context,
	attributeID int64,
) ([]schema.AttrsListOptionRow, error) {
	sqSelect := s.db.Select(schema.AttrsListOptionsTableIDCol, schema.AttrsListOptionsTableNameCol,
		schema.AttrsListOptionsTableAttributeIDCol, schema.AttrsListOptionsTableParentIDCol).
		From(schema.AttrsListOptionsTable).
		Order(schema.AttrsListOptionsTablePositionCol.Asc())

	if attributeID > 0 {
		sqSelect = sqSelect.Where(schema.AttrsListOptionsTableAttributeIDCol.Eq(attributeID))
	}

	r := make([]schema.AttrsListOptionRow, 0)
	err := sqSelect.ScanStructsContext(ctx, &r)

	return r, err
}

func (s *Repository) Unit(ctx context.Context, id int64) (*schema.AttrsUnitRow, error) {
	units, err := s.unitsMap(ctx)
	if err != nil {
		return nil, err
	}

	unit := units[id]

	return &unit, nil
}

func (s *Repository) Units(ctx context.Context) ([]schema.AttrsUnitRow, error) {
	units, err := s.unitsMap(ctx)
	if err != nil {
		return nil, err
	}

	return slices.Collect(maps.Values(units)), nil
}

func (s *Repository) ZoneAttributes(
	ctx context.Context,
	zoneID int64,
) ([]schema.AttrsZoneAttributeRow, error) {
	attrs := make([]schema.AttrsZoneAttributeRow, 0)
	err := s.db.Select(schema.AttrsZoneAttributesTableZoneIDCol, schema.AttrsZoneAttributesTableAttributeIDCol).
		From(schema.AttrsZoneAttributesTable).
		Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID)).
		ScanStructsContext(ctx, &attrs)

	return attrs, err
}

func (s *Repository) Zones(ctx context.Context) ([]schema.AttrsZoneRow, error) {
	r := make([]schema.AttrsZoneRow, 0)
	err := s.db.Select(schema.AttrsZonesTableIDCol, schema.AttrsZonesTableNameCol).
		From(schema.AttrsZonesTable).
		ScanStructsContext(ctx, &r)

	return r, err
}

func (s *Repository) TotalValues(ctx context.Context) (int32, error) {
	sqSelect := s.db.From(schema.AttrsValuesTable)

	result, err := sqSelect.CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return int32(result), nil //nolint: gosec
}

func (s *Repository) TotalZoneAttrs(ctx context.Context, zoneID int64) (int32, error) {
	sqSelect := s.db.From(schema.AttrsAttributesTable).
		Join(
			schema.AttrsZoneAttributesTable,
			goqu.On(schema.AttrsAttributesTableIDCol.Eq(schema.AttrsZoneAttributesTableAttributeIDCol)),
		).
		Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID))

	result, err := sqSelect.CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return int32(result), nil //nolint: gosec
}

func (s *Repository) TopUserBrands(
	ctx context.Context, userID int64, limit uint,
) ([]TopUserBrand, error) {
	rows := make([]TopUserBrand, 0)

	const volumeAlias = "volume"

	err := s.db.Select(
		schema.ItemTableIDCol, schema.ItemTableNameCol, schema.ItemTableCatnameCol,
		goqu.COUNT(goqu.Star()).As(volumeAlias),
	).
		From(schema.ItemTable).
		Join(schema.ItemParentCacheTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentCacheTableParentIDCol))).
		Join(
			schema.AttrsUserValuesTable,
			goqu.On(schema.ItemParentCacheTableItemIDCol.Eq(schema.AttrsUserValuesTableItemIDCol)),
		).
		Where(
			schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand),
			schema.AttrsUserValuesTableUserIDCol.Eq(userID),
		).
		GroupBy(schema.ItemTableIDCol).
		Order(goqu.C(volumeAlias).Desc()).
		Limit(limit).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) ValuesCount(
	ctx context.Context,
	options query.AttrsValueListOptions,
) (int32, error) {
	sqSelect := s.ValuesSelect(options, ValuesOrderByNone)

	result, err := sqSelect.CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return int32(result), nil //nolint: gosec
}

func (s *Repository) ValuesSelect(
	options query.AttrsValueListOptions,
	orderBy ValuesOrderBy,
) *goqu.SelectDataset {
	alias := query.AttrsValuesAlias
	aliasTable := goqu.T(alias)

	sqSelect := options.Select(s.db, alias).Select(
		aliasTable.Col(schema.AttrsValuesTableAttributeIDColName),
		aliasTable.Col(schema.AttrsValuesTableItemIDColName),
	)

	if orderBy == ValuesOrderByUpdateDate {
		sqSelect = sqSelect.Order(aliasTable.Col(schema.AttrsValuesTableUpdateDateColName).Desc())
	}

	return sqSelect
}

func (s *Repository) ValuesPaginated(
	ctx context.Context,
	options query.AttrsValueListOptions,
	orderBy ValuesOrderBy,
	page int32,
	limit int32,
) ([]schema.AttrsValueRow, *util.Pages, error) {
	sqSelect := s.ValuesSelect(options, orderBy)

	paginator := util.Paginator{
		SQLSelect:         sqSelect,
		CurrentPageNumber: page,
		ItemCountPerPage:  limit,
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	res := make([]schema.AttrsValueRow, 0)
	err = sqSelect.ScanStructsContext(ctx, &res)

	return res, pages, err
}

func (s *Repository) Values(
	ctx context.Context, options query.AttrsValueListOptions, orderBy ValuesOrderBy,
) ([]schema.AttrsValueRow, error) {
	sqSelect := s.ValuesSelect(options, orderBy)
	res := make([]schema.AttrsValueRow, 0)
	err := sqSelect.ScanStructsContext(ctx, &res)

	return res, err
}

func (s *Repository) UserValueRows(
	ctx context.Context, options query.AttrsUserValueListOptions,
) ([]schema.AttrsUserValueRow, error) {
	res := make([]schema.AttrsUserValueRow, 0)

	err := options.Select(s.db, query.AttrsUserValuesAlias).Select(
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableAttributeIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableItemIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableUserIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableUpdateDateColName),
	).
		ScanStructsContext(ctx, &res)

	return res, err
}

func (s *Repository) UserValueRow(
	ctx context.Context, options query.AttrsUserValueListOptions,
) (schema.AttrsUserValueRow, bool, error) {
	var row schema.AttrsUserValueRow

	success, err := options.Select(s.db, query.AttrsUserValuesAlias).Select(
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableAttributeIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableItemIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableUserIDColName),
		goqu.T(query.AttrsUserValuesAlias).Col(schema.AttrsUserValuesTableUpdateDateColName),
	).ScanStructContext(ctx, &row)

	return row, success, err
}

func (s *Repository) ActualValue(
	ctx context.Context,
	attributeID int64,
	itemID int64,
) (Value, error) {
	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return Value{}, err
	}

	if attribute == nil {
		return Value{}, fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	if !attribute.TypeID.Valid {
		return Value{}, nil
	}

	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		var value sql.NullString

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsValuesStringTableValueCol).
				From(schema.AttrsValuesStringTable).
				Where(
					schema.AttrsValuesStringTableAttributeIDCol.Eq(attributeID),
					schema.AttrsValuesStringTableItemIDCol.Eq(itemID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:       success,
			StringValue: value.String,
			Type:        attribute.TypeID.AttributeTypeID,
			IsEmpty:     !value.Valid,
		}, nil

	case schema.AttrsAttributeTypeIDInteger:
		var value sql.NullInt32

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsValuesIntTableValueCol).From(schema.AttrsValuesIntTable).Where(
				schema.AttrsValuesIntTableAttributeIDCol.Eq(attributeID),
				schema.AttrsValuesIntTableItemIDCol.Eq(itemID),
			),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:    success,
			IntValue: value.Int32,
			Type:     attribute.TypeID.AttributeTypeID,
			IsEmpty:  !value.Valid,
		}, nil
	case schema.AttrsAttributeTypeIDBoolean:
		var value sql.NullBool

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsValuesIntTableValueCol).From(schema.AttrsValuesIntTable).Where(
				schema.AttrsValuesIntTableAttributeIDCol.Eq(attributeID),
				schema.AttrsValuesIntTableItemIDCol.Eq(itemID),
			),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:     success,
			BoolValue: value.Bool,
			Type:      attribute.TypeID.AttributeTypeID,
			IsEmpty:   !value.Valid,
		}, nil

	case schema.AttrsAttributeTypeIDFloat:
		var value sql.NullFloat64

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsValuesFloatTableValueCol).
				From(schema.AttrsValuesFloatTable).
				Where(
					schema.AttrsValuesFloatTableAttributeIDCol.Eq(attributeID),
					schema.AttrsValuesFloatTableItemIDCol.Eq(itemID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:      success,
			FloatValue: value.Float64,
			Type:       attribute.TypeID.AttributeTypeID,
			IsEmpty:    !value.Valid,
		}, nil
	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		var values []sql.NullInt64

		err = s.db.Select(schema.AttrsValuesListTableValueCol).
			From(schema.AttrsValuesListTable).
			Where(
				schema.AttrsValuesListTableAttributeIDCol.Eq(attributeID),
				schema.AttrsValuesListTableItemIDCol.Eq(itemID),
			).
			Order(schema.AttrsValuesListTableOrderingCol.Asc()).
			ScanValsContext(ctx, &values)
		if err != nil {
			return Value{}, err
		}

		vals := make([]int64, 0, len(values))
		isEmpty := false

		for _, val := range values {
			if !val.Valid {
				isEmpty = true

				break
			}

			vals = append(vals, val.Int64)
		}

		return Value{
			Valid:     len(vals) > 0 || isEmpty,
			ListValue: vals,
			Type:      attribute.TypeID.AttributeTypeID,
			IsEmpty:   isEmpty,
		}, nil

	case schema.AttrsAttributeTypeIDUnknown:
	}

	return Value{}, nil
}

func (s *Repository) UserValue(
	ctx context.Context,
	attributeID int64,
	itemID int64,
	userID int64,
) (Value, error) {
	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return Value{}, err
	}

	if attribute == nil {
		return Value{}, fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	if !attribute.TypeID.Valid {
		return Value{}, nil
	}

	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		var value sql.NullString

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsUserValuesStringTableValueCol).
				From(schema.AttrsUserValuesStringTable).
				Where(
					schema.AttrsUserValuesStringTableAttributeIDCol.Eq(attributeID),
					schema.AttrsUserValuesStringTableItemIDCol.Eq(itemID),
					schema.AttrsUserValuesStringTableUserIDCol.Eq(userID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:       success,
			StringValue: value.String,
			Type:        attribute.TypeID.AttributeTypeID,
			IsEmpty:     !value.Valid,
		}, nil

	case schema.AttrsAttributeTypeIDInteger:
		var value sql.NullInt32

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsUserValuesIntTableValueCol).
				From(schema.AttrsUserValuesIntTable).
				Where(
					schema.AttrsUserValuesIntTableAttributeIDCol.Eq(attributeID),
					schema.AttrsUserValuesIntTableItemIDCol.Eq(itemID),
					schema.AttrsUserValuesIntTableUserIDCol.Eq(userID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:    success,
			IntValue: value.Int32,
			Type:     attribute.TypeID.AttributeTypeID,
			IsEmpty:  !value.Valid,
		}, nil
	case schema.AttrsAttributeTypeIDBoolean:
		var value sql.NullBool

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsUserValuesIntTableValueCol).
				From(schema.AttrsUserValuesIntTable).
				Where(
					schema.AttrsUserValuesIntTableAttributeIDCol.Eq(attributeID),
					schema.AttrsUserValuesIntTableItemIDCol.Eq(itemID),
					schema.AttrsUserValuesIntTableUserIDCol.Eq(userID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:     success,
			BoolValue: value.Bool,
			Type:      attribute.TypeID.AttributeTypeID,
			IsEmpty:   !value.Valid,
		}, nil

	case schema.AttrsAttributeTypeIDFloat:
		var value sql.NullFloat64

		success, err := util.ScanValContextAndRetryOnDeadlock(
			ctx,
			s.db.Select(schema.AttrsUserValuesFloatTableValueCol).
				From(schema.AttrsUserValuesFloatTable).
				Where(
					schema.AttrsUserValuesFloatTableAttributeIDCol.Eq(attributeID),
					schema.AttrsUserValuesFloatTableItemIDCol.Eq(itemID),
					schema.AttrsUserValuesFloatTableUserIDCol.Eq(userID),
				),
			&value,
		)
		if err != nil {
			return Value{}, err
		}

		return Value{
			Valid:      success,
			FloatValue: value.Float64,
			Type:       attribute.TypeID.AttributeTypeID,
			IsEmpty:    !value.Valid,
		}, nil
	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		var values []sql.NullInt64

		err = s.db.Select(schema.AttrsUserValuesListTableValueCol).
			From(schema.AttrsUserValuesListTable).
			Where(
				schema.AttrsUserValuesListTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesListTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesListTableUserIDCol.Eq(userID),
			).
			Order(schema.AttrsUserValuesListTableOrderingCol.Asc()).
			ScanValsContext(ctx, &values)
		if err != nil {
			return Value{}, err
		}

		vals := make([]int64, 0, len(values))
		isEmpty := false

		for _, val := range values {
			if !val.Valid {
				isEmpty = true

				break
			}

			vals = append(vals, val.Int64)
		}

		return Value{
			Valid:     len(vals) > 0 || isEmpty,
			ListValue: vals,
			Type:      attribute.TypeID.AttributeTypeID,
			IsEmpty:   isEmpty,
		}, nil

	case schema.AttrsAttributeTypeIDUnknown:
	}

	return Value{}, nil
}

func (s *Repository) UserValueText(
	ctx context.Context, attributeID int64, itemID int64, userID int64, lang string,
) (Value, string, error) {
	value, err := s.UserValue(ctx, attributeID, itemID, userID)
	if err != nil {
		return Value{}, "", err
	}

	text, err := s.valueToText(ctx, attributeID, value, lang)

	return value, text, err
}

func (s *Repository) ActualValueText(
	ctx context.Context, attributeID int64, itemID int64, lang string,
) (Value, string, error) {
	value, err := s.ActualValue(ctx, attributeID, itemID)
	if err != nil {
		return Value{}, "", err
	}

	text, err := s.valueToText(ctx, attributeID, value, lang)

	return value, text, err
}

func (s *Repository) ListOptionsText(
	ctx context.Context,
	attributeID int64,
	id int64,
	lang string,
) (string, error) {
	err := s.loadListOptions(ctx)
	if err != nil {
		return "", err
	}

	if _, ok := s.listOptions[attributeID][id]; !ok {
		return "", fmt.Errorf("%w: `%d`", errListOptionFound, id)
	}

	localizer := s.i18n.Localizer(lang)

	return localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{
			ID: s.listOptions[attributeID][id],
		},
	})
}

func (s *Repository) DeleteUserValue(ctx context.Context, attributeID, itemID, userID int64) error {
	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return err
	}

	if attribute == nil {
		return fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	ctx = context.WithoutCancel(ctx)

	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		_, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Delete(schema.AttrsUserValuesStringTable).Where(
				schema.AttrsUserValuesStringTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesStringTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesStringTableUserIDCol.Eq(userID),
			).Executor(),
		)
		if err != nil {
			return err
		}

	case schema.AttrsAttributeTypeIDInteger, schema.AttrsAttributeTypeIDBoolean:
		_, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Delete(schema.AttrsUserValuesIntTable).Where(
				schema.AttrsUserValuesIntTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesIntTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesIntTableUserIDCol.Eq(userID),
			).Executor(),
		)
		if err != nil {
			return err
		}

	case schema.AttrsAttributeTypeIDFloat:
		_, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Delete(schema.AttrsUserValuesFloatTable).Where(
				schema.AttrsUserValuesFloatTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesFloatTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesFloatTableUserIDCol.Eq(userID),
			).Executor(),
		)
		if err != nil {
			return err
		}

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		_, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Delete(schema.AttrsUserValuesListTable).Where(
				schema.AttrsUserValuesListTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesListTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesListTableUserIDCol.Eq(userID),
			).Executor(),
		)
		if err != nil {
			return err
		}

	case schema.AttrsAttributeTypeIDUnknown:
	}

	_, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsUserValuesTable).Where(
			schema.AttrsUserValuesTableAttributeIDCol.Eq(attributeID),
			schema.AttrsUserValuesTableItemIDCol.Eq(itemID),
			schema.AttrsUserValuesTableUserIDCol.Eq(userID),
		).Executor(),
	)
	if err != nil {
		return err
	}

	err = s.updateActualValue(ctx, attributeID, itemID)
	if err != nil {
		return fmt.Errorf(
			"%w: updateActualValue(%d, %d)",
			errAttributeNotFound,
			attributeID,
			itemID,
		)
	}

	return nil
}

func (s *Repository) UpdateActualValues(ctx context.Context, itemID int64) error {
	err := s.loadAttributesTree(ctx)
	if err != nil {
		return err
	}

	for _, attribute := range s.attributes {
		if attribute.TypeID.Valid {
			_, err = s.updateAttributeActualValue(ctx, attribute, itemID)
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s *Repository) RefreshItemConflictFlags(ctx context.Context, itemID int64) error {
	var ids []int64

	err := s.db.Select(schema.AttrsUserValuesTableAttributeIDCol).Distinct().
		From(schema.AttrsUserValuesTable).
		Where(schema.AttrsUserValuesTableItemIDCol.Eq(itemID)).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, id := range ids {
		err = s.refreshConflictFlag(ctx, id, itemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) RefreshUserConflictsStat(
	ctx context.Context,
	userIDs []int64,
	all bool,
) error {
	if len(userIDs) == 0 && !all {
		return nil
	}

	pSelect := s.db.Select(goqu.SUM(schema.AttrsUserValuesTableWeightCol)).
		From(schema.AttrsUserValuesTable).
		Where(
			schema.AttrsUserValuesTableUserIDCol.Eq(schema.UserTableIDCol),
			schema.AttrsUserValuesTableWeightCol.Gt(0),
		)

	nSelect := s.db.Select(goqu.Func("ABS", goqu.SUM(schema.AttrsUserValuesTableWeightCol))).
		From(schema.AttrsUserValuesTable).
		Where(
			schema.AttrsUserValuesTableUserIDCol.Eq(schema.UserTableIDCol),
			schema.AttrsUserValuesTableWeightCol.Lt(0),
		)

	expr := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableSpecsWeightColName: goqu.L(
			"1.5 * ((1 + IFNULL((?), 0)) / (1 + IFNULL((?), 0)))",
			pSelect,
			nSelect,
		),
	})

	if !all {
		expr = expr.Where(schema.UserTableIDCol.In(userIDs))
	}

	_, err := util.ExecAndRetryOnDeadlock(ctx, expr.Executor())

	return err
}

func (s *Repository) RefreshConflictFlags(ctx context.Context) error {
	var rows []schema.AttrsUserValueRow

	err := s.db.Select(schema.AttrsUserValuesTableAttributeIDCol, schema.AttrsUserValuesTableItemIDCol).
		Distinct().
		From(schema.AttrsUserValuesTable).
		Where(schema.AttrsUserValuesTableConflictCol.IsTrue()).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return err
	}

	for _, row := range rows {
		err = s.refreshConflictFlag(ctx, row.AttributeID, row.ItemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) MoveUserValues(ctx context.Context, srcItemID, destItemID int64) error {
	rows, err := s.UserValueRows(ctx, query.AttrsUserValueListOptions{
		ItemID: srcItemID,
	})
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, row := range rows {
		_, err = s.db.Update(schema.AttrsUserValuesTable).Set(goqu.Record{
			schema.AttrsUserValuesTableItemIDColName: destItemID,
		}).Where(
			schema.AttrsUserValuesTableAttributeIDCol.Eq(row.AttributeID),
			schema.AttrsUserValuesTableItemIDCol.Eq(row.ItemID),
			schema.AttrsUserValuesTableUserIDCol.Eq(row.UserID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		_, err = s.db.Update(schema.AttrsUserValuesFloatTable).Set(goqu.Record{
			schema.AttrsUserValuesFloatTableItemIDColName: destItemID,
		}).Where(
			schema.AttrsUserValuesFloatTableAttributeIDCol.Eq(row.AttributeID),
			schema.AttrsUserValuesFloatTableItemIDCol.Eq(row.ItemID),
			schema.AttrsUserValuesFloatTableUserIDCol.Eq(row.UserID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		_, err = s.db.Update(schema.AttrsUserValuesIntTable).Set(goqu.Record{
			schema.AttrsUserValuesIntTableItemIDColName: destItemID,
		}).Where(
			schema.AttrsUserValuesIntTableAttributeIDCol.Eq(row.AttributeID),
			schema.AttrsUserValuesIntTableItemIDCol.Eq(row.ItemID),
			schema.AttrsUserValuesIntTableUserIDCol.Eq(row.UserID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		_, err = s.db.Update(schema.AttrsUserValuesStringTable).Set(goqu.Record{
			schema.AttrsUserValuesStringTableItemIDColName: destItemID,
		}).Where(
			schema.AttrsUserValuesStringTableAttributeIDCol.Eq(row.AttributeID),
			schema.AttrsUserValuesStringTableItemIDCol.Eq(row.ItemID),
			schema.AttrsUserValuesStringTableUserIDCol.Eq(row.UserID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		_, err = s.db.Update(schema.AttrsUserValuesListTable).Set(goqu.Record{
			schema.AttrsUserValuesListTableItemIDColName: destItemID,
		}).Where(
			schema.AttrsUserValuesListTableAttributeIDCol.Eq(row.AttributeID),
			schema.AttrsUserValuesListTableItemIDCol.Eq(row.ItemID),
			schema.AttrsUserValuesListTableUserIDCol.Eq(row.UserID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		err = s.updateActualValue(ctx, row.AttributeID, row.ItemID)
		if err != nil {
			return err
		}

		err = s.updateActualValue(ctx, row.AttributeID, destItemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) UpdateAllActualValues(ctx context.Context) error {
	err := s.loadAttributesTree(ctx)
	if err != nil {
		return err
	}

	var itemIDs []int64

	err = s.db.Select(schema.AttrsUserValuesTableItemIDCol).Distinct().
		From(schema.AttrsUserValuesTable).
		ScanValsContext(ctx, &itemIDs)
	if err != nil {
		return err
	}

	for _, itemID := range itemIDs {
		for _, attribute := range s.attributes {
			if attribute.TypeID.Valid {
				_, err = s.updateAttributeActualValue(ctx, attribute, itemID)
				if err != nil {
					return err
				}
			}
		}
	}

	return nil
}

func (s *Repository) UpdateInheritedValues(ctx context.Context, itemID int64) error {
	err := s.loadAttributesTree(ctx)
	if err != nil {
		return err
	}

	for _, attribute := range s.attributes {
		if attribute.TypeID.Valid {
			haveValue, err := s.haveOwnAttributeValue(ctx, attribute.ID, itemID)
			if err != nil {
				return err
			}

			if !haveValue {
				_, err = s.updateAttributeActualValue(ctx, attribute, itemID)
				if err != nil {
					return err
				}
			}
		}
	}

	return nil
}

func (s *Repository) ZoneIDByVehicleTypeIDs(
	itemTypeID schema.ItemTableItemTypeID,
	vehicleTypeIDs []int64,
) int64 {
	if itemTypeID == schema.ItemTableItemTypeIDEngine {
		return engineZoneID
	}

	zoneID := defaultZoneID

	for _, vehicleTypeID := range vehicleTypeIDs {
		if util.Contains(busVehicleTypes, vehicleTypeID) {
			zoneID = busZoneID

			break
		}
	}

	return zoneID
}

func (s *Repository) ChildSpecifications(
	ctx context.Context, itemID int64, lang string,
) (*CarSpecTable, error) {
	rows, _, err := s.itemsRepository.ItemParents(ctx, &query.ItemParentListOptions{
		ParentID: itemID,
	}, items.ItemParentFields{}, items.ItemParentOrderByAuto)
	if err != nil {
		return nil, err
	}

	ids := make([]int64, 0, len(rows))
	for _, row := range rows {
		ids = append(ids, row.ItemID)
	}

	return s.Specifications(ctx, ids, itemID, lang)
}

func (s *Repository) SetUserValue( //nolint: maintidx
	ctx context.Context,
	userID, attributeID, itemID int64,
	value Value,
) (bool, error) {
	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return false, err
	}

	if attribute == nil {
		return false, fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	if !attribute.TypeID.Valid {
		return false, nil
	}

	ctx = context.WithoutCancel(ctx)

	// convert empty values to valid = false
	if value.Valid && !value.IsEmpty {
		switch attribute.TypeID.AttributeTypeID {
		case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
			value.Valid = len(value.StringValue) > 0

		case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
			if len(value.ListValue) > 0 {
				sqSelect := s.db.Select(schema.AttrsListOptionsTableIDCol).
					From(schema.AttrsListOptionsTable).
					Where(
						schema.AttrsListOptionsTableAttributeIDCol.Eq(attribute.ID),
						schema.AttrsListOptionsTableIDCol.In(value.ListValue),
					)

				if !attribute.Multiple {
					sqSelect = sqSelect.Limit(1)
				}

				err = sqSelect.ScanValsContext(ctx, &value.ListValue)
				if err != nil {
					return false, fmt.Errorf("ScanValsContext(): %w", err)
				}
			}

			value.Valid = len(value.ListValue) > 0

		case schema.AttrsAttributeTypeIDInteger,
			schema.AttrsAttributeTypeIDBoolean,
			schema.AttrsAttributeTypeIDFloat,
			schema.AttrsAttributeTypeIDUnknown:
		}
	}

	if !value.Valid {
		err = s.DeleteUserValue(ctx, attributeID, itemID, userID)
		if err != nil {
			return false, fmt.Errorf(
				"DeleteUserValue(%d, %d, %d): %w",
				attributeID,
				itemID,
				userID,
				err,
			)
		}

		return false, nil
	}

	oldValue, err := s.UserValue(ctx, attributeID, itemID, userID)
	if err != nil {
		return false, fmt.Errorf("UserValue(%d, %d, %d): %w", attributeID, itemID, userID, err)
	}

	if oldValue.Equals(value) {
		return false, nil
	}

	_, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsUserValuesTable).Rows(goqu.Record{
			schema.AttrsUserValuesTableAttributeIDColName: attribute.ID,
			schema.AttrsUserValuesTableItemIDColName:      itemID,
			schema.AttrsUserValuesTableUserIDColName:      userID,
			schema.AttrsUserValuesTableAddDateColName:     goqu.Func("NOW"),
			schema.AttrsUserValuesTableUpdateDateColName:  goqu.Func("NOW"),
		}).Executor(),
	)
	if err != nil && !util.IsMysqlDuplicateKeyError(err) {
		return false, fmt.Errorf("failed to insert attribute user value descriptor: %w", err)
	}

	valueChanged := false

	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		valueChanged, err = s.setStringUserValue(
			ctx,
			attribute.ID,
			itemID,
			userID,
			value.StringValue,
			value.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setStringUserValue(%d, %d, %d, %s, %t): %w",
				attribute.ID, itemID, userID, value.StringValue, value.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDInteger:
		valueChanged, err = s.setIntUserValue(
			ctx,
			attribute.ID,
			itemID,
			userID,
			value.IntValue,
			value.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setIntUserValue(%d, %d, %d, %d, %t): %w",
				attribute.ID, itemID, userID, value.IntValue, value.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDBoolean:
		var intValue int32
		if value.BoolValue {
			intValue = 1
		}

		valueChanged, err = s.setIntUserValue(
			ctx,
			attribute.ID,
			itemID,
			userID,
			intValue,
			value.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setIntUserValue(%d, %d, %d, %d, %t): %w",
				attribute.ID, itemID, userID, value.IntValue, value.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDFloat:
		valueChanged, err = s.setFloatUserValue(
			ctx,
			attribute.ID,
			itemID,
			userID,
			value.FloatValue,
			value.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setFloatUserValue(%d, %d, %d, %x, %t): %w",
				attribute.ID, itemID, userID, value.IntValue, value.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		valueChanged, err = s.setListUserValue(
			ctx,
			attribute,
			itemID,
			userID,
			value.ListValue,
			value.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setListUserValue(%d, %d, %d, %v, %t): %w",
				attribute.ID, itemID, userID, value.ListValue, value.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDUnknown:
	}

	if valueChanged {
		_, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Update(schema.AttrsUserValuesTable).Set(goqu.Record{
				schema.AttrsUserValuesTableUpdateDateColName: goqu.Func("NOW"),
			}).Where(
				schema.AttrsUserValuesTableAttributeIDCol.Eq(attribute.ID),
				schema.AttrsUserValuesTableItemIDCol.Eq(itemID),
				schema.AttrsUserValuesTableUserIDCol.Eq(userID),
			).Executor(),
		)
		if err != nil {
			return false, fmt.Errorf("failed to update attribute user value descriptor: %w", err)
		}
	}

	somethingChanged, err := s.updateAttributeActualValue(ctx, attribute, itemID)
	if err != nil {
		return false, fmt.Errorf(
			"updateAttributeActualValue(%d, %d): %w",
			attribute.ID,
			itemID,
			err,
		)
	}

	return somethingChanged || valueChanged, nil
}

func (s *Repository) Specifications( //nolint: maintidx
	ctx context.Context, itemIDs []int64, contextItemID int64, lang string,
) (*CarSpecTable, error) {
	cars, _, err := s.itemsRepository.List(ctx, &query.ItemListOptions{
		ItemIDs: itemIDs,
	}, &items.ItemFields{NameText: true}, items.OrderByName, false)
	if err != nil {
		return nil, err
	}

	specsZoneID, err := s.zoneByItemsList(ctx, cars)
	if err != nil {
		return nil, err
	}

	var actualValues map[int64]map[int64]Value

	if specsZoneID > 0 {
		actualValues, err = s.ZoneItemsActualValues(ctx, specsZoneID, itemIDs)
		if err != nil {
			return nil, fmt.Errorf("ZoneItemsActualValues(): %w", err)
		}
	} else {
		actualValues, err = s.ItemsActualValues(ctx, itemIDs)
		if err != nil {
			return nil, fmt.Errorf("ItemsActualValues(): %w", err)
		}
	}

	actualValuesText, err := s.actualValuesToText(ctx, actualValues, lang)
	if err != nil {
		return nil, fmt.Errorf("actualValuesToText(): %w", err)
	}

	localizer := s.i18n.Localizer(lang)
	result := make([]CarSpecTableItem, 0, len(cars))

	for _, car := range cars {
		itemID := car.ID
		values := actualValuesText[itemID]

		_, ok := values[schema.EngineNameAttr]

		// append engine name
		if !ok && car.EngineItemID.Valid {
			engineRow, err := s.itemsRepository.Item(ctx,
				&query.ItemListOptions{ItemID: car.EngineItemID.Int64, Language: lang},
				&items.ItemFields{NameText: true},
			)
			if err != nil && !errors.Is(err, items.ErrItemNotFound) {
				return nil, fmt.Errorf("Item(): %w", err)
			}

			if err == nil {
				formatterOptions := items.ItemNameFormatterOptions{
					BeginModelYear: util.NullInt32ToScalar(engineRow.BeginModelYear),
					EndModelYear:   util.NullInt32ToScalar(engineRow.EndModelYear),
					BeginModelYearFraction: util.NullStringToString(
						engineRow.BeginModelYearFraction,
					),
					EndModelYearFraction: util.NullStringToString(engineRow.EndModelYearFraction),
					Spec:                 engineRow.SpecShortName,
					SpecFull:             engineRow.SpecName,
					Body:                 engineRow.Body,
					Name:                 engineRow.NameOnly,
					BeginYear:            util.NullInt32ToScalar(engineRow.BeginYear),
					EndYear:              util.NullInt32ToScalar(engineRow.EndYear),
					Today:                util.NullBoolToBoolPtr(engineRow.Today),
					BeginMonth:           util.NullInt16ToScalar(engineRow.BeginMonth),
					EndMonth:             util.NullInt16ToScalar(engineRow.EndMonth),
				}

				nameText, err := s.nameFormatter.FormatText(formatterOptions, lang)
				if err != nil {
					return nil, err
				}

				values[schema.EngineNameAttr] = nameText
			}
		}

		name := ""

		if contextItemID > 0 {
			itemParentRow, err := s.itemsRepository.ItemParent(ctx, &query.ItemParentListOptions{
				ItemID:   car.ID,
				ParentID: contextItemID,
			}, items.ItemParentFields{Name: true})
			if err != nil && !errors.Is(err, items.ErrItemNotFound) {
				return nil, fmt.Errorf("ItemParent(): %w", err)
			}

			if err == nil {
				name = itemParentRow.Name
			}
		} else {
			formatterOptions := items.ItemNameFormatterOptions{
				BeginModelYear:         util.NullInt32ToScalar(car.BeginModelYear),
				EndModelYear:           util.NullInt32ToScalar(car.EndModelYear),
				BeginModelYearFraction: util.NullStringToString(car.BeginModelYearFraction),
				EndModelYearFraction:   util.NullStringToString(car.EndModelYearFraction),
				Spec:                   car.SpecShortName,
				SpecFull:               car.SpecName,
				Body:                   car.Body,
				Name:                   car.NameOnly,
				BeginYear:              util.NullInt32ToScalar(car.BeginYear),
				EndYear:                util.NullInt32ToScalar(car.EndYear),
				Today:                  util.NullBoolToBoolPtr(car.Today),
				BeginMonth:             util.NullInt16ToScalar(car.BeginMonth),
				EndMonth:               util.NullInt16ToScalar(car.EndMonth),
			}

			nameText, err := s.nameFormatter.FormatText(formatterOptions, lang)
			if err != nil {
				return nil, err
			}

			name = nameText
		}

		topPicture, topPictureURL, err := s.specPicture(
			ctx,
			car.ID,
			pictures.OrderByTopPerspectives,
		)
		if err != nil && !errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("specPicture(): %w", err)
		}

		bottomPicture, bottomPictureURL, err := s.specPicture(
			ctx,
			car.ID,
			pictures.OrderByBottomPerspectives,
		)
		if err != nil && !errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("specPicture(): %w", err)
		}

		yearsHTML, err := items.RenderYearsHTML(
			util.NullBoolToBoolPtr(car.Today),
			util.NullInt32ToScalar(car.BeginYear),
			util.NullInt16ToScalar(car.BeginMonth),
			util.NullInt32ToScalar(car.EndYear),
			util.NullInt16ToScalar(car.EndMonth),
			localizer,
		)
		if err != nil {
			return nil, fmt.Errorf("RenderYearsHTML(): %w", err)
		}

		result = append(result, CarSpecTableItem{
			ID:                 itemID,
			NameHTML:           template.HTML(name),      //nolint: gosec
			YearsHTML:          template.HTML(yearsHTML), //nolint: gosec
			TopPictureURL:      topPictureURL,
			TopPictureImage:    topPicture,
			BottomPictureURL:   bottomPictureURL,
			BottomPictureImage: bottomPicture,
			Values:             values,
		})
	}

	attributes, err := s.attributesRecursive(ctx, specsZoneID, 0, 0)
	if err != nil {
		return nil, fmt.Errorf("attributesRecursive(): %w", err)
	}

	// remove empty attributes
	attributes = s.removeEmpty(attributes, result)

	attributes = s.flatternAttributes(attributes)

	for idx := range attributes {
		name, err := localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{
				ID: attributes[idx].Name,
			},
		})
		if err != nil {
			return nil, err
		}

		attributes[idx].NameTranslated = name
	}

	units, err := s.i18nUnitsMap(ctx, lang)
	if err != nil {
		return nil, fmt.Errorf("i18nUnitsMap(): %w", err)
	}

	return &CarSpecTable{
		Items:      result,
		Attributes: attributes,
		Units:      units,
	}, nil
}

func (s *Repository) ZoneItemsActualValues(
	ctx context.Context, zoneID int64, itemIDs []int64,
) (map[int64]map[int64]Value, error) {
	floatRows, err := s.zoneItemsFloatValuesRows(ctx, zoneID, itemIDs)
	if err != nil {
		return nil, err
	}

	floatValues := s.floatValuesRowsToMap(floatRows)

	intRows, err := s.zoneItemsIntValuesRows(ctx, zoneID, itemIDs)
	if err != nil {
		return nil, err
	}

	intValues, err := s.intValuesRowsToMap(ctx, intRows)
	if err != nil {
		return nil, err
	}

	stringRows, err := s.zoneItemsStringValuesRows(ctx, zoneID, itemIDs)
	if err != nil {
		return nil, err
	}

	stringValues := s.stringValuesRowsToMap(stringRows)

	listRows, err := s.zoneItemsListValuesRows(ctx, zoneID, itemIDs)
	if err != nil {
		return nil, err
	}

	listValues, err := s.listValuesRowsToMap(ctx, listRows)
	if err != nil {
		return nil, err
	}

	values := intValues

	for itemID, attrs := range stringValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	for itemID, attrs := range floatValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	for itemID, attrs := range listValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	return values, nil
}

func (s *Repository) ItemsActualValues(
	ctx context.Context,
	itemIDs []int64,
) (map[int64]map[int64]Value, error) {
	floatRows, err := s.zoneItemsFloatValuesRows(ctx, 0, itemIDs)
	if err != nil {
		return nil, err
	}

	floatValues := s.floatValuesRowsToMap(floatRows)

	intRows, err := s.zoneItemsIntValuesRows(ctx, 0, itemIDs)
	if err != nil {
		return nil, err
	}

	intValues, err := s.intValuesRowsToMap(ctx, intRows)
	if err != nil {
		return nil, err
	}

	stringRows, err := s.zoneItemsStringValuesRows(ctx, 0, itemIDs)
	if err != nil {
		return nil, err
	}

	stringValues := s.stringValuesRowsToMap(stringRows)

	listRows, err := s.zoneItemsListValuesRows(ctx, 0, itemIDs)
	if err != nil {
		return nil, err
	}

	listValues, err := s.listValuesRowsToMap(ctx, listRows)
	if err != nil {
		return nil, err
	}

	values := intValues

	for itemID, attrs := range stringValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	for itemID, attrs := range floatValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	for itemID, attrs := range listValues {
		if _, ok := values[itemID]; ok {
			maps.Copy(values[itemID], attrs)
		} else {
			values[itemID] = attrs
		}
	}

	return values, nil
}

type Contributor struct {
	UserID int64 `db:"user_id"`
	Count  int32 `db:"count"`
}

func (s *Repository) Contributors(ctx context.Context, itemID int64) ([]Contributor, error) {
	if itemID == 0 {
		return nil, nil
	}

	var sts []Contributor

	err := s.db.Select(
		schema.AttrsUserValuesTableUserIDCol, goqu.COUNT(goqu.Star()).As("count")).
		From(schema.AttrsUserValuesTable).
		Where(schema.AttrsUserValuesTableItemIDCol.Eq(itemID)).
		GroupBy(schema.AttrsUserValuesTableUserIDCol).
		Order(goqu.C("count").Desc()).
		ScanStructsContext(ctx, &sts)

	return sts, err
}

func (s *Repository) ChartData(ctx context.Context, attributeID int64) ([]ChartDataset, error) {
	if !util.Contains(ChartParameters, attributeID) {
		return nil, errAttributeNotFound
	}

	attrRow, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return nil, err
	}

	if attrRow == nil {
		return nil, errAttributeNotFound
	}

	valueTable, err := ValueTableByType(attrRow.TypeID.AttributeTypeID)
	if err != nil {
		return nil, err
	}

	datasets := make([]ChartDataset, 0)

	for _, specID := range chartSpecs {
		specRow, err := s.itemsRepository.Spec(ctx, specID)
		if err != nil {
			return nil, err
		}

		specIDs, err := s.specIDs(ctx, specID)
		if err != nil {
			return nil, err
		}

		pairs := make(map[int]Value)

		sqSelect := s.db.Select(
			goqu.Func("YEAR", schema.ItemTableBeginOrderCacheCol).As("year"),
			goqu.Func("ROUND", goqu.Func("AVG", valueTable.ValueCol)).As("value"),
		).
			From(valueTable.Table).
			Join(schema.ItemTable, goqu.On(valueTable.ItemIDCol.Eq(schema.ItemTableIDCol))).
			Join(schema.ItemVehicleTypeTable, goqu.On(
				schema.ItemTableIDCol.Eq(schema.ItemVehicleTypeTableItemIDCol),
			)).
			Join(schema.VehicleTypeParentTable, goqu.On(
				schema.ItemVehicleTypeTableVehicleTypeIDCol.Eq(schema.VehicleTypeParentTableIDCol),
			)).
			Where(
				valueTable.AttributeIDCol.Eq(attributeID),
				schema.VehicleTypeParentTableParentIDCol.Eq(schema.VehicleTypeCarID),
				schema.ItemTableBeginOrderCacheCol.IsNotNull(),
				schema.ItemTableBeginOrderCacheCol.Lt("2100-01-01 00:00:00"),
				schema.ItemTableSpecIDCol.In(specIDs),
				valueTable.ValueCol.IsNotNull(),
			).
			GroupBy(goqu.C("year")).
			Order(goqu.C("year").Asc())

		switch attrRow.TypeID.AttributeTypeID {
		case schema.AttrsAttributeTypeIDInteger:
			var sts []struct {
				Year  int   `db:"year"`
				Value int32 `db:"value"`
			}

			err = sqSelect.ScanStructsContext(ctx, &sts)
			if err != nil {
				return nil, err
			}

			for _, st := range sts {
				pairs[st.Year] = Value{
					Valid:    true,
					IntValue: st.Value,
					Type:     attrRow.TypeID.AttributeTypeID,
					IsEmpty:  false,
				}
			}
		case schema.AttrsAttributeTypeIDFloat:
			var sts []struct {
				Year  int     `db:"year"`
				Value float64 `db:"value"`
			}

			err = sqSelect.ScanStructsContext(ctx, &sts)
			if err != nil {
				return nil, err
			}

			for _, st := range sts {
				pairs[st.Year] = Value{
					Valid:      true,
					FloatValue: st.Value,
					Type:       attrRow.TypeID.AttributeTypeID,
					IsEmpty:    false,
				}
			}
		case schema.AttrsAttributeTypeIDString,
			schema.AttrsAttributeTypeIDText,
			schema.AttrsAttributeTypeIDBoolean,
			schema.AttrsAttributeTypeIDList,
			schema.AttrsAttributeTypeIDTree,
			schema.AttrsAttributeTypeIDUnknown:
			return nil, errAttributeTypeNotSupported
		}

		datasets = append(datasets, ChartDataset{
			Title: specRow.Name,
			Pairs: pairs,
		})
	}

	return datasets, nil
}

func (s *Repository) loadZoneAttributesTree(ctx context.Context, zoneID int64) error {
	err := s.loadAttributesTree(ctx)
	if err != nil {
		return err
	}

	s.zoneAttributesTreeMutex.Lock()
	defer s.zoneAttributesTreeMutex.Unlock()

	if _, ok := s.zoneAttributesTree[zoneID]; !ok {
		tree := make(map[int64][]*schema.AttrsAttributeRow)

		sqSelect := s.db.Select(schema.AttrsZoneAttributesTableAttributeIDCol).
			From(schema.AttrsZoneAttributesTable).
			Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID)).
			Order(schema.AttrsZoneAttributesTablePositionCol.Asc())

		ids := make([]int64, 0)

		err = sqSelect.ScanValsContext(ctx, &ids)
		if err != nil {
			return err
		}

		list := make([]*schema.AttrsAttributeRow, 0, len(ids))

		for _, id := range ids {
			attr, ok := s.attributes[id]
			if !ok || attr == nil {
				return errAttributeNotFound
			}

			list = append(list, attr)

			var parentID int64
			if attr.ParentID.Valid {
				parentID = attr.ParentID.Int64
			}

			if _, ok := tree[parentID]; !ok {
				tree[parentID] = make([]*schema.AttrsAttributeRow, 0, 1)
			}

			tree[parentID] = append(tree[parentID], attr)
		}

		s.zoneAttributes[zoneID] = list
		s.zoneAttributesTree[zoneID] = tree
	}

	return nil
}

func (s *Repository) loadAttributesTree(ctx context.Context) error {
	s.attributesTreeMutex.Lock()
	defer s.attributesTreeMutex.Unlock()

	if s.attributesTree == nil {
		rows := make([]schema.AttrsAttributeRow, 0)

		err := s.db.Select(
			schema.AttrsAttributesTableIDCol,
			schema.AttrsAttributesTableNameCol,
			schema.AttrsAttributesTableDescriptionCol,
			schema.AttrsAttributesTableTypeIDCol,
			schema.AttrsAttributesTableUnitIDCol,
			schema.AttrsAttributesTableMultipleCol,
			schema.AttrsAttributesTablePrecisionCol,
			schema.AttrsAttributesTableParentIDCol,
		).
			From(schema.AttrsAttributesTable).
			Order(schema.AttrsAttributesTablePositionCol.Asc()).
			ScanStructsContext(ctx, &rows)
		if err != nil {
			return err
		}

		list := make(map[int64]*schema.AttrsAttributeRow, len(rows))
		tree := make(map[int64][]*schema.AttrsAttributeRow)

		for _, row := range rows {
			list[row.ID] = &row

			var parentID int64
			if row.ParentID.Valid {
				parentID = row.ParentID.Int64
			}

			if _, ok := tree[parentID]; !ok {
				tree[parentID] = make([]*schema.AttrsAttributeRow, 0, 1)
			}

			tree[parentID] = append(tree[parentID], &row)
		}

		s.attributes = list
		s.attributesTree = tree
	}

	return nil
}

func (s *Repository) attributesRecursive(
	ctx context.Context, zoneID int64, parentID int64, deep int,
) ([]*AttributeRow, error) {
	var tree map[int64][]*schema.AttrsAttributeRow

	if zoneID > 0 {
		err := s.loadZoneAttributesTree(ctx, zoneID)
		if err != nil {
			return nil, err
		}

		tree = s.zoneAttributesTree[zoneID]
	} else {
		err := s.loadAttributesTree(ctx)
		if err != nil {
			return nil, err
		}

		tree = s.attributesTree
	}

	rows := tree[parentID]

	result := make([]*AttributeRow, 0, len(rows))

	for _, row := range rows {
		childs, err := s.attributesRecursive(ctx, zoneID, row.ID, deep+1)
		if err != nil {
			return nil, err
		}

		result = append(result, &AttributeRow{
			AttrsAttributeRow: *row,
			Childs:            childs,
			Deep:              deep,
		})
	}

	return result, nil
}

func (s *Repository) i18nUnitsMap(ctx context.Context, lang string) (map[int64]I18nUnit, error) {
	s.i18nUnitsMutex.Lock()
	defer s.i18nUnitsMutex.Unlock()

	localizer := s.i18n.Localizer(lang)

	if _, ok := s.i18nUnits[lang]; !ok {
		units, err := s.unitsMap(ctx)
		if err != nil {
			return nil, err
		}

		i18nMap := make(map[int64]I18nUnit, len(units))

		for id, row := range units {
			name, err := localizer.Localize(&i18n.LocalizeConfig{
				DefaultMessage: &i18n.Message{
					ID: row.Name,
				},
			})
			if err != nil {
				return nil, err
			}

			abbr, err := localizer.Localize(&i18n.LocalizeConfig{
				DefaultMessage: &i18n.Message{
					ID: row.Abbr,
				},
			})
			if err != nil {
				return nil, err
			}

			i18nMap[id] = I18nUnit{
				Name: name,
				Abbr: abbr,
			}
		}

		s.i18nUnits[lang] = i18nMap
	}

	return s.i18nUnits[lang], nil
}

func (s *Repository) unitsMap(ctx context.Context) (map[int64]schema.AttrsUnitRow, error) {
	s.unitsMutex.Lock()
	defer s.unitsMutex.Unlock()

	if len(s.units) == 0 {
		rows := make([]schema.AttrsUnitRow, 0)

		err := s.db.Select(schema.AttrsUnitsTableIDCol, schema.AttrsUnitsTableNameCol, schema.AttrsUnitsTableAbbrCol).
			From(schema.AttrsUnitsTable).
			ScanStructsContext(ctx, &rows)
		if err != nil {
			return nil, err
		}

		for _, row := range rows {
			s.units[row.ID] = row
		}
	}

	return s.units, nil
}

func (s *Repository) valueToText(
	ctx context.Context,
	attributeID int64,
	value Value,
	lang string,
) (string, error) {
	if !value.Valid {
		return "", nil
	}

	if value.IsEmpty {
		return "—", nil
	}

	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return "", err
	}

	if attribute == nil {
		return "", fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	switch value.Type {
	case schema.AttrsAttributeTypeIDString:
		return value.StringValue, nil

	case schema.AttrsAttributeTypeIDInteger:
		tag := language.Make(lang)
		printer := message.NewPrinter(tag)
		dec := number.Decimal(value.IntValue)

		return printer.Sprint(dec), nil

	case schema.AttrsAttributeTypeIDFloat:
		tag := language.Make(lang)
		printer := message.NewPrinter(tag)
		opts := make([]number.Option, 0)

		if attribute.Precision.Valid {
			opts = append(opts,
				number.MinFractionDigits(int(attribute.Precision.Int32)),
				number.MaxFractionDigits(int(attribute.Precision.Int32)),
			)
		}

		dec := number.Decimal(value.FloatValue, opts...)

		return printer.Sprint(dec), nil

	case schema.AttrsAttributeTypeIDText:
		return value.StringValue, nil

	case schema.AttrsAttributeTypeIDBoolean:
		msgID := "specifications/boolean/false"
		if value.BoolValue {
			msgID = "specifications/boolean/true"
		}

		localizer := s.i18n.Localizer(lang)

		localized, err := localizer.Localize(&i18n.LocalizeConfig{
			DefaultMessage: &i18n.Message{
				ID: msgID,
			},
		})
		if err != nil {
			return "", err
		}

		return localized, nil

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		text := make([]string, 0, len(value.ListValue))

		for _, v := range value.ListValue {
			option, err := s.ListOptionsText(ctx, attribute.ID, v, lang)
			if err != nil {
				return "", err
			}

			text = append(text, option)
		}

		return strings.Join(text, ", "), nil

	case schema.AttrsAttributeTypeIDUnknown:
	}

	return "", nil
}

func (s *Repository) loadListOptions(ctx context.Context) error {
	s.listOptionsMutex.Lock()
	defer s.listOptionsMutex.Unlock()

	if len(s.listOptions) > 0 {
		return nil
	}

	var rows []schema.AttrsListOptionRow

	err := s.db.Select(
		schema.AttrsListOptionsTableAttributeIDCol,
		schema.AttrsListOptionsTableIDCol,
		schema.AttrsListOptionsTableParentIDCol,
		schema.AttrsListOptionsTableNameCol,
	).
		From(schema.AttrsListOptionsTable).
		Order(schema.AttrsListOptionsTablePositionCol.Asc()).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return err
	}

	for _, row := range rows {
		aid := row.AttributeID
		id := row.ID

		var pid int64

		if row.ParentID.Valid {
			pid = row.ParentID.Int64
		}

		if _, ok := s.listOptions[aid]; !ok {
			s.listOptions[aid] = make(map[int64]string)
		}

		s.listOptions[aid][id] = row.Name

		if _, ok := s.listOptionsChilds[aid]; !ok {
			s.listOptionsChilds[aid] = make(map[int64][]int64)
		}

		if _, ok := s.listOptionsChilds[aid][pid]; !ok {
			s.listOptionsChilds[aid][pid] = []int64{id}
		} else {
			s.listOptionsChilds[aid][pid] = append(s.listOptionsChilds[aid][pid], id)
		}
	}

	return nil
}

func (s *Repository) updateActualValue(ctx context.Context, attributeID, itemID int64) error {
	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return err
	}

	if attribute == nil {
		return fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	_, err = s.updateAttributeActualValue(ctx, attribute, itemID)
	if err != nil {
		return fmt.Errorf("%w: updateAttributeActualValue(%d, %d)", err, attribute.ID, itemID)
	}

	return nil
}

func (s *Repository) updateAttributeActualValue(
	ctx context.Context, attribute *schema.AttrsAttributeRow, itemID int64,
) (bool, error) {
	actualValue, err := s.calcAvgUserValue(ctx, attribute, itemID)
	if err != nil {
		return false, fmt.Errorf("calcAvgUserValue(%d, %d): %w", attribute.ID, itemID, err)
	}

	if !actualValue.Valid {
		actualValue, err = s.calcEngineValue(ctx, attribute.ID, itemID)
		if err != nil {
			return false, fmt.Errorf("calcEngineValue(%d, %d): %w", attribute.ID, itemID, err)
		}
	}

	if !actualValue.Valid {
		actualValue, err = s.calcInheritedValue(ctx, attribute.ID, itemID)
		if err != nil {
			return false, fmt.Errorf("calcInheritedValue(%d, %d): %w", attribute.ID, itemID, err)
		}
	}

	ctx = context.WithoutCancel(ctx)

	somethingChanged, err := s.setActualValue(ctx, attribute, itemID, actualValue)
	if err != nil {
		return false, fmt.Errorf("setActualValue(%d, %d): %w", attribute.ID, itemID, err)
	}

	if somethingChanged {
		err = s.propagateInheritance(ctx, attribute, itemID)
		if err != nil {
			return false, fmt.Errorf("propagateInheritance(%d, %d): %w", attribute.ID, itemID, err)
		}

		err = s.propagateEngine(ctx, attribute, itemID)
		if err != nil {
			return false, fmt.Errorf("propagateEngine(%d, %d): %w", attribute.ID, itemID, err)
		}

		err = s.refreshConflictFlag(ctx, attribute.ID, itemID)
		if err != nil {
			return false, fmt.Errorf("refreshConflictFlag(%d, %d): %w", attribute.ID, itemID, err)
		}
	}

	return somethingChanged, nil
}

type valueItem struct {
	Value Value
	Row   schema.AttrsUserValueRow
}

func (s *Repository) topValue(ctx context.Context, data []valueItem) (Value, error) {
	if len(data) == 0 {
		return Value{}, nil
	}

	idx := 0
	registry := make(map[int]Value, 0)
	freshness := make(map[int]time.Time)
	ratios := make(map[int]float32)

	for _, valueRow := range data {
		// look for same value
		matchRegIdx := -1

		for regIdx, regVal := range registry {
			if regVal.Equals(valueRow.Value) {
				matchRegIdx = regIdx

				break
			}
		}

		if matchRegIdx == -1 {
			registry[idx] = valueRow.Value
			matchRegIdx = idx
			idx++
		}

		if _, ok := ratios[matchRegIdx]; !ok {
			ratios[matchRegIdx] = 0
		}

		weight, err := s.getUserValueWeight(ctx, valueRow.Row.UserID)
		if err != nil {
			return Value{}, err
		}

		ratios[matchRegIdx] += weight

		_, freshnessExists := freshness[matchRegIdx]
		if !freshnessExists || freshness[matchRegIdx].Before(valueRow.Row.UpdateDate) {
			freshness[matchRegIdx] = valueRow.Row.UpdateDate
		}
	}

	// select max
	var (
		maxValueRatio float32
		maxValueIdx   = -1
	)

	for idx, ratio := range ratios {
		if maxValueIdx == -1 || maxValueRatio <= ratio {
			maxValueIdx = idx
			maxValueRatio = ratio
		}
	}

	actualValue := registry[maxValueIdx]

	return actualValue, nil
}

func (s *Repository) calcAvgUserValue(
	ctx context.Context, attribute *schema.AttrsAttributeRow, itemID int64,
) (Value, error) {
	userValueRows, err := s.UserValueRows(ctx, query.AttrsUserValueListOptions{
		AttributeID: attribute.ID,
		ItemID:      itemID,
	})
	if err != nil {
		return Value{}, err
	}

	// group by users
	data := make([]valueItem, 0, len(userValueRows))

	for _, userValueRow := range userValueRows {
		uid := userValueRow.UserID

		value, err := s.UserValue(ctx, userValueRow.AttributeID, userValueRow.ItemID, uid)
		if err != nil {
			return Value{}, err
		}

		data = append(data, valueItem{
			Value: value,
			Row:   userValueRow,
		})
	}

	return s.topValue(ctx, data)
}

func (s *Repository) getUserValueWeight(ctx context.Context, userID int64) (float32, error) {
	var weight float32

	success, err := util.ScanValContextAndRetryOnDeadlock(
		ctx,
		s.db.Select(schema.UserTableSpecsWeightCol).
			From(schema.UserTable).
			Where(schema.UserTableIDCol.Eq(userID)),
		&weight,
	)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, nil
	}

	return weight, nil
}

func (s *Repository) getEngineAttributeIDs(ctx context.Context) ([]int64, error) {
	if len(s.engineAttributes) > 0 {
		return s.engineAttributes, nil
	}

	err := s.db.Select(schema.AttrsZoneAttributesTableAttributeIDCol).
		From(schema.AttrsZoneAttributesTable).
		Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(engineZoneID)).
		ScanValsContext(ctx, &s.engineAttributes)

	return s.engineAttributes, err
}

func (s *Repository) isEngineAttributeID(ctx context.Context, attrID int64) (bool, error) {
	ids, err := s.getEngineAttributeIDs(ctx)
	if err != nil {
		return false, err
	}

	return util.Contains(ids, attrID), nil
}

func (s *Repository) calcEngineValue(
	ctx context.Context,
	attributeID int64,
	itemID int64,
) (Value, error) {
	isEngineAttributeID, err := s.isEngineAttributeID(ctx, attributeID)
	if err != nil {
		return Value{}, err
	}

	if !isEngineAttributeID {
		return Value{}, nil
	}

	var engineItemID sql.NullInt64

	success, err := util.ScanValContextAndRetryOnDeadlock(
		ctx,
		s.db.Select(schema.ItemTableEngineItemIDCol).
			From(schema.ItemTable).
			Where(schema.ItemTableIDCol.Eq(itemID)),
		&engineItemID,
	)
	if err != nil {
		return Value{}, err
	}

	if !success || !engineItemID.Valid {
		return Value{}, nil
	}

	return s.ActualValue(ctx, attributeID, engineItemID.Int64)
}

func (s *Repository) calcInheritedValue(
	ctx context.Context,
	attributeID int64,
	itemID int64,
) (Value, error) {
	valueRows, err := s.Values(ctx, query.AttrsValueListOptions{
		AttributeID: attributeID,
		ChildItemID: itemID,
	}, ValuesOrderByNone)
	if err != nil {
		return Value{}, err
	}

	idx := 0
	registry := make(map[int]Value)
	ratios := make(map[int]int)

	for _, valueRow := range valueRows {
		value, err := s.ActualValue(ctx, valueRow.AttributeID, valueRow.ItemID)
		if err != nil {
			return Value{}, err
		}

		// look for same value
		matchRegIdx := -1

		for regIdx, regVal := range registry {
			if regVal.Equals(value) {
				matchRegIdx = regIdx

				break
			}
		}

		if matchRegIdx == -1 {
			registry[idx] = value
			matchRegIdx = idx
			idx++
		}

		if _, ok := ratios[matchRegIdx]; !ok {
			ratios[matchRegIdx] = 0
		}

		ratios[matchRegIdx]++
	}

	// select max
	maxValueRatio := 0
	maxValueIdx := -1

	for idx, ratio := range ratios {
		if maxValueIdx == -1 || maxValueRatio <= ratio {
			maxValueIdx = idx
			maxValueRatio = ratio
		}
	}

	if maxValueIdx != -1 {
		return registry[maxValueIdx], nil
	}

	return Value{}, nil
}

func (s *Repository) clearStringValue(
	ctx context.Context,
	attributeID, itemID int64,
) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesStringTable).Where(
			schema.AttrsValuesStringTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesStringTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, err
}

func (s *Repository) clearIntValue(ctx context.Context, attributeID, itemID int64) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesIntTable).Where(
			schema.AttrsValuesIntTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesIntTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, err
}

func (s *Repository) clearFloatValue(ctx context.Context, attributeID, itemID int64) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesFloatTable).Where(
			schema.AttrsValuesFloatTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesFloatTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, err
}

func (s *Repository) clearListValue(ctx context.Context, attributeID, itemID int64) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesListTable).Where(
			schema.AttrsValuesListTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesListTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, err
}

func (s *Repository) clearValue(
	ctx context.Context,
	attribute *schema.AttrsAttributeRow,
	itemID int64,
) (bool, error) {
	var (
		somethingChanges = false
		err              error
		res              sql.Result
	)

	ctx = context.WithoutCancel(ctx)

	// value
	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		somethingChanges, err = s.clearStringValue(ctx, attribute.ID, itemID)

	case schema.AttrsAttributeTypeIDInteger, schema.AttrsAttributeTypeIDBoolean:
		somethingChanges, err = s.clearIntValue(ctx, attribute.ID, itemID)

	case schema.AttrsAttributeTypeIDFloat:
		somethingChanges, err = s.clearFloatValue(ctx, attribute.ID, itemID)

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		somethingChanges, err = s.clearListValue(ctx, attribute.ID, itemID)

	case schema.AttrsAttributeTypeIDUnknown:
	}

	if err != nil {
		return false, err
	}

	// descriptor
	res, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesTable).Where(
			schema.AttrsValuesTableAttributeIDCol.Eq(attribute.ID),
			schema.AttrsValuesTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return somethingChanges || affected > 0, nil
}

func (s *Repository) setStringValue(
	ctx context.Context, attributeID, itemID int64, value string, isEmpty bool,
) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsValuesStringTable).Rows(goqu.Record{
			schema.AttrsValuesStringTableAttributeIDColName: attributeID,
			schema.AttrsValuesStringTableItemIDColName:      itemID,
			schema.AttrsValuesStringTableValueColName: sql.NullString{
				String: value,
				Valid:  !isEmpty,
			},
		}).OnConflict(
			goqu.DoUpdate(
				schema.AttrsValuesStringTableAttributeIDColName+","+schema.AttrsValuesStringTableItemIDColName,
				goqu.Record{
					schema.AttrsValuesStringTableValueColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsValuesStringTableValueColName),
					),
				},
			)).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()

	return affected > 0, err
}

func (s *Repository) setIntValue(
	ctx context.Context, attributeID, itemID int64, value int32, isEmpty bool,
) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsValuesIntTable).Rows(goqu.Record{
			schema.AttrsValuesIntTableAttributeIDColName: attributeID,
			schema.AttrsValuesIntTableItemIDColName:      itemID,
			schema.AttrsValuesIntTableValueColName: sql.NullInt32{
				Int32: value,
				Valid: !isEmpty,
			},
		}).OnConflict(
			goqu.DoUpdate(
				schema.AttrsValuesIntTableAttributeIDColName+","+schema.AttrsValuesIntTableItemIDColName,
				goqu.Record{
					schema.AttrsValuesIntTableValueColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsValuesIntTableValueColName),
					),
				},
			)).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()

	return affected > 0, err
}

func (s *Repository) setFloatValue(
	ctx context.Context, attributeID, itemID int64, value float64, isEmpty bool,
) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsValuesFloatTable).Rows(goqu.Record{
			schema.AttrsValuesFloatTableAttributeIDColName: attributeID,
			schema.AttrsValuesFloatTableItemIDColName:      itemID,
			schema.AttrsValuesFloatTableValueColName: sql.NullFloat64{
				Float64: value,
				Valid:   !isEmpty,
			},
		}).OnConflict(
			goqu.DoUpdate(
				schema.AttrsValuesFloatTableAttributeIDColName+","+schema.AttrsValuesFloatTableItemIDColName,
				goqu.Record{
					schema.AttrsValuesFloatTableValueColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsValuesFloatTableValueColName),
					),
				},
			)).Executor(),
	)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()

	return affected > 0, err
}

func (s *Repository) setListValue(
	ctx context.Context, attributeID, itemID int64, value []int64, isEmpty bool,
) (bool, error) {
	var ( //nolint: prealloc
		records   []goqu.Record
		orderings []int
	)

	if isEmpty {
		records = []goqu.Record{{
			schema.AttrsValuesListTableAttributeIDColName: attributeID,
			schema.AttrsValuesListTableItemIDColName:      itemID,
			schema.AttrsValuesListTableOrderingColName:    0,
			schema.AttrsValuesListTableValueColName:       nil,
		}}
		orderings = []int{0}
	} else {
		records = make([]goqu.Record, 0, len(value))
		orderings = make([]int, 0, len(value))
	}

	for index, listValue := range value {
		records = append(records, goqu.Record{
			schema.AttrsValuesListTableAttributeIDColName: attributeID,
			schema.AttrsValuesListTableItemIDColName:      itemID,
			schema.AttrsValuesListTableOrderingColName:    index,
			schema.AttrsValuesListTableValueColName:       listValue,
		})

		orderings = append(orderings, index)
	}

	ctx = context.WithoutCancel(ctx)

	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsValuesListTable).Rows(records).OnConflict(
			goqu.DoUpdate(
				schema.AttrsValuesListTableAttributeIDColName+","+
					schema.AttrsValuesListTableItemIDColName+","+
					schema.AttrsValuesListTableOrderingColName,
				goqu.Record{
					schema.AttrsValuesListTableValueColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsValuesListTableValueColName),
					),
				},
			)).Executor(),
	)
	if err != nil {
		return false, err
	}

	inserted, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	res, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Delete(schema.AttrsValuesListTable).Where(
			schema.AttrsValuesListTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesListTableItemIDCol.Eq(itemID),
			schema.AttrsValuesListTableOrderingCol.NotIn(orderings),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	deleted, err := res.RowsAffected()

	return inserted > 0 || deleted > 0, err
}

func (s *Repository) setActualValue(
	ctx context.Context, attribute *schema.AttrsAttributeRow, itemID int64, actualValue Value,
) (bool, error) {
	if !attribute.TypeID.Valid {
		return false, nil
	}

	ctx = context.WithoutCancel(ctx)

	if !actualValue.Valid {
		res, err := s.clearValue(ctx, attribute, itemID)
		if err != nil {
			return false, fmt.Errorf("clearValue(%d, %d): %w", attribute.ID, itemID, err)
		}

		return res, nil
	}

	var err error

	// descriptor
	_, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(schema.AttrsValuesTable).Rows(goqu.Record{
			schema.AttrsValuesTableAttributeIDColName: attribute.ID,
			schema.AttrsValuesTableItemIDColName:      itemID,
			schema.AttrsValuesTableUpdateDateColName:  goqu.Func("NOW"),
		}).OnConflict(
			goqu.DoUpdate(
				schema.AttrsValuesTableAttributeIDColName+","+schema.AttrsValuesTableItemIDColName,
				goqu.Record{
					schema.AttrsValuesTableUpdateDateColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsValuesTableUpdateDateColName),
					),
				},
			),
		).Executor(),
	)
	if err != nil {
		return false, err
	}

	// value
	valueChanged := false

	switch attribute.TypeID.AttributeTypeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		valueChanged, err = s.setStringValue(
			ctx,
			attribute.ID,
			itemID,
			actualValue.StringValue,
			actualValue.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setStringValue(%d, %d, %s, %t): %w",
				attribute.ID, itemID, actualValue.StringValue, actualValue.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDInteger:
		valueChanged, err = s.setIntValue(
			ctx,
			attribute.ID,
			itemID,
			actualValue.IntValue,
			actualValue.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setIntValue(%d, %d, %d, %t): %w",
				attribute.ID, itemID, actualValue.IntValue, actualValue.IsEmpty, err,
			)
		}

	case schema.AttrsAttributeTypeIDBoolean:
		var value int32
		if actualValue.BoolValue {
			value = 1
		}

		valueChanged, err = s.setIntValue(ctx, attribute.ID, itemID, value, actualValue.IsEmpty)
		if err != nil {
			return false, fmt.Errorf(
				"setIntValue(%d, %d, %d, %t): %w",
				attribute.ID,
				itemID,
				value,
				actualValue.IsEmpty,
				err,
			)
		}

	case schema.AttrsAttributeTypeIDFloat:
		valueChanged, err = s.setFloatValue(
			ctx,
			attribute.ID,
			itemID,
			actualValue.FloatValue,
			actualValue.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setFloatValue(%d, %d, %f, %t): %w",
				attribute.ID,
				itemID,
				actualValue.FloatValue,
				actualValue.IsEmpty,
				err,
			)
		}

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		valueChanged, err = s.setListValue(
			ctx,
			attribute.ID,
			itemID,
			actualValue.ListValue,
			actualValue.IsEmpty,
		)
		if err != nil {
			return false, fmt.Errorf(
				"setFloatValue(%d, %d, %v, %t): %w",
				attribute.ID,
				itemID,
				actualValue.ListValue,
				actualValue.IsEmpty,
				err,
			)
		}

	case schema.AttrsAttributeTypeIDUnknown:
	}

	return valueChanged, nil
}

func (s *Repository) setScalarUserValue(
	ctx context.Context,
	attributeID, itemID, userID int64,
	table exp.IdentifierExpression,
	sqlValue interface{},
) (bool, error) {
	res, err := util.ExecAndRetryOnDeadlock(ctx,
		s.db.Insert(table).Rows(goqu.Record{
			schema.AttrsUserValuesTypeTableAttributeIDColName: attributeID,
			schema.AttrsUserValuesTypeTableItemIDColName:      itemID,
			schema.AttrsUserValuesTypeTableUserIDColName:      userID,
			schema.AttrsUserValuesTypeTableValueColName:       sqlValue,
		}).Executor(),
	)
	if err != nil {
		if !util.IsMysqlDuplicateKeyError(err) {
			return false, err
		}

		res, err = util.ExecAndRetryOnDeadlock(ctx,
			s.db.Update(table).Set(goqu.Record{
				schema.AttrsUserValuesTypeTableValueColName: sqlValue,
			}).Where(
				table.Col(schema.AttrsUserValuesTypeTableAttributeIDColName).Eq(attributeID),
				table.Col(schema.AttrsUserValuesStringTableItemIDColName).Eq(itemID),
				table.Col(schema.AttrsUserValuesStringTableUserIDColName).Eq(userID),
			).Executor(),
		)
		if err != nil {
			return false, err
		}
	}

	affected, err := res.RowsAffected()

	return affected > 0, err
}

func (s *Repository) setStringUserValue(
	ctx context.Context, attributeID, itemID, userID int64, value string, isEmpty bool,
) (bool, error) {
	return s.setScalarUserValue(
		ctx,
		attributeID,
		itemID,
		userID,
		schema.AttrsUserValuesStringTable,
		sql.NullString{
			String: value,
			Valid:  !isEmpty,
		},
	)
}

func (s *Repository) setIntUserValue(
	ctx context.Context, attributeID, itemID, userID int64, value int32, isEmpty bool,
) (bool, error) {
	return s.setScalarUserValue(
		ctx,
		attributeID,
		itemID,
		userID,
		schema.AttrsUserValuesIntTable,
		sql.NullInt32{
			Int32: value,
			Valid: !isEmpty,
		},
	)
}

func (s *Repository) setFloatUserValue(
	ctx context.Context, attributeID, itemID, userID int64, value float64, isEmpty bool,
) (bool, error) {
	return s.setScalarUserValue(
		ctx,
		attributeID,
		itemID,
		userID,
		schema.AttrsUserValuesFloatTable,
		sql.NullFloat64{
			Float64: value,
			Valid:   !isEmpty,
		},
	)
}

func (s *Repository) setListUserValue(
	ctx context.Context,
	attribute *schema.AttrsAttributeRow,
	itemID, userID int64,
	value []int64,
	isEmpty bool,
) (bool, error) {
	var (
		err      error
		affected int64
		res      sql.Result
		deleted  int64
	)

	ctx = context.WithoutCancel(ctx)

	insertExpr := s.db.Insert(schema.AttrsUserValuesListTable).Cols(
		schema.AttrsUserValuesListTableAttributeIDCol,
		schema.AttrsUserValuesListTableItemIDCol,
		schema.AttrsUserValuesListTableUserIDCol,
		schema.AttrsUserValuesListTableOrderingCol,
		schema.AttrsUserValuesListTableValueCol,
	).
		OnConflict(
			goqu.DoUpdate(
				schema.AttrsUserValuesListTableAttributeIDColName+","+
					schema.AttrsUserValuesListTableItemIDColName+","+
					schema.AttrsUserValuesListTableUserIDColName+","+
					schema.AttrsUserValuesListTableOrderingColName,
				goqu.Record{
					schema.AttrsUserValuesListTableValueColName: goqu.Func(
						"VALUES",
						goqu.C(schema.AttrsUserValuesListTableValueColName),
					),
				},
			))

	if isEmpty {
		res, err = util.ExecAndRetryOnDeadlock(ctx,
			insertExpr.Vals([]interface{}{attribute.ID, itemID, userID, 0, nil}).Executor(),
		)
		if err != nil {
			return false, fmt.Errorf("error inserting attrs user list value: %w", err)
		}

		affected, err = res.RowsAffected()
		if err != nil {
			return false, err
		}
	} else if len(value) > 0 {
		sqSelect := s.db.Select(
			schema.AttrsListOptionsTableAttributeIDCol,
			goqu.V(itemID),
			goqu.V(userID),
			goqu.L("ROW_NUMBER() OVER(ORDER BY ?)", schema.AttrsListOptionsTablePositionCol),
			schema.AttrsListOptionsTableIDCol,
		).
			From(schema.AttrsListOptionsTable).
			Where(
				schema.AttrsListOptionsTableAttributeIDCol.Eq(attribute.ID),
				schema.AttrsListOptionsTableIDCol.In(value),
			)

		if !attribute.Multiple {
			sqSelect = sqSelect.Limit(1)
		}

		res, err = util.ExecAndRetryOnDeadlock(ctx,
			insertExpr.FromQuery(sqSelect).Executor(),
		)
		if err != nil {
			return false, fmt.Errorf("error inserting attrs user list value: %w", err)
		}

		affected, err = res.RowsAffected()
		if err != nil {
			return false, err
		}
	}

	deleteExpr := s.db.Delete(schema.AttrsUserValuesListTable).Where(
		schema.AttrsUserValuesListTableAttributeIDCol.Eq(attribute.ID),
		schema.AttrsUserValuesListTableItemIDCol.Eq(itemID),
		schema.AttrsUserValuesListTableUserIDCol.Eq(userID),
	)

	if isEmpty {
		deleteExpr = deleteExpr.Where(schema.AttrsUserValuesListTableValueCol.IsNotNull())
	} else if len(value) > 0 {
		deleteExpr = deleteExpr.Where(schema.AttrsUserValuesListTableValueCol.NotIn(value))
	}

	res, err = util.ExecAndRetryOnDeadlock(ctx, deleteExpr.Executor())
	if err != nil {
		return false, fmt.Errorf("error deleting attrs user list value: %w", err)
	}

	deleted, err = res.RowsAffected()
	if err != nil {
		return false, err
	}

	return deleted > 0 || affected > 0, err
}

func (s *Repository) propagateInheritance(
	ctx context.Context, attribute *schema.AttrsAttributeRow, itemID int64,
) error {
	var childIDs []int64

	err := s.db.Select(schema.ItemParentTableItemIDCol).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(itemID)).
		ScanValsContext(ctx, &childIDs)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, childID := range childIDs {
		// update only if row use inheritance
		haveValue, err := s.haveOwnAttributeValue(ctx, attribute.ID, childID)
		if err != nil {
			return err
		}

		if !haveValue {
			value, err := s.calcInheritedValue(ctx, attribute.ID, childID)
			if err != nil {
				return err
			}

			changed, err := s.setActualValue(ctx, attribute, childID, value)
			if err != nil {
				return err
			}

			if changed {
				err = s.propagateInheritance(ctx, attribute, childID)
				if err != nil {
					return err
				}

				err = s.propagateEngine(ctx, attribute, childID)
				if err != nil {
					return err
				}
			}
		}
	}

	return nil
}

func (s *Repository) haveOwnAttributeValue(
	ctx context.Context,
	attributeID, itemID int64,
) (bool, error) {
	var exists bool

	success, err := util.ScanValContextAndRetryOnDeadlock(
		ctx,
		s.db.Select(goqu.V(true)).From(schema.AttrsUserValuesTable).Where(
			schema.AttrsUserValuesTableAttributeIDCol.Eq(attributeID),
			schema.AttrsUserValuesTableItemIDCol.Eq(itemID),
		),
		&exists,
	)

	return success && exists, err
}

func (s *Repository) propagateEngine(
	ctx context.Context,
	attribute *schema.AttrsAttributeRow,
	itemID int64,
) error {
	isEngineAttributeID, err := s.isEngineAttributeID(ctx, attribute.ID)
	if err != nil {
		return err
	}

	if !isEngineAttributeID {
		return nil
	}

	if !attribute.TypeID.Valid {
		return nil
	}

	var vehicleIDs []int64

	err = s.db.Select(schema.ItemTableIDCol).From(schema.ItemTable).Where(
		schema.ItemTableEngineItemIDCol.Eq(itemID),
	).ScanValsContext(ctx, &vehicleIDs)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, vehicleID := range vehicleIDs {
		_, err = s.updateAttributeActualValue(ctx, attribute, vehicleID)
		if err != nil {
			return fmt.Errorf(
				"%w: updateAttributeActualValue(%d, %d)",
				errAttributeNotFound,
				attribute.ID,
				itemID,
			)
		}
	}

	return nil
}

func (s *Repository) refreshConflictFlag(ctx context.Context, attributeID, itemID int64) error {
	if itemID <= 0 {
		return errInvalidItemID
	}

	attribute, err := s.Attribute(ctx, attributeID)
	if err != nil {
		return err
	}

	if attribute == nil {
		return fmt.Errorf("%w: `%d`", errAttributeNotFound, attributeID)
	}

	userValueRows, err := s.UserValueRows(ctx, query.AttrsUserValueListOptions{
		AttributeID: attributeID,
		ItemID:      itemID,
	})
	if err != nil {
		return err
	}

	type userValueItem struct {
		Value Value
		Row   schema.AttrsUserValueRow
	}

	userValues := make(map[int64]userValueItem)
	hasConflict := false

	for _, userValueRow := range userValueRows {
		val, err := s.UserValue(
			ctx,
			attribute.ID,
			itemID,
			userValueRow.UserID,
		)
		if err != nil {
			return err
		}

		userValues[userValueRow.UserID] = userValueItem{
			Value: val,
			Row:   userValueRow,
		}

		if !hasConflict {
			for _, uv := range userValues {
				if !uv.Value.Equals(val) {
					hasConflict = true

					break
				}
			}
		}
	}

	ctx = context.WithoutCancel(ctx)

	_, err = util.ExecAndRetryOnDeadlock(ctx,
		s.db.Update(schema.AttrsValuesTable).Set(goqu.Record{
			schema.AttrsValuesTableConflictColName: hasConflict,
		}).Where(
			schema.AttrsValuesTableAttributeIDCol.Eq(attributeID),
			schema.AttrsValuesTableItemIDCol.Eq(itemID),
		).Executor(),
	)
	if err != nil {
		return err
	}

	affectedUserIDs := make([]int64, 0)

	if hasConflict {
		actualValue, err := s.ActualValue(ctx, attributeID, itemID)
		if err != nil {
			return err
		}

		minDate := time.Now() // min date of actual value
		actualValueVoters := 0

		for _, userValue := range userValues {
			if userValue.Value.Equals(actualValue) {
				actualValueVoters++

				if minDate.After(userValue.Row.UpdateDate) {
					minDate = userValue.Row.UpdateDate
				}
			}
		}

		for userID, userValue := range userValues {
			matchActual := userValue.Value.Equals(actualValue)

			conflict := -1
			if matchActual {
				conflict = 1
			}

			weight := weightNone
			if actualValueVoters > 1 {
				weight = weightWrong

				if matchActual {
					weight = weightFirstActual

					if userValue.Row.UpdateDate.Equal(minDate) {
						weight = weightSecondActual
					}
				}
			}

			res, err := util.ExecAndRetryOnDeadlock(ctx,
				s.db.Update(schema.AttrsUserValuesTable).Set(goqu.Record{
					schema.AttrsUserValuesTableConflictColName: conflict,
					schema.AttrsUserValuesTableWeightColName:   weight,
				}).Where(
					schema.AttrsUserValuesTableUserIDCol.Eq(userID),
					schema.AttrsUserValuesTableAttributeIDCol.Eq(attributeID),
					schema.AttrsUserValuesTableItemIDCol.Eq(itemID),
				).Executor(),
			)
			if err != nil {
				return err
			}

			affected, err := res.RowsAffected()
			if err != nil {
				return err
			}

			if affected > 0 {
				affectedUserIDs = append(affectedUserIDs, userID)
			}
		}
	} else {
		res, err := util.ExecAndRetryOnDeadlock(ctx,
			s.db.Update(schema.AttrsUserValuesTable).Set(goqu.Record{
				schema.AttrsUserValuesTableConflictColName: 0,
				schema.AttrsUserValuesTableWeightColName:   weightNone,
			}).Where(
				schema.AttrsUserValuesTableAttributeIDCol.Eq(attributeID),
				schema.AttrsUserValuesTableItemIDCol.Eq(itemID),
			).Executor(),
		)
		if err != nil {
			return err
		}

		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}

		if affected > 0 {
			for _, userValue := range userValues {
				affectedUserIDs = append(affectedUserIDs, userValue.Row.UserID)
			}
		}
	}

	return s.RefreshUserConflictsStat(ctx, affectedUserIDs, false)
}

func (s *Repository) zoneByItemsList(ctx context.Context, list []*items.Item) (int64, error) {
	ids := make(map[int64]bool)

	for _, car := range list {
		vehicleTypeIDs, err := s.itemsRepository.VehicleTypeIDs(ctx, car.ID, false)
		if err != nil {
			return 0, fmt.Errorf("VehicleTypes(): %w", err)
		}

		zoneID := s.ZoneIDByVehicleTypeIDs(car.ItemTypeID, vehicleTypeIDs)
		ids[zoneID] = true
	}

	var res int64

	if len(ids) == 1 {
		for id := range ids {
			res = id

			break
		}
	}

	return res, nil
}

func (s *Repository) actualValuesToText(
	ctx context.Context, actualValues map[int64]map[int64]Value, lang string,
) (map[int64]map[int64]string, error) {
	var err error

	res := make(map[int64]map[int64]string)

	for itemID, itemActualValues := range actualValues {
		itemValuesMap := make(map[int64]string)
		for attributeID, value := range itemActualValues {
			itemValuesMap[attributeID], err = s.valueToText(ctx, attributeID, value, lang)
			if err != nil {
				return nil, fmt.Errorf("valueToText(): %w", err)
			}
		}

		res[itemID] = itemValuesMap
	}

	return res, nil
}

func (s *Repository) listValuesRowsToMap(
	ctx context.Context, rows []schema.AttrsValuesListRow,
) (map[int64]map[int64]Value, error) {
	values := make(map[int64]map[int64]Value)

	for _, row := range rows {
		if _, ok := values[row.ItemID]; !ok {
			values[row.ItemID] = make(map[int64]Value)
		}

		attr, err := s.Attribute(ctx, row.AttributeID)
		if err != nil {
			return nil, err
		}

		if attr == nil {
			return nil, fmt.Errorf("%w: `%d`", errAttributeNotFound, row.AttributeID)
		}

		value, ok := values[row.ItemID][row.AttributeID]
		if !ok {
			value = Value{
				Valid:     true,
				Type:      attr.TypeID.AttributeTypeID,
				IsEmpty:   !row.Value.Valid,
				ListValue: []int64{},
			}
		}

		if row.Value.Valid {
			value.ListValue = append(value.ListValue, row.Value.Int64)
		}

		values[row.ItemID][row.AttributeID] = value
	}

	return values, nil
}

func (s *Repository) stringValuesRowsToMap(
	rows []schema.AttrsValuesStringRow,
) map[int64]map[int64]Value {
	values := make(map[int64]map[int64]Value)

	for _, row := range rows {
		value := Value{
			Valid:       true,
			Type:        schema.AttrsAttributeTypeIDString,
			IsEmpty:     !row.Value.Valid,
			StringValue: row.Value.String,
		}

		if _, ok := values[row.ItemID]; !ok {
			values[row.ItemID] = make(map[int64]Value)
		}

		values[row.ItemID][row.AttributeID] = value
	}

	return values
}

func (s *Repository) intValuesRowsToMap(
	ctx context.Context, rows []schema.AttrsValuesIntRow,
) (map[int64]map[int64]Value, error) {
	values := make(map[int64]map[int64]Value)

	for _, row := range rows {
		attr, err := s.Attribute(ctx, row.AttributeID)
		if err != nil {
			return nil, err
		}

		if attr == nil {
			return nil, fmt.Errorf("%w: `%d`", errAttributeNotFound, row.AttributeID)
		}

		if !attr.TypeID.Valid {
			return nil, fmt.Errorf(
				"%w: Valid=false for attribute_id=%d",
				errAttrTypeUnexpected,
				attr.ID,
			)
		}

		value := Value{
			Valid:   true,
			Type:    attr.TypeID.AttributeTypeID,
			IsEmpty: !row.Value.Valid,
		}

		switch attr.TypeID.AttributeTypeID {
		case schema.AttrsAttributeTypeIDInteger:
			value.IntValue = row.Value.Int32
		case schema.AttrsAttributeTypeIDBoolean:
			value.BoolValue = row.Value.Int32 > 0
		case schema.AttrsAttributeTypeIDUnknown,
			schema.AttrsAttributeTypeIDString,
			schema.AttrsAttributeTypeIDFloat,
			schema.AttrsAttributeTypeIDText,
			schema.AttrsAttributeTypeIDList,
			schema.AttrsAttributeTypeIDTree:
			return nil, fmt.Errorf("%w: type_id=%v for attribute_id=%d",
				errAttrTypeUnexpected, attr.TypeID.AttributeTypeID, attr.ID)
		}

		if _, ok := values[row.ItemID]; !ok {
			values[row.ItemID] = make(map[int64]Value)
		}

		values[row.ItemID][row.AttributeID] = value
	}

	return values, nil
}

func (s *Repository) floatValuesRowsToMap(
	rows []schema.AttrsValuesFloatRow,
) map[int64]map[int64]Value {
	values := make(map[int64]map[int64]Value)

	for _, row := range rows {
		value := Value{
			Valid:      true,
			FloatValue: row.Value.Float64,
			Type:       schema.AttrsAttributeTypeIDFloat,
			IsEmpty:    !row.Value.Valid,
		}

		if _, ok := values[row.ItemID]; !ok {
			values[row.ItemID] = make(map[int64]Value)
		}

		values[row.ItemID][row.AttributeID] = value
	}

	return values
}

func (s *Repository) zoneItemsListValuesRows(
	ctx context.Context, zoneID int64, itemIDs []int64,
) ([]schema.AttrsValuesListRow, error) {
	rows := make([]schema.AttrsValuesListRow, 0)

	sqSelect := s.db.Select(
		schema.AttrsValuesListTableAttributeIDCol, schema.AttrsValuesListTableItemIDCol,
		schema.AttrsValuesListTableValueCol,
	).
		From(schema.AttrsValuesListTable).
		Where(
			schema.AttrsValuesListTableItemIDCol.In(itemIDs),
			schema.AttrsValuesListTableValueCol.IsNotNull(),
		)

	if zoneID > 0 {
		sqSelect = sqSelect.Join(schema.AttrsZoneAttributesTable, goqu.On(
			schema.AttrsValuesListTableAttributeIDCol.Eq(
				schema.AttrsZoneAttributesTableAttributeIDCol,
			),
		)).Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID))
	}

	err := sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) zoneItemsStringValuesRows(
	ctx context.Context, zoneID int64, itemIDs []int64,
) ([]schema.AttrsValuesStringRow, error) {
	rows := make([]schema.AttrsValuesStringRow, 0)

	sqSelect := s.db.Select(
		schema.AttrsValuesStringTableAttributeIDCol, schema.AttrsValuesStringTableItemIDCol,
		schema.AttrsValuesStringTableValueCol,
	).
		From(schema.AttrsValuesStringTable).
		Where(
			schema.AttrsValuesStringTableItemIDCol.In(itemIDs),
			schema.AttrsValuesStringTableValueCol.IsNotNull(),
		)

	if zoneID > 0 {
		sqSelect = sqSelect.Join(schema.AttrsZoneAttributesTable, goqu.On(
			schema.AttrsValuesStringTableAttributeIDCol.Eq(
				schema.AttrsZoneAttributesTableAttributeIDCol,
			),
		)).Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID))
	}

	err := sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) zoneItemsIntValuesRows(
	ctx context.Context, zoneID int64, itemIDs []int64,
) ([]schema.AttrsValuesIntRow, error) {
	rows := make([]schema.AttrsValuesIntRow, 0)

	sqSelect := s.db.Select(
		schema.AttrsValuesIntTableAttributeIDCol, schema.AttrsValuesIntTableItemIDCol,
		schema.AttrsValuesIntTableValueCol,
	).
		From(schema.AttrsValuesIntTable).
		Where(
			schema.AttrsValuesIntTableItemIDCol.In(itemIDs),
			schema.AttrsValuesIntTableValueCol.IsNotNull(),
		)

	if zoneID > 0 {
		sqSelect = sqSelect.Join(schema.AttrsZoneAttributesTable, goqu.On(
			schema.AttrsValuesIntTableAttributeIDCol.Eq(
				schema.AttrsZoneAttributesTableAttributeIDCol,
			),
		)).Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID))
	}

	err := sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) zoneItemsFloatValuesRows(
	ctx context.Context, zoneID int64, itemIDs []int64,
) ([]schema.AttrsValuesFloatRow, error) {
	rows := make([]schema.AttrsValuesFloatRow, 0)

	sqSelect := s.db.Select(
		schema.AttrsValuesFloatTableAttributeIDCol, schema.AttrsValuesFloatTableItemIDCol,
		schema.AttrsValuesFloatTableValueCol,
	).
		From(schema.AttrsValuesFloatTable).
		Where(
			schema.AttrsValuesFloatTableItemIDCol.In(itemIDs),
			schema.AttrsValuesFloatTableValueCol.IsNotNull(),
		)

	if zoneID > 0 {
		sqSelect = sqSelect.Join(schema.AttrsZoneAttributesTable, goqu.On(
			schema.AttrsValuesFloatTableAttributeIDCol.Eq(
				schema.AttrsZoneAttributesTableAttributeIDCol,
			),
		)).Where(schema.AttrsZoneAttributesTableZoneIDCol.Eq(zoneID))
	}

	err := sqSelect.ScanStructsContext(ctx, &rows)

	return rows, err
}

type ValueTable struct {
	Table              exp.IdentifierExpression
	AttributeIDCol     exp.IdentifierExpression
	AttributeIDColName string
	ValueCol           exp.IdentifierExpression
	ValueColName       string
	ItemIDCol          exp.IdentifierExpression
	ItemIDColName      string
}

func ValueTableByType(typeID schema.AttrsAttributeTypeID) (ValueTable, error) {
	switch typeID {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		return ValueTable{
			Table:              schema.AttrsValuesStringTable,
			AttributeIDCol:     schema.AttrsValuesStringTableAttributeIDCol,
			AttributeIDColName: schema.AttrsValuesStringTableAttributeIDColName,
			ValueCol:           schema.AttrsValuesStringTableValueCol,
			ValueColName:       schema.AttrsValuesStringTableValueColName,
			ItemIDCol:          schema.AttrsValuesStringTableItemIDCol,
			ItemIDColName:      schema.AttrsValuesStringTableItemIDColName,
		}, nil
	case schema.AttrsAttributeTypeIDInteger:
		return ValueTable{
			Table:              schema.AttrsValuesIntTable,
			AttributeIDCol:     schema.AttrsValuesIntTableAttributeIDCol,
			AttributeIDColName: schema.AttrsValuesIntTableAttributeIDColName,
			ValueCol:           schema.AttrsValuesIntTableValueCol,
			ValueColName:       schema.AttrsValuesIntTableValueColName,
			ItemIDCol:          schema.AttrsValuesIntTableItemIDCol,
			ItemIDColName:      schema.AttrsValuesIntTableItemIDColName,
		}, nil
	case schema.AttrsAttributeTypeIDFloat:
		return ValueTable{
			Table:              schema.AttrsValuesFloatTable,
			AttributeIDCol:     schema.AttrsValuesFloatTableAttributeIDCol,
			AttributeIDColName: schema.AttrsValuesFloatTableAttributeIDColName,
			ValueCol:           schema.AttrsValuesFloatTableValueCol,
			ValueColName:       schema.AttrsValuesFloatTableValueColName,
			ItemIDCol:          schema.AttrsValuesFloatTableItemIDCol,
			ItemIDColName:      schema.AttrsValuesFloatTableItemIDColName,
		}, nil
	case schema.AttrsAttributeTypeIDList,
		schema.AttrsAttributeTypeIDTree,
		schema.AttrsAttributeTypeIDBoolean,
		schema.AttrsAttributeTypeIDUnknown:
		return ValueTable{}, fmt.Errorf("%w: '%d'", errAttributeTypeNotSupported, typeID)
	}

	return ValueTable{}, fmt.Errorf("%w: '%d'", errAttributeTypeNotSupported, typeID)
}

func (s *Repository) specPicture(
	ctx context.Context, itemID int64, orderBy pictures.OrderBy,
) (*CarSpecTableItemImage, string, error) {
	row, err := s.picturesRepository.Picture(ctx, &query.PictureListOptions{
		Status: schema.PictureStatusAccepted,
		PictureItem: &query.PictureItemListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
				ParentID: itemID,
			},
		},
	}, nil, orderBy)
	if err != nil {
		return nil, "", err
	}

	var result *CarSpecTableItemImage

	if row.ImageID.Valid {
		image, err := s.imageStorage.FormattedImage(ctx, int(row.ImageID.Int64), "picture-thumb-medium")
		if err != nil {
			return nil, "", fmt.Errorf("FormattedImage(): %w", err)
		}

		if image != nil {
			result = &CarSpecTableItemImage{
				Src:    image.Src(),
				Width:  image.Width(),
				Height: image.Height(),
			}
		}
	}

	pictureURL := frontend.PicturePath(row.Identity)

	return result, pictureURL, nil
}

func (s *Repository) removeEmpty(
	attributes []*AttributeRow,
	cars []CarSpecTableItem,
) []*AttributeRow {
	result := make([]*AttributeRow, 0)

	for _, attribute := range attributes {
		attribute.Childs = s.removeEmpty(attribute.Childs, cars)

		haveValue := len(attribute.Childs) > 0
		if !haveValue {
			id := attribute.ID
			for _, car := range cars {
				if _, ok := car.Values[id]; ok {
					haveValue = true

					break
				}
			}
		}

		if haveValue {
			result = append(result, attribute)
		}
	}

	return result
}

func (s *Repository) flatternAttributes(attributes []*AttributeRow) []*AttributeRow {
	result := make([]*AttributeRow, 0)

	for _, attribute := range attributes {
		result = append(result, attribute)
		result = append(result, s.flatternAttributes(attribute.Childs)...)
	}

	return result
}

func (s *Repository) specIDs(ctx context.Context, id int32) ([]int32, error) {
	var ids []int32

	err := s.db.Select(schema.SpecTableIDCol).
		From(schema.SpecTable).
		Where(schema.SpecTableParentIDCol.Eq(id)).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return nil, err
	}

	result := []int32{id}

	for _, pid := range ids {
		cids, err := s.specIDs(ctx, pid)
		if err != nil {
			return nil, err
		}

		result = append(result, cids...)
	}

	return append(ids, result...), nil
}
