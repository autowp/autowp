package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

type ItemTableItemTypeID int

const (
	ItemTableItemTypeIDVehicle   ItemTableItemTypeID = 1
	ItemTableItemTypeIDEngine    ItemTableItemTypeID = 2
	ItemTableItemTypeIDCategory  ItemTableItemTypeID = 3
	ItemTableItemTypeIDTwins     ItemTableItemTypeID = 4
	ItemTableItemTypeIDBrand     ItemTableItemTypeID = 5
	ItemTableItemTypeIDFactory   ItemTableItemTypeID = 6
	ItemTableItemTypeIDMuseum    ItemTableItemTypeID = 7
	ItemTableItemTypeIDPerson    ItemTableItemTypeID = 8
	ItemTableItemTypeIDCopyright ItemTableItemTypeID = 9

	ItemTableName                          = "item"
	ItemTableNameColName                   = "name"
	ItemTableCatnameColName                = "catname"
	ItemTableEngineItemIDColName           = "engine_item_id"
	ItemTableEngineInheritColName          = "engine_inherit"
	ItemTableItemTypeIDColName             = "item_type_id"
	ItemTableIsConceptColName              = "is_concept"
	ItemTableIsConceptInheritColName       = "is_concept_inherit"
	ItemTableSpecIDColName                 = "spec_id"
	ItemTableIDColName                     = "id"
	ItemTableFullNameColName               = "full_name"
	ItemTableLogoIDColName                 = "logo_id"
	ItemTableBeginYearColName              = "begin_year"
	ItemTableEndYearColName                = "end_year"
	ItemTableBeginMonthColName             = "begin_month"
	ItemTableEndMonthColName               = "end_month"
	ItemTableBeginModelYearColName         = "begin_model_year"
	ItemTableEndModelYearColName           = "end_model_year"
	ItemTableBeginModelYearFractionColName = "begin_model_year_fraction"
	ItemTableEndModelYearFractionColName   = "end_model_year_fraction"
	ItemTableTodayColName                  = "today"
	ItemTableBodyColName                   = "body"
	ItemTableIsGroupColName                = "is_group"
	ItemTableProducedExactlyColName        = "produced_exactly"
	ItemTableAddDatetimeColName            = "add_datetime"
	ItemTableBeginOrderCacheColName        = "begin_order_cache"
	ItemTableEndOrderCacheColName          = "end_order_cache"
	ItemTableVehicleTypeInheritColName     = "vehicle_type_inherit"
	ItemTableSpecInheritColName            = "spec_inherit"
	ItemTableProducedColName               = "produced"

	ItemNameMinLength     = 2
	ItemNameMaxLength     = 150
	ItemFullNameMaxLength = 255
	ItemCatnameMinLength  = 3
	ItemCatnameMaxLength  = 100
	ItemBodyMinLength     = 0
	ItemBodyMaxLength     = 20
	ItemYearMin           = 1500
	ItemYearMax           = 2100
)

var ( //nolint: dupl
	ItemTable                          = goqu.T(ItemTableName)
	ItemTableIDCol                     = ItemTable.Col(ItemTableIDColName)
	ItemTableBodyCol                   = ItemTable.Col(ItemTableBodyColName)
	ItemTableSpecIDCol                 = ItemTable.Col(ItemTableSpecIDColName)
	ItemTableCatnameCol                = ItemTable.Col(ItemTableCatnameColName)
	ItemTableNameCol                   = ItemTable.Col(ItemTableNameColName)
	ItemTableBeginYearCol              = ItemTable.Col(ItemTableBeginYearColName)
	ItemTableEndYearCol                = ItemTable.Col(ItemTableEndYearColName)
	ItemTableBeginMonthCol             = ItemTable.Col(ItemTableBeginMonthColName)
	ItemTableEndMonthCol               = ItemTable.Col(ItemTableEndMonthColName)
	ItemTableBeginModelYearCol         = ItemTable.Col(ItemTableBeginModelYearColName)
	ItemTableBeginModelYearFractionCol = ItemTable.Col(ItemTableBeginModelYearFractionColName)
	ItemTableEndModelYearCol           = ItemTable.Col(ItemTableEndModelYearColName)
	ItemTableEndModelYearFractionCol   = ItemTable.Col(ItemTableEndModelYearFractionColName)
	ItemTableIsGroupCol                = ItemTable.Col(ItemTableIsGroupColName)
	ItemTableItemTypeIDCol             = ItemTable.Col(ItemTableItemTypeIDColName)
	ItemTableTodayCol                  = ItemTable.Col(ItemTableTodayColName)
	ItemTableEngineItemIDCol           = ItemTable.Col(ItemTableEngineItemIDColName)
	ItemTableEngineInheritCol          = ItemTable.Col(ItemTableEngineInheritColName)
	ItemTableIsConceptInheritCol       = ItemTable.Col(ItemTableIsConceptInheritColName)
	ItemTableIsConceptCol              = ItemTable.Col(ItemTableIsConceptColName)
	ItemTableVehicleTypeInheritCol     = ItemTable.Col(ItemTableVehicleTypeInheritColName)
	ItemTableSpecInheritCol            = ItemTable.Col(ItemTableSpecInheritColName)
	ItemTableProducedCol               = ItemTable.Col(ItemTableProducedColName)
	ItemTableProducedExactlyCol        = ItemTable.Col(ItemTableProducedExactlyColName)
	ItemTableBeginOrderCacheCol        = ItemTable.Col(ItemTableBeginOrderCacheColName)
)

type ItemRow struct {
	ID                     int64               `db:"id"`
	Name                   string              `db:"name"`
	Catname                sql.NullString      `db:"catname"`
	ItemTypeID             ItemTableItemTypeID `db:"item_type_id"`
	Body                   string              `db:"body"`
	BeginYear              sql.NullInt32       `db:"begin_year"`
	EndYear                sql.NullInt32       `db:"end_year"`
	BeginModelYear         sql.NullInt32       `db:"begin_model_year"`
	EndModelYear           sql.NullInt32       `db:"end_model_year"`
	SpecID                 sql.NullInt32       `db:"spec_id"`
	LogoID                 sql.NullInt64       `db:"logo_id"`
	BeginMonth             sql.NullInt16       `db:"begin_month"`
	EndMonth               sql.NullInt16       `db:"end_month"`
	EngineItemID           sql.NullInt64       `db:"engine_item_id"`
	EngineInherit          bool                `db:"engine_inherit"`
	Today                  sql.NullBool        `db:"today"`
	IsConcept              bool                `db:"is_concept"`
	IsConceptInherit       bool                `db:"is_concept_inherit"`
	BeginModelYearFraction sql.NullString      `db:"begin_model_year_fraction"`
	EndModelYearFraction   sql.NullString      `db:"end_model_year_fraction"`
	Produced               sql.NullInt32       `db:"produced"`
	ProducedExactly        bool                `db:"produced_exactly"`
	IsGroup                bool                `db:"is_group"`
	VehicleTypeInherit     bool                `db:"vehicle_type_inherit"`
	SpecInherit            bool                `db:"spec_inherit"`
	AddDatetime            sql.NullTime        `db:"add_datetime"`
	FullName               sql.NullString      `db:"full_name"`
}

var AllowedTypeCombinations = map[ItemTableItemTypeID][]ItemTableItemTypeID{
	ItemTableItemTypeIDVehicle: {ItemTableItemTypeIDVehicle},
	ItemTableItemTypeIDEngine:  {ItemTableItemTypeIDEngine},
	ItemTableItemTypeIDCategory: {
		ItemTableItemTypeIDVehicle,
		ItemTableItemTypeIDCategory,
		ItemTableItemTypeIDBrand,
	},
	ItemTableItemTypeIDTwins: {ItemTableItemTypeIDVehicle},
	ItemTableItemTypeIDBrand: {
		ItemTableItemTypeIDBrand,
		ItemTableItemTypeIDVehicle,
		ItemTableItemTypeIDEngine,
	},
	ItemTableItemTypeIDFactory: {
		ItemTableItemTypeIDVehicle,
		ItemTableItemTypeIDEngine,
	},
	ItemTableItemTypeIDPerson:    {},
	ItemTableItemTypeIDCopyright: {},
	ItemTableItemTypeIDMuseum:    {},
}
