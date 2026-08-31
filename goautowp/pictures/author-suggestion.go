package pictures

import (
	"context"
	"regexp"
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const authorNameMinLen = 2

// authorNameNoiseRe matches copyright boilerplate commonly wrapped around a photographer's name
// in EXIF Artist/Copyright, so "© 2019 Jan Kowalski / All rights reserved" normalises to
// "Jan Kowalski".
var authorNameNoiseRe = regexp.MustCompile(
	`(?i)(©|\(c\)|\bcopyright\b|\ball rights reserved\b|\bphotos?\s+by\b|\b\d{4}\b)`,
)

// NormalizeAuthorName strips copyright boilerplate and collapses whitespace. It never tries to
// reorder "Lastname, Firstname" or expand initials — matching stays deliberately literal.
func NormalizeAuthorName(raw string) string {
	s := authorNameNoiseRe.ReplaceAllString(raw, " ")
	s = strings.Join(strings.Fields(s), " ")

	return strings.Trim(s, " .,;:-_/|")
}

func hasLetter(s string) bool {
	for _, r := range s {
		if unicode.IsLetter(r) {
			return true
		}
	}

	return false
}

// ResolveAuthorPersons returns catalogue person ids whose name matches the given EXIF author
// string case-insensitively and exactly, restricted to persons already credited as the author of
// at least one picture. Returns nil when the normalised name is too short/empty or nothing
// matches. Several matches (namesakes) are all returned.
func (s *Repository) ResolveAuthorPersons(ctx context.Context, rawName string) ([]int64, error) {
	name := NormalizeAuthorName(rawName)
	if utf8.RuneCountInString(name) < authorNameMinLen || !hasLetter(name) {
		return nil, nil
	}

	lowerName := strings.ToLower(name)

	var ids []int64

	err := s.db.Select(schema.ItemTableIDCol).
		Distinct().
		From(schema.ItemTable).
		Where(
			schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDPerson),
			goqu.Or(
				goqu.L("LOWER(?) = ?", schema.ItemTableNameCol, lowerName),
				goqu.L("EXISTS ?",
					s.db.From(schema.ItemLanguageTable).
						Select(goqu.L("1")).
						Where(
							schema.ItemLanguageTableItemIDCol.Eq(schema.ItemTableIDCol),
							goqu.L("LOWER(?) = ?", schema.ItemLanguageTableNameCol, lowerName),
						),
				),
			),
			goqu.L("EXISTS ?",
				s.db.From(schema.PictureItemTable).
					Select(goqu.L("1")).
					Where(
						schema.PictureItemTableItemIDCol.Eq(schema.ItemTableIDCol),
						schema.PictureItemTableTypeCol.Eq(schema.PictureItemTypeAuthor),
					),
			),
		).
		Order(schema.ItemTableIDCol.Asc()).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return nil, err
	}

	return ids, nil
}

// SetPictureAuthorSuggestions stores advisory author candidates for a picture. Idempotent: a
// candidate already stored for the picture is left untouched.
func (s *Repository) SetPictureAuthorSuggestions(
	ctx context.Context, pictureID int64, rows []schema.PictureAuthorSuggestionRow,
) error {
	if len(rows) == 0 {
		return nil
	}

	records := make([]any, 0, len(rows))
	for _, row := range rows {
		records = append(records, goqu.Record{
			schema.PictureAuthorSuggestionTablePictureIDColName: pictureID,
			schema.PictureAuthorSuggestionTableItemIDColName:    row.ItemID,
			schema.PictureAuthorSuggestionTableSourceColName:    row.Source,
			schema.PictureAuthorSuggestionTableRawValueColName:  row.RawValue,
		})
	}

	_, err := s.db.Insert(schema.PictureAuthorSuggestionTable).
		Rows(records...).
		OnConflict(goqu.DoNothing()).
		Executor().ExecContext(ctx)

	return err
}

// PictureAuthorSuggestions returns the stored author candidates for a picture, oldest item id
// first.
func (s *Repository) PictureAuthorSuggestions(
	ctx context.Context, pictureID int64,
) ([]schema.PictureAuthorSuggestionRow, error) {
	var rows []schema.PictureAuthorSuggestionRow

	err := s.db.Select(
		schema.PictureAuthorSuggestionTablePictureIDCol,
		schema.PictureAuthorSuggestionTableItemIDCol,
		schema.PictureAuthorSuggestionTableSourceCol,
		schema.PictureAuthorSuggestionTableRawValueCol,
	).
		From(schema.PictureAuthorSuggestionTable).
		Where(schema.PictureAuthorSuggestionTablePictureIDCol.Eq(pictureID)).
		Order(schema.PictureAuthorSuggestionTableItemIDCol.Asc()).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

// PictureOwnerAndStatus returns the owner id and status of a picture. found is false when no such
// picture exists.
func (s *Repository) PictureOwnerAndStatus(
	ctx context.Context, pictureID int64,
) (int64, schema.PictureStatus, bool, error) {
	var row schema.PictureRow

	found, err := s.db.Select(schema.PictureTableOwnerIDCol, schema.PictureTableStatusCol).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanStructContext(ctx, &row)
	if err != nil || !found {
		return 0, schema.PictureStatusUnknown, found, err
	}

	return row.OwnerID.Int64, row.Status, true, nil
}
