package pictures

import (
	"database/sql"
	"strconv"
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func createPerson(t *testing.T, db *goqu.Database, name string) int64 {
	t.Helper()

	var id int64

	success, err := db.Insert(schema.ItemTable).Rows(goqu.Record{
		schema.ItemTableNameColName:       name,
		schema.ItemTableItemTypeIDColName: schema.ItemTableItemTypeIDPerson,
		"body":                            "",
		"produced_exactly":                false,
	}).Returning(schema.ItemTableIDCol).Executor().ScanValContext(t.Context(), &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}

func linkPictureAuthor(t *testing.T, db *goqu.Database, pictureID, itemID int64) {
	t.Helper()

	_, err := db.Insert(schema.PictureItemTable).Rows(goqu.Record{
		schema.PictureItemTablePictureIDColName: pictureID,
		schema.PictureItemTableItemIDColName:    itemID,
		schema.PictureItemTableTypeColName:      schema.PictureItemTypeAuthor,
	}).OnConflict(goqu.DoNothing()).Executor().ExecContext(t.Context())
	require.NoError(t, err)
}

func TestNormalizeAuthorName(t *testing.T) {
	t.Parallel()

	cases := []struct {
		input string
		want  string
	}{
		{"Jan Kowalski", "Jan Kowalski"},
		{"  Jan   Kowalski  ", "Jan Kowalski"},
		{"© 2019 Jan Kowalski", "Jan Kowalski"},
		{"Copyright Jan Kowalski, all rights reserved", "Jan Kowalski"},
		{"(c) Jan Kowalski 2021", "Jan Kowalski"},
		{"Photo by Jan Kowalski", "Jan Kowalski"},
		{"Jan Kowalski\n", "Jan Kowalski"},
		{"2019", ""},
		{"©", ""},
	}

	for _, c := range cases {
		require.Equalf(t, c.want, NormalizeAuthorName(c.input), "input %q", c.input)
	}
}

func TestResolveAuthorPersons(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	suffix := strconv.Itoa(int(createRandomUser(t, db))) + "x"
	authorName := "Ansel Adams " + suffix

	// A person credited as author of at least one picture is resolvable.
	authorID := createPerson(t, db, authorName)
	picID := createTestPicture(t, db, sql.NullInt64{})
	linkPictureAuthor(t, db, picID, authorID)

	// A person that only appears as a subject (no author picture-item) is not.
	createPerson(t, db, "Not An Author "+suffix)

	ids, err := repo.ResolveAuthorPersons(ctx, authorName)
	require.NoError(t, err)
	require.Equal(t, []int64{authorID}, ids)

	// Case-insensitive and copyright boilerplate is stripped.
	ids, err = repo.ResolveAuthorPersons(ctx, "© 2020 "+authorName)
	require.NoError(t, err)
	require.Equal(t, []int64{authorID}, ids)

	ids, err = repo.ResolveAuthorPersons(ctx, "Not An Author "+suffix)
	require.NoError(t, err)
	require.Empty(t, ids)

	// Namesakes: every matching author is returned.
	twinName := "Twin Author " + suffix
	twinA := createPerson(t, db, twinName)
	twinB := createPerson(t, db, twinName)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), twinA)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), twinB)

	ids, err = repo.ResolveAuthorPersons(ctx, twinName)
	require.NoError(t, err)
	require.ElementsMatch(t, []int64{twinA, twinB}, ids)
}

func hasAuthorPictureItem(t *testing.T, db *goqu.Database, pictureID, itemID int64) bool {
	t.Helper()

	found, err := db.From(schema.PictureItemTable).
		Select(goqu.L("1")).
		Where(
			schema.PictureItemTablePictureIDCol.Eq(pictureID),
			schema.PictureItemTableItemIDCol.Eq(itemID),
			schema.PictureItemTableTypeCol.Eq(schema.PictureItemTypeAuthor),
		).
		ScanValContext(t.Context(), new(int64))
	require.NoError(t, err)

	return found
}

func TestProcessEXIFAuthorSingleMatchAutoLinks(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	suffix := strconv.Itoa(int(createRandomUser(t, db))) + "x"
	name := "Diane Arbus " + suffix

	personID := createPerson(t, db, name)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), personID)

	picID := createTestPicture(t, db, sql.NullInt64{})

	err := repo.processEXIFAuthor(ctx, picID, exifExtractedValues{artist: "© 2020 " + name}, false)
	require.NoError(t, err)

	suggestions, err := repo.PictureAuthorSuggestions(ctx, picID)
	require.NoError(t, err)
	require.Len(t, suggestions, 1)
	require.Equal(t, personID, suggestions[0].ItemID)
	require.Equal(t, schema.PictureAuthorSuggestionSourceEXIFArtist, suggestions[0].Source)
	require.Equal(t, name, suggestions[0].RawValue)

	require.True(t, hasAuthorPictureItem(t, db, picID, personID))
}

func TestProcessEXIFAuthorRespectsFormAuthor(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	suffix := strconv.Itoa(int(createRandomUser(t, db))) + "x"
	name := "Robert Capa " + suffix

	personID := createPerson(t, db, name)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), personID)

	picID := createTestPicture(t, db, sql.NullInt64{})

	// authorLinked=true: the uploader picked an author in the form, so the single EXIF match is
	// stored as a suggestion but not auto-linked.
	err := repo.processEXIFAuthor(ctx, picID, exifExtractedValues{artist: name}, true)
	require.NoError(t, err)

	suggestions, err := repo.PictureAuthorSuggestions(ctx, picID)
	require.NoError(t, err)
	require.Len(t, suggestions, 1)
	require.False(t, hasAuthorPictureItem(t, db, picID, personID))
}

func TestProcessEXIFAuthorNamesakesNoAutoLink(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	suffix := strconv.Itoa(int(createRandomUser(t, db))) + "x"
	name := "Ambiguous Shooter " + suffix

	twinA := createPerson(t, db, name)
	twinB := createPerson(t, db, name)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), twinA)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), twinB)

	picID := createTestPicture(t, db, sql.NullInt64{})

	err := repo.processEXIFAuthor(ctx, picID, exifExtractedValues{artist: name}, false)
	require.NoError(t, err)

	suggestions, err := repo.PictureAuthorSuggestions(ctx, picID)
	require.NoError(t, err)
	require.Len(t, suggestions, 2)
	require.False(t, hasAuthorPictureItem(t, db, picID, twinA))
	require.False(t, hasAuthorPictureItem(t, db, picID, twinB))
}

func TestProcessEXIFAuthorFallsBackToCopyright(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	suffix := strconv.Itoa(int(createRandomUser(t, db))) + "x"
	name := "Vivian Maier " + suffix

	personID := createPerson(t, db, name)
	linkPictureAuthor(t, db, createTestPicture(t, db, sql.NullInt64{}), personID)

	picID := createTestPicture(t, db, sql.NullInt64{})

	err := repo.processEXIFAuthor(
		ctx, picID, exifExtractedValues{artist: "", copyrights: name}, false,
	)
	require.NoError(t, err)

	suggestions, err := repo.PictureAuthorSuggestions(ctx, picID)
	require.NoError(t, err)
	require.Len(t, suggestions, 1)
	require.Equal(t, schema.PictureAuthorSuggestionSourceEXIFCopyright, suggestions[0].Source)
}
