package users

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/usercontacts"
	"github.com/doug-martin/goqu/v9"
	"github.com/sirupsen/logrus"
)

// BackfillContactsResult is the summary of a BackfillUserContacts run.
type BackfillContactsResult struct {
	Scanned   int
	Matched   int
	Inserted  int
	Unmatched int
}

// linkedAccountServiceSkip lists user_account.service_id values whose link is never a plain
// external profile URL we want (Facebook and Google+ are excluded by policy; keycloak is the
// SSO account, not a profile).
var linkedAccountServiceSkip = map[string]bool{
	"facebook":                true,
	"google-plus":             true,
	KeycloakExternalAccountID: true,
}

// backfillCandidate is one (user, url) pair to try attributing to a platform.
type backfillCandidate struct {
	UserID int64  `db:"user_id"`
	Link   string `db:"link"`
}

// BackfillUserContacts scans two legacy sources of external-profile URLs — the free-text
// users.url field and user_account.link (OAuth-linked accounts, e.g. VK) — and adds a
// user_contact row for every value that parses as a known platform profile link.
//
// The source rows are left untouched, and contacts_public is left at its default, so nothing
// that was public becomes public without the owner's action. Idempotent (ON CONFLICT DO
// NOTHING); run once by an operator after reviewing a --dry-run, not part of migrate-postgres.
func (s *Repository) BackfillUserContacts(ctx context.Context, dryRun bool) (BackfillContactsResult, error) {
	var res BackfillContactsResult

	fromURL, err := s.backfillCandidatesFromURL(ctx)
	if err != nil {
		return res, err
	}

	if err = s.backfillContacts(ctx, dryRun, "users.url", fromURL, &res); err != nil {
		return res, err
	}

	fromAccounts, err := s.backfillCandidatesFromAccounts(ctx)
	if err != nil {
		return res, err
	}

	if err = s.backfillContacts(ctx, dryRun, "user_account.link", fromAccounts, &res); err != nil {
		return res, err
	}

	return res, nil
}

func (s *Repository) backfillCandidatesFromURL(ctx context.Context) ([]backfillCandidate, error) {
	var rows []backfillCandidate

	err := s.db.Select(schema.UserTableIDCol.As("user_id"), schema.UserTableURLCol.As("link")).
		From(schema.UserTable).
		Where(schema.UserTableURLCol.Neq(""), schema.UserTableDeletedCol.IsFalse()).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) backfillCandidatesFromAccounts(ctx context.Context) ([]backfillCandidate, error) {
	skip := make([]string, 0, len(linkedAccountServiceSkip))
	for service := range linkedAccountServiceSkip {
		skip = append(skip, service)
	}

	var rows []backfillCandidate

	err := s.db.Select(schema.UserAccountTableUserIDCol, schema.UserAccountTableLinkCol).
		From(schema.UserAccountTable).
		Where(
			schema.UserAccountTableLinkCol.Neq(""),
			schema.UserAccountTableServiceIDCol.NotIn(skip),
		).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) backfillContacts(
	ctx context.Context, dryRun bool, source string, candidates []backfillCandidate,
	res *BackfillContactsResult,
) error {
	for _, candidate := range candidates {
		res.Scanned++

		platform, username, ok := usercontacts.Detect(candidate.Link)
		if !ok {
			res.Unmatched++

			continue
		}

		res.Matched++

		logrus.Infof(
			"user %d: %s %q -> platform %d, username %q%s",
			candidate.UserID, source, candidate.Link, platform, username, dryRunSuffix(dryRun),
		)

		if dryRun {
			continue
		}

		result, err := s.db.Insert(schema.UserContactTable).
			Rows(goqu.Record{
				schema.UserContactTableUserIDColName:   candidate.UserID,
				schema.UserContactTablePlatformColName: platform,
				schema.UserContactTableUsernameColName: username,
			}).
			OnConflict(goqu.DoNothing()).
			Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		if affected, _ := result.RowsAffected(); affected > 0 {
			res.Inserted++
		}
	}

	return nil
}

func dryRunSuffix(dryRun bool) string {
	if dryRun {
		return " (dry-run)"
	}

	return ""
}
