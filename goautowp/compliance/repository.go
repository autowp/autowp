// Package compliance holds the internal, staff-only "suppression list" of people who objected to
// or requested erasure of their name being used as a public photo-author credit (GDPR Art.
// 17/21). Entries here are never exposed through public endpoints - they exist only so a name
// removed once does not silently reappear, either through EXIF auto-matching, a moderator
// manually recreating a catalogue person, or the EXIF copyrights free-text block.
package compliance

import (
	"context"
	"regexp"
	"sort"
	"strings"
	"unicode/utf8"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

const (
	objectionsPerPage = 30
	minNameLen        = 2
	// maxPermutedTokens caps how many words CanonicalizeName/permutations will reorder over - a
	// real person's name is almost always 2-3 words (given-name/family-name order varies by
	// culture and EXIF convention), and permutation count grows factorially, so anything longer
	// (more likely a mis-entered phrase than a name) is left in its original word order.
	maxPermutedTokens = 4
)

// nameNoiseRe strips copyright boilerplate commonly wrapped around a name in EXIF Artist/
// Copyright text, mirroring pictures.NormalizeAuthorName (duplicated here rather than imported to
// avoid a compliance<->pictures import cycle, since pictures.Repository calls into compliance).
var nameNoiseRe = regexp.MustCompile(
	`(?i)(©|\(c\)|\bcopyright\b|\ball rights reserved\b|\bphotos?\s+by\b|\b\d{4}\b)`,
)

// NormalizeName strips copyright boilerplate, collapses whitespace and lower-cases, so the same
// name compares equal regardless of surrounding EXIF noise or case.
func NormalizeName(raw string) string {
	s := nameNoiseRe.ReplaceAllString(raw, " ")
	s = strings.Join(strings.Fields(s), " ")
	s = strings.Trim(s, " .,;:-_/|")

	return strings.ToLower(s)
}

// CanonicalizeName normalizes name and then sorts its words, so "Olaf Itrich" and "Itrich Olaf"
// (given-name/family-name order swapped - common between EXIF conventions, cultures, and
// hand-typed catalogue entries) compare equal. Capped at maxPermutedTokens words.
func CanonicalizeName(raw string) string {
	tokens := strings.Fields(NormalizeName(raw))
	if len(tokens) > maxPermutedTokens {
		return NormalizeName(raw)
	}

	sorted := make([]string, len(tokens))
	copy(sorted, tokens)
	sort.Strings(sorted)

	return strings.Join(sorted, " ")
}

// NamePermutations returns every word-order permutation of name's normalized form (including
// itself), for substring-searching freeform text where the words may appear in any order. Capped
// at maxPermutedTokens words to keep the factorial blow-up bounded; beyond that only the original
// order is returned. Exported so callers outside the package (e.g. items-grpc.go's SuppressAuthor)
// can expand a name into every word-order variant before searching EXIF/IPTC/XMP or freeform
// copyrights text.
func NamePermutations(name string) []string {
	tokens := strings.Fields(NormalizeName(name))
	if len(tokens) == 0 {
		return nil
	}

	if len(tokens) > maxPermutedTokens {
		return []string{strings.Join(tokens, " ")}
	}

	var (
		results []string
		permute func(remaining []string, acc []string)
	)

	permute = func(remaining []string, acc []string) {
		if len(remaining) == 0 {
			results = append(results, strings.Join(acc, " "))

			return
		}

		for i, token := range remaining {
			next := make([]string, 0, len(remaining)-1)
			next = append(next, remaining[:i]...)
			next = append(next, remaining[i+1:]...)
			permute(next, append(acc, token))
		}
	}

	permute(tokens, make([]string, 0, len(tokens)))

	return results
}

// Repository stores and checks the GDPR-objection suppression list.
type Repository struct {
	db                    *goqu.Database
	textStorageRepository *textstorage.Repository
}

// NewRepository constructor.
func NewRepository(db *goqu.Database, textStorageRepository *textstorage.Repository) *Repository {
	return &Repository{db: db, textStorageRepository: textStorageRepository}
}

// CreateOptions is a new suppression-list case. Names holds every known spelling (a person is
// often catalogued under several localized name variants - see items.Repository.ItemLanguageList
// - and all of them need to resolve to this same case); at least one is required. SourceText,
// when non-empty, is the original request correspondence (e.g. the erasure/objection email) and
// is stored separately in textstorage - see the source_text_id comment in migration 53.
// AuthorUserID attributes who stored it, for the textstorage revision history.
type CreateOptions struct {
	Names        []string
	Reference    string
	ContactEmail string
	Note         string
	SourceText   string
	AuthorUserID int64
}

// Create records a new case with every one of opts.Names as a recognised spelling. Empty names
// are skipped; if none remain, nothing is created and id is 0.
func (s *Repository) Create(ctx context.Context, opts CreateOptions) (int64, error) {
	names := make([]string, 0, len(opts.Names))

	for _, name := range opts.Names {
		if strings.TrimSpace(name) != "" {
			names = append(names, name)
		}
	}

	if len(names) == 0 {
		return 0, nil
	}

	record := goqu.Record{
		schema.GdprObjectionTableReferenceColName:    opts.Reference,
		schema.GdprObjectionTableContactEmailColName: opts.ContactEmail,
		schema.GdprObjectionTableNoteColName:         opts.Note,
	}

	if opts.SourceText != "" {
		textID, err := s.textStorageRepository.CreateText(ctx, opts.SourceText, opts.AuthorUserID)
		if err != nil {
			return 0, err
		}

		record[schema.GdprObjectionTableSourceTextIDColName] = textID
	}

	var id int64

	_, err := s.db.Insert(schema.GdprObjectionTable).
		Rows(record).
		Returning(schema.GdprObjectionTableIDCol).
		Executor().ScanValContext(ctx, &id)
	if err != nil {
		return 0, err
	}

	nameRecords := make([]any, 0, len(names))
	for _, name := range names {
		nameRecords = append(nameRecords, goqu.Record{
			schema.GdprObjectionNameTableObjectionIDColName:    id,
			schema.GdprObjectionNameTableNameColName:           name,
			schema.GdprObjectionNameTableNormalizedNameColName: NormalizeName(name),
			schema.GdprObjectionNameTableCanonicalNameColName:  CanonicalizeName(name),
		})
	}

	if _, err = s.db.Insert(schema.GdprObjectionNameTable).
		Rows(nameRecords...).
		Executor().
		ExecContext(ctx); err != nil {
		return 0, err
	}

	return id, nil
}

// Names returns every known spelling for a case, in the order they were recorded.
func (s *Repository) Names(ctx context.Context, objectionID int64) ([]string, error) {
	var names []string

	err := s.db.Select(schema.GdprObjectionNameTableNameCol).
		From(schema.GdprObjectionNameTable).
		Where(schema.GdprObjectionNameTableObjectionIDCol.Eq(objectionID)).
		Order(schema.GdprObjectionNameTableIDCol.Asc()).
		ScanValsContext(ctx, &names)

	return names, err
}

// SourceText returns the original request correspondence attached to an objection, if any.
func (s *Repository) SourceText(ctx context.Context, row schema.GdprObjectionRow) (string, error) {
	if !row.SourceTextID.Valid {
		return "", nil
	}

	return s.textStorageRepository.Text(ctx, row.SourceTextID.Int32)
}

// Delete removes a case, cascading to its recorded name spellings. ok is false if it did not
// exist.
func (s *Repository) Delete(ctx context.Context, id int64) (bool, error) {
	res, err := s.db.Delete(schema.GdprObjectionTable).
		Where(schema.GdprObjectionTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, nil
}

// ListOptions paginates the suppression list. HitsOnly restricts it to entries that were matched
// again since last acknowledged (hit_count > 0) - the moderator-menu badge counter's source.
type ListOptions struct {
	Page     int32
	HitsOnly bool
}

// List returns a page of cases, newest first.
func (s *Repository) List(ctx context.Context, opts ListOptions) ([]schema.GdprObjectionRow, *util.Pages, error) {
	sqSelect := s.db.From(schema.GdprObjectionTable).
		Order(schema.GdprObjectionTableCreatedAtCol.Desc(), schema.GdprObjectionTableIDCol.Desc())

	if opts.HitsOnly {
		sqSelect = sqSelect.Where(schema.GdprObjectionTableHitCountCol.Gt(0))
	}

	page := opts.Page
	if page < 1 {
		page = 1
	}

	paginator := util.Paginator{
		SQLSelect:         sqSelect,
		ItemCountPerPage:  objectionsPerPage,
		CurrentPageNumber: page,
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	pageSelect, err := paginator.GetItemsByPage(ctx, page)
	if err != nil {
		return nil, nil, err
	}

	rows := make([]schema.GdprObjectionRow, 0)
	if err = pageSelect.ScanStructsContext(ctx, &rows); err != nil {
		return nil, nil, err
	}

	return rows, pages, nil
}

// Get returns a single case by id.
func (s *Repository) Get(ctx context.Context, id int64) (schema.GdprObjectionRow, bool, error) {
	var row schema.GdprObjectionRow

	found, err := s.db.From(schema.GdprObjectionTable).
		Where(schema.GdprObjectionTableIDCol.Eq(id)).
		ScanStructContext(ctx, &row)

	return row, found, err
}

// FindExact matches name against every recorded spelling of every case, regardless of word order
// - "Olaf Itrich" and "Itrich Olaf" both hit the same case (see CanonicalizeName), since
// given-name/family-name order is not consistent across EXIF conventions, cultures, or hand-typed
// catalogue entries. Used where the candidate is already a bare name (a person item's display
// name), not freeform text - see FindInText for that.
func (s *Repository) FindExact(ctx context.Context, name string) (schema.GdprObjectionRow, bool, error) {
	canonical := CanonicalizeName(name)
	if utf8.RuneCountInString(canonical) < minNameLen {
		return schema.GdprObjectionRow{}, false, nil
	}

	var row schema.GdprObjectionRow

	// Explicit Select(gdpr_objection.*): without it, goqu's struct-scan builds an unqualified
	// column list from GdprObjectionRow's db tags (including a bare "id"), which Postgres then
	// rejects as ambiguous - both joined tables have their own "id" column, even though only
	// gdpr_objection's is ever destined for the struct.
	found, err := s.db.From(schema.GdprObjectionTable).
		Select(schema.GdprObjectionTable.All()).
		Join(
			schema.GdprObjectionNameTable,
			goqu.On(schema.GdprObjectionTableIDCol.Eq(schema.GdprObjectionNameTableObjectionIDCol)),
		).
		Where(schema.GdprObjectionNameTableCanonicalNameCol.Eq(canonical)).
		Order(schema.GdprObjectionTableIDCol.Asc()).
		Limit(1).
		ScanStructContext(ctx, &row)

	return row, found, err
}

// FindInText looks up whether any case's recorded spelling - in any word order (see
// NamePermutations) - occurs inside the normalized form of text (a substring/phrase check). Used
// for freeform fields such as the EXIF copyrights block, where the objectionable name may be
// wrapped in unrelated text. The tables are expected to stay small (GDPR objections are rare), so
// a full scan per upload is not a concern.
func (s *Repository) FindInText(ctx context.Context, text string) (schema.GdprObjectionRow, bool, error) {
	normalizedText := NormalizeName(text)
	if normalizedText == "" {
		return schema.GdprObjectionRow{}, false, nil
	}

	var nameRows []schema.GdprObjectionNameRow

	err := s.db.From(schema.GdprObjectionNameTable).
		Order(schema.GdprObjectionNameTableObjectionIDCol.Asc()).
		ScanStructsContext(ctx, &nameRows)
	if err != nil {
		return schema.GdprObjectionRow{}, false, err
	}

	for _, nameRow := range nameRows {
		if nameRow.NormalizedName == "" {
			continue
		}

		matched := false

		for _, permutation := range NamePermutations(nameRow.NormalizedName) {
			if strings.Contains(normalizedText, permutation) {
				matched = true

				break
			}
		}

		if !matched {
			continue
		}

		row, found, getErr := s.Get(ctx, nameRow.ObjectionID)
		if getErr != nil {
			return schema.GdprObjectionRow{}, false, getErr
		}

		if found {
			return row, true, nil
		}
	}

	return schema.GdprObjectionRow{}, false, nil
}

// RecordHit bumps the hit counter and last-hit timestamp - a name that was blocked was actually
// encountered again, worth surfacing to whoever reviews the suppression list.
func (s *Repository) RecordHit(ctx context.Context, id int64) error {
	_, err := s.db.Update(schema.GdprObjectionTable).
		Set(goqu.Record{
			schema.GdprObjectionTableHitCountColName:  goqu.L("? + 1", schema.GdprObjectionTableHitCountCol),
			schema.GdprObjectionTableLastHitAtColName: goqu.Func("NOW"),
		}).
		Where(schema.GdprObjectionTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

// Acknowledge clears the hit counter after a moderator has reviewed a match - it stops
// contributing to the moderator-menu badge count until the name is encountered again.
// last_hit_at is left as-is (historical record of when it last happened).
func (s *Repository) Acknowledge(ctx context.Context, id int64) error {
	_, err := s.db.Update(schema.GdprObjectionTable).
		Set(goqu.Record{schema.GdprObjectionTableHitCountColName: 0}).
		Where(schema.GdprObjectionTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

// AddCleanupCandidates records content found (by a site-wide text search) to mention a case's
// name but not something SuppressAuthor can safely touch automatically - a picture's freeform
// copyrights text, or a visitor comment. Idempotent: re-adding an already-recorded (objectionID,
// entityType, entityID) is a no-op, so re-running the search later does not duplicate rows.
func (s *Repository) AddCleanupCandidates(
	ctx context.Context,
	objectionID int64,
	entityType schema.GdprObjectionCleanupCandidateEntityType,
	entityIDs []int64,
) error {
	if len(entityIDs) == 0 {
		return nil
	}

	records := make([]any, 0, len(entityIDs))
	for _, entityID := range entityIDs {
		records = append(records, goqu.Record{
			schema.GdprObjectionCleanupCandidateTableObjectionIDColName: objectionID,
			schema.GdprObjectionCleanupCandidateTableEntityTypeColName:  entityType,
			schema.GdprObjectionCleanupCandidateTableEntityIDColName:    entityID,
		})
	}

	_, err := s.db.Insert(schema.GdprObjectionCleanupCandidateTable).
		Rows(records...).
		OnConflict(goqu.DoNothing()).
		Executor().ExecContext(ctx)

	return err
}

// CleanupCandidates returns every cleanup candidate recorded for a case, oldest first.
func (s *Repository) CleanupCandidates(
	ctx context.Context, objectionID int64,
) ([]schema.GdprObjectionCleanupCandidateRow, error) {
	var rows []schema.GdprObjectionCleanupCandidateRow

	err := s.db.From(schema.GdprObjectionCleanupCandidateTable).
		Where(schema.GdprObjectionCleanupCandidateTableObjectionIDCol.Eq(objectionID)).
		Order(schema.GdprObjectionCleanupCandidateTableIDCol.Asc()).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

// ResolveCleanupCandidate marks a candidate as reviewed (the moderator decided what, if anything,
// to do with the underlying picture/comment - this only stops it showing as pending). ok is false
// if it did not exist.
func (s *Repository) ResolveCleanupCandidate(ctx context.Context, id int64, moderatorID int64) (bool, error) {
	res, err := s.db.Update(schema.GdprObjectionCleanupCandidateTable).
		Set(goqu.Record{
			schema.GdprObjectionCleanupCandidateTableResolvedAtColName: goqu.Func("NOW"),
			schema.GdprObjectionCleanupCandidateTableResolvedByColName: moderatorID,
		}).
		Where(schema.GdprObjectionCleanupCandidateTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	return affected > 0, nil
}
