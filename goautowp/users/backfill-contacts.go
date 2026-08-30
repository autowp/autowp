package users

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/usercontacts"
	"github.com/doug-martin/goqu/v9"
	"github.com/sirupsen/logrus"
)

// BackfillContactsResult is the summary of a BackfillUserContactsFromURL run.
type BackfillContactsResult struct {
	Scanned   int
	Matched   int
	Inserted  int
	Unmatched int
}

// BackfillUserContactsFromURL scans the legacy users.url free-text field and, for every value
// that parses as a known platform profile link, adds a user_contact row. users.url itself is
// left untouched (a user may still want it shown as their website), and contacts_public is left
// at its default so nothing that was public becomes public without the owner's action.
//
// Run once by an operator after reviewing a --dry-run; it is idempotent (ON CONFLICT DO NOTHING)
// and deliberately not part of migrate-postgres.
func (s *Repository) BackfillUserContactsFromURL(
	ctx context.Context, dryRun bool,
) (BackfillContactsResult, error) {
	var res BackfillContactsResult

	var rows []struct {
		ID  int64  `db:"id"`
		URL string `db:"url"`
	}

	err := s.db.Select(schema.UserTableIDCol, schema.UserTableURLCol).
		From(schema.UserTable).
		Where(schema.UserTableURLCol.Neq(""), schema.UserTableDeletedCol.IsFalse()).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return res, err
	}

	for _, row := range rows {
		res.Scanned++

		platform, username, ok := usercontacts.Detect(row.URL)
		if !ok {
			res.Unmatched++

			continue
		}

		res.Matched++

		logrus.Infof(
			"user %d: url %q -> platform %d, username %q%s",
			row.ID, row.URL, platform, username, dryRunSuffix(dryRun),
		)

		if dryRun {
			continue
		}

		result, err := s.db.Insert(schema.UserContactTable).
			Rows(goqu.Record{
				schema.UserContactTableUserIDColName:   row.ID,
				schema.UserContactTablePlatformColName: platform,
				schema.UserContactTableUsernameColName: username,
			}).
			OnConflict(goqu.DoNothing()).
			Executor().ExecContext(ctx)
		if err != nil {
			return res, err
		}

		if affected, _ := result.RowsAffected(); affected > 0 {
			res.Inserted++
		}
	}

	return res, nil
}

func dryRunSuffix(dryRun bool) string {
	if dryRun {
		return " (dry-run)"
	}

	return ""
}
