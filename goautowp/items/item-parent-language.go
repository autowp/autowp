package items

import (
	"context"
	"fmt"
	"regexp"
	"sort"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/dlclark/regexp2"
	"github.com/doug-martin/goqu/v9"
)

type ItemParentLanguageRepository struct {
	db               *goqu.Database
	contentLanguages []string
}

var extraSpacesRegexp = regexp.MustCompile("[[:space:]]+")

func yearsPrefix(begin int32, end int32) string {
	if begin <= 0 && end <= 0 {
		return ""
	}

	if end == begin {
		return strconv.Itoa(int(begin))
	}

	const oneHundred = 100

	var (
		bms = begin / oneHundred
		ems = end / oneHundred
	)

	if bms == ems {
		return fmt.Sprintf("%d–%02d", begin, end%oneHundred)
	}

	if begin <= 0 {
		return fmt.Sprintf("xx–%d", end)
	}

	if end > 0 {
		return fmt.Sprintf("%d–%d", begin, end)
	}

	return fmt.Sprintf("%d–xx", begin)
}

func NewItemParentLanguageRepository(
	db *goqu.Database,
	contentLanguages []string,
) *ItemParentLanguageRepository {
	return &ItemParentLanguageRepository{db: db, contentLanguages: contentLanguages}
}

func (s *ItemParentLanguageRepository) RefreshItemParentLanguage(
	ctx context.Context, parentItemTypeID schema.ItemTableItemTypeID, limit uint,
) error {
	logging.Infof("RefreshItemParentLanguage(%d, %d)", parentItemTypeID, limit)

	var res []struct {
		ItemID   int64 `db:"item_id"`
		ParentID int64 `db:"parent_id"`
	}

	sqSelect := s.db.Select(
		schema.ItemParentTableItemIDCol,
		schema.ItemParentTableParentIDCol,
	).
		From(schema.ItemParentTable).
		LeftJoin(schema.ItemParentLanguageTable, goqu.On(
			schema.ItemParentTableItemIDCol.Eq(schema.ItemParentLanguageTableItemIDCol),
			schema.ItemParentTableParentIDCol.Eq(schema.ItemParentLanguageTableParentIDCol),
		)).
		GroupBy(schema.ItemParentTableItemIDCol, schema.ItemParentTableParentIDCol).
		Having(goqu.COUNT(schema.ItemParentLanguageTableItemIDCol).Lt(len(s.contentLanguages))).
		Limit(limit)

	if parentItemTypeID > 0 {
		sqSelect = sqSelect.
			Join(
				schema.ItemTable,
				goqu.On(schema.ItemParentTableParentIDCol.Eq(schema.ItemTableIDCol)),
			).
			Where(schema.ItemTableItemTypeIDCol.Eq(parentItemTypeID))
	}

	err := sqSelect.ScanStructsContext(ctx, &res)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, row := range res {
		err = s.RefreshItemParentLanguage2(ctx, row.ParentID, row.ItemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *ItemParentLanguageRepository) SetItemParentLanguages(
	ctx context.Context,
	parentID, itemID int64,
	values map[string]schema.ItemParentLanguageRow,
	forceIsAuto bool,
) error {
	ctx = context.WithoutCancel(ctx)

	for _, lang := range s.contentLanguages {
		name := ""
		if _, ok := values[lang]; ok {
			name = values[lang].Name
		}

		err := s.SetItemParentLanguage(ctx, parentID, itemID, lang, name, forceIsAuto)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *ItemParentLanguageRepository) UpdateAutoNamesForParentsAndChilds(
	ctx context.Context,
	itemID int64,
) error {
	var ids []int64

	err := s.db.Select(schema.ItemParentTableItemIDCol).From(schema.ItemParentTable).Where(
		schema.ItemParentTableParentIDCol.Eq(itemID),
	).ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, id := range ids {
		err = s.updateAutoNames(ctx, itemID, id)
		if err != nil {
			return err
		}
	}

	err = s.db.Select(schema.ItemParentTableParentIDCol).From(schema.ItemParentTable).Where(
		schema.ItemParentTableItemIDCol.Eq(itemID),
	).ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, id := range ids {
		err = s.updateAutoNames(ctx, id, itemID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *ItemParentLanguageRepository) SetItemParentLanguage(
	ctx context.Context,
	parentID int64,
	itemID int64,
	lang string,
	newName string,
	forceIsAuto bool,
) error {
	var err error

	isAuto := true

	if !forceIsAuto {
		name := ""

		bvlRow := struct {
			IsAuto bool   `db:"is_auto"`
			Name   string `db:"name"`
		}{}

		success, err := s.db.Select(schema.ItemParentLanguageTableIsAutoCol, schema.ItemParentLanguageTableNameCol).
			From(schema.ItemParentLanguageTable).
			Where(
				schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
				schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
				schema.ItemParentLanguageTableLanguageCol.Eq(lang),
			).
			ScanStructContext(ctx, &bvlRow)
		if err != nil {
			return err
		}

		if success {
			isAuto = bvlRow.IsAuto
			name = bvlRow.Name
		}

		if name != newName {
			isAuto = false
		}
	}

	if len(newName) == 0 {
		newName, err = s.extractItemParentLanguageName(ctx, parentID, itemID, lang)
		if err != nil {
			return err
		}

		isAuto = true
	} else if len(newName) > schema.ItemLanguageNameMaxLength {
		newName = newName[:schema.ItemLanguageNameMaxLength]
	}

	_, err = s.db.Insert(schema.ItemParentLanguageTable).Rows(goqu.Record{
		schema.ItemParentLanguageTableItemIDColName:   itemID,
		schema.ItemParentLanguageTableParentIDColName: parentID,
		schema.ItemParentLanguageTableLanguageColName: lang,
		schema.ItemParentLanguageTableNameColName:     newName,
		schema.ItemParentLanguageTableIsAutoColName:   isAuto,
	}).OnConflict(
		goqu.DoUpdate(
			schema.ItemParentLanguageTableItemIDColName+","+schema.ItemParentLanguageTableParentIDColName+","+
				schema.ItemParentLanguageTableLanguageColName,
			goqu.Record{
				schema.ItemParentLanguageTableNameColName: schema.Excluded(schema.ItemParentLanguageTableNameColName),
				schema.ItemParentLanguageTableIsAutoColName: schema.Excluded(
					schema.ItemParentLanguageTableIsAutoColName,
				),
			},
		),
	).
		Executor().ExecContext(ctx)

	return err
}

func (s *ItemParentLanguageRepository) RefreshItemParentLanguage2(ctx context.Context, parentID, itemID int64) error {
	logging.Infof("RefreshItemParentLanguage2(%d, %d)", parentID, itemID)

	var rows []schema.ItemParentLanguageRow

	err := s.db.Select(
		schema.ItemParentLanguageTableIsAutoCol,
		schema.ItemParentLanguageTableNameCol,
		schema.ItemParentLanguageTableLanguageCol,
	).
		From(schema.ItemParentLanguageTable).
		Where(
			schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
			schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
		).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return err
	}

	values := make(map[string]schema.ItemParentLanguageRow)

	for _, iplRow := range rows {
		row := schema.ItemParentLanguageRow{}
		if !iplRow.IsAuto {
			row.Name = iplRow.Name
		}

		values[iplRow.Language] = row
	}

	return s.SetItemParentLanguages(ctx, parentID, itemID, values, false)
}

func (s *ItemParentLanguageRepository) AfterItemParentCreated(
	ctx context.Context,
	parentRow *Item,
	itemRow *Item,
) error {
	values := make(map[string]schema.ItemParentLanguageRow)

	for _, lang := range s.contentLanguages {
		name, err := s.ExtractName(ctx, parentRow.ItemRow, itemRow.ItemRow, lang)
		if err != nil {
			return err
		}

		values[lang] = schema.ItemParentLanguageRow{
			Name: name,
		}
	}

	return s.SetItemParentLanguages(ctx, parentRow.ID, itemRow.ID, values, true)
}

func (s *ItemParentLanguageRepository) ExtractName(
	ctx context.Context, parentRow schema.ItemRow, vehicleRow schema.ItemRow, lang string,
) (string, error) {
	langName, err := s.getName(ctx, vehicleRow.ID, lang)
	if err != nil {
		return "", err
	}

	vehicleName := langName
	if len(langName) == 0 {
		vehicleName = vehicleRow.Name
	}

	aliases, err := s.getAliases(ctx, parentRow.ID)
	if err != nil {
		return "", err
	}

	name := vehicleName

	for _, alias := range aliases {
		patterns := []string{
			"by The " + alias + " Company",
			"by " + alias,
			"di " + alias,
			"par " + alias,
			alias + "-",
			"-" + alias,
		}

		for _, pattern := range patterns {
			re := regexp2.MustCompile(regexp2.Escape(pattern), regexp2.IgnoreCase|regexp2.Unicode)

			name, err = re.Replace(name, "", -1, -1)
			if err != nil {
				return "", err
			}
		}

		re := regexp2.MustCompile(`\b`+regexp2.Escape(alias)+`\b`, regexp2.IgnoreCase|regexp2.Unicode)

		name, err = re.Replace(name, "", -1, -1)
		if err != nil {
			return "", err
		}
	}

	name = strings.TrimSpace(extraSpacesRegexp.ReplaceAllString(name, " "))

	name = strings.TrimLeft(name, "/")
	if len(name) == 0 && len(vehicleRow.Body) > 0 && vehicleRow.Body != parentRow.Body {
		name = vehicleRow.Body
	}

	vbmy := vehicleRow.BeginModelYear.Int32
	vemy := vehicleRow.EndModelYear.Int32

	if len(name) == 0 && vehicleRow.BeginModelYear.Valid && vbmy > 0 {
		modelYearsDifferent := vbmy != parentRow.BeginModelYear.Int32 ||
			vemy != parentRow.EndModelYear.Int32
		if modelYearsDifferent {
			name = yearsPrefix(vbmy, vemy)
		}
	}

	vby := vehicleRow.BeginYear.Int32
	vey := vehicleRow.EndYear.Int32

	if len(name) == 0 && vehicleRow.BeginYear.Valid && vby > 0 {
		yearsDifferent := vby != parentRow.BeginYear.Int32 || vey != parentRow.EndYear.Int32
		if yearsDifferent {
			name = yearsPrefix(vby, vey)
		}
	}

	if len(name) == 0 && vehicleRow.SpecID.Valid {
		specsDifferent := vehicleRow.SpecID.Int32 != parentRow.SpecID.Int32
		if specsDifferent {
			specShortName := ""

			success, err := s.db.Select(schema.SpecTableShortNameCol).From(schema.SpecTable).
				Where(schema.SpecTableIDCol.Eq(vehicleRow.SpecID.Int32)).
				ScanValContext(ctx, &specShortName)
			if err != nil {
				return "", err
			}

			if success {
				name = specShortName
			}
		}
	}

	if len(name) == 0 {
		name = vehicleName
	}

	return name, nil
}

func (s *ItemParentLanguageRepository) updateAutoNames(
	ctx context.Context,
	parentID int64,
	itemID int64,
) error {
	for _, lang := range s.contentLanguages {
		err := s.updateAutoName(ctx, parentID, itemID, lang)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *ItemParentLanguageRepository) updateAutoName(
	ctx context.Context,
	parentID int64,
	itemID int64,
	lang string,
) error {
	var isAuto bool

	success, err := s.db.Select(schema.ItemParentLanguageTableIsAutoCol).
		From(schema.ItemParentLanguageTable).
		Where(
			schema.ItemParentLanguageTableParentIDCol.Eq(parentID),
			schema.ItemParentLanguageTableItemIDCol.Eq(itemID),
			schema.ItemParentLanguageTableLanguageCol.Eq(lang),
		).
		ScanValContext(ctx, &isAuto)
	if err != nil {
		return err
	}

	if success && !isAuto {
		return nil
	}

	newName, err := s.extractItemParentLanguageName(ctx, parentID, itemID, lang)
	if err != nil {
		return err
	}

	logging.Infof("Update automatically item_parent_language.name to `%s` (%s)", newName, lang)

	_, err = s.db.Insert(schema.ItemParentLanguageTable).Rows(goqu.Record{
		schema.ItemParentLanguageTableItemIDColName:   itemID,
		schema.ItemParentLanguageTableParentIDColName: parentID,
		schema.ItemParentLanguageTableLanguageColName: lang,
		schema.ItemParentLanguageTableNameColName:     newName,
	}).OnConflict(
		goqu.DoUpdate(
			schema.ItemParentLanguageTableItemIDColName+","+schema.ItemParentLanguageTableParentIDColName+","+
				schema.ItemParentLanguageTableLanguageColName,
			goqu.Record{
				schema.ItemParentLanguageTableNameColName: schema.Excluded(schema.ItemParentLanguageTableNameColName),
			},
		),
	).
		Executor().ExecContext(ctx)

	return err
}

func (s *ItemParentLanguageRepository) extractItemParentLanguageName(
	ctx context.Context, parentID int64, itemID int64, lang string,
) (string, error) {
	var (
		parentRow schema.ItemRow
		itmRow    schema.ItemRow
	)

	success, err := s.db.Select(
		schema.ItemTableIDCol,
		schema.ItemTableNameCol,
		schema.ItemTableBodyCol,
		schema.ItemTableSpecIDCol,
		schema.ItemTableBeginYearCol,
		schema.ItemTableEndYearCol,
		schema.ItemTableBeginModelYearCol,
		schema.ItemTableEndModelYearCol,
	).
		From(schema.ItemTable).
		Where(schema.ItemTableIDCol.Eq(parentID)).
		ScanStructContext(ctx, &parentRow)
	if err != nil {
		return "", err
	}

	if !success {
		return "", ErrItemNotFound
	}

	success, err = s.db.Select(
		schema.ItemTableIDCol,
		schema.ItemTableNameCol,
		schema.ItemTableBodyCol,
		schema.ItemTableSpecIDCol,
		schema.ItemTableBeginYearCol,
		schema.ItemTableEndYearCol,
		schema.ItemTableBeginModelYearCol,
		schema.ItemTableEndModelYearCol,
	).
		From(schema.ItemTable).
		Where(schema.ItemTableIDCol.Eq(itemID)).
		ScanStructContext(ctx, &itmRow)
	if err != nil {
		return "", err
	}

	if !success {
		return "", ErrItemNotFound
	}

	newName, err := s.ExtractName(ctx, parentRow, itmRow, lang)
	if err != nil {
		return "", err
	}

	if len(newName) > schema.ItemLanguageNameMaxLength {
		newName = newName[:schema.ItemLanguageNameMaxLength]
	}

	return newName, nil
}

func (s *ItemParentLanguageRepository) getAliases(ctx context.Context, itemID int64) ([]string, error) {
	//nolint: prealloc
	var aliases []string

	err := s.db.Select(schema.BrandAliasTableNameCol).From(schema.BrandAliasTable).
		Where(schema.BrandAliasTableItemIDCol.Eq(itemID)).ScanValsContext(ctx, &aliases)
	if err != nil {
		return nil, err
	}

	langNames, err := s.getNames(ctx, itemID)
	if err != nil {
		return nil, err
	}

	aliases = append(aliases, langNames...)

	sort.Slice(aliases, func(i, j int) bool {
		return len(aliases[i]) > len(aliases[j])
	})

	return aliases, nil
}

func (s *ItemParentLanguageRepository) getNames(ctx context.Context, itemID int64) ([]string, error) {
	var result []string

	err := s.db.Select(schema.ItemLanguageTableNameCol).From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(itemID),
			goqu.L("? > 0", goqu.Func("length", schema.ItemLanguageTableNameCol)),
		).ScanValsContext(ctx, &result)

	return result, err
}

func (s *ItemParentLanguageRepository) getName(ctx context.Context, itemID int64, lang string) (string, error) {
	orderExpr, err := langPriorityOrderExpr(
		schema.ItemLanguageTableLanguageCol,
		lang,
	)
	if err != nil {
		return "", err
	}

	result := ""

	query := s.db.Select(schema.ItemLanguageTableNameCol).
		From(schema.ItemLanguageTable).
		Where(
			schema.ItemLanguageTableItemIDCol.Eq(itemID),
			goqu.L("? > 0", goqu.Func("length", schema.ItemLanguageTableNameCol)),
		).
		Order(orderExpr).
		Limit(1)

	success, err := query.ScanValContext(ctx, &result)
	if err != nil {
		return "", err
	}

	if !success {
		return "", nil
	}

	return result, nil
}
