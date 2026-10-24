package items

import (
	"database/sql"
	"sort"

	"github.com/autowp/goautowp/schema"
	"golang.org/x/text/collate"
	"golang.org/x/text/language"
)

// scanItemRows retains the column handling of the existing List scanner.
func scanItemRows(rows *sql.Rows) ([]*Item, []int64, error) { //nolint:maintidx
	columnNames, err := rows.Columns()
	if err != nil {
		return nil, nil, err
	}

	var (
		result []*Item
		groups []int64
	)

	for rows.Next() {
		var (
			row               Item
			groupID, position int64
		)

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
			case "batch_group":
				pointers[i] = &groupID
			case "batch_position":
				pointers[i] = &position
			case schema.ItemTableIDColName:
				pointers[i] = &row.ID
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
		groups = append(groups, groupID)
	}

	if err = rows.Err(); err != nil {
		return nil, nil, err
	}

	return result, groups, nil
}

func sortItemsByName(result []*Item, lang string) {
	tag := language.English

	switch lang {
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

		switch lang {
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
