## Context

23 achievements across 5 categories: one-shot (Pictures Contributor), relative/periodic
(Top Pictures Contributor, Veteran), and four 5-tier ladders (Picture Inspector, Picture
Buster, Spec Master, Commentator), each Bronze(100) → Silver(1,000) → Gold(10,000) →
Platinum(100,000) → Diamond(1,000,000) — the same ladder used by most ranked-game tier
systems. Full implementation plan lives at
`/home/dvp/.claude/plans/lazy-mixing-curry.md` for file-level detail; this document
captures only the decisions that need justification.

## Goals / Non-Goals

- **Goal**: grant achievements reliably, exactly once per threshold crossing, without
  adding meaningful latency to the write paths they hook into (picture accept/queue,
  spec edit, comment post).
- **Goal**: make per-user progress durable enough to power a future rating/leaderboard
  feature without re-scanning source tables.
- **Non-goal**: build that rating/leaderboard feature itself — only the data model
  (`user_achievement_progress`) is being put in place for it.
- **Non-goal**: perfect historical accuracy in the backfill migration — approximated
  where no exact signal exists, and explicitly called out where it is.

## Decisions

### Achievement name/description live only in the frontend, not the database

The DB dictionary (`achievement` table) stores `code` (+ an internal, non-translated
`label` for SQL/admin convenience) — no display text server-side. The Angular frontend
maps `code` → translated name/description via `utils/translations.ts`, the same pattern
already used for attribute names. Alternative considered: store `name` in the DB
directly — rejected because it's effectively single-language unless paired with a
translations table, and diverges from how every other code-identified dictionary on this
site already handles display text.

### Grant timing: inline for event-driven achievements, daily job for relative ones

Pictures Contributor and the four tiered series are checked inline at their triggering
action — cheap, and the user sees the congratulation message promptly. Top Pictures
Contributor (depends on everyone's counts) and Veteran (depends on wall-clock time, not
an action) can't be event-triggered, so both run in the existing `scheduler-daily` job.

### Counting mechanism: a persisted, ever-incrementing counter, not a live `COUNT(*)`

All four tiered series share one mechanism (`achievements.Repository.incrementAndGrant`):
an atomic `INSERT ... ON CONFLICT (user_id, metric) DO UPDATE SET count = count + 1
RETURNING count` against `user_achievement_progress`, checked against each tier's
threshold immediately after.

This was chosen over re-deriving a live `COUNT(*)` from each series' source table
(`picture`, `log_event`, `attrs_user_values`, `comment_message`) on every single action,
for three reasons:
1. **Performance** — O(1) per action instead of a table/log scan. This is not
   theoretical: an earlier draft of this design had Picture Buster counting via a
   `LIKE` scan over `log_event` on every single queue-for-removal action.
2. **Reliability** — a live count against `picture.status` is unsound for Picture
   Buster specifically, because a queued picture routinely moves on to `removed` or
   gets restored; `status = 'removing'` is a transient snapshot, not a cumulative
   record. The persisted counter sidesteps this because it's independent of later
   state changes to the source row.
3. **The explicit requirement** driving this change: persisting per-user/per-metric
   progress so a future rating/leaderboard feature can query
   `ORDER BY count DESC WHERE metric = ?` directly.

A consequence: the counter only ever increases. It is not recomputed from source tables
after the migration-26 backfill seed, so (for example) a moderator soft-deleting an old
comment does not shrink another user's Commentator progress. This is treated as a
feature, not a bug — a lifetime achievement counter shouldn't shrink because of
something unrelated happening later.

### Deleted users never earn or hold an achievement row

Enforced centrally in `Grant()` (which every grant path funnels through), not
re-implemented at each call site or in the backfill migration. `Grant` looks the user up
with a `Deleted: false` filter before inserting; a deleted/missing user short-circuits to
"not granted," no error.

### Picture Buster's live-counting scope is intentionally broader than what the backfill can approximate

Live counting increments on every successful `pictures.Repository.QueueRemove` call,
including the incidental one inside `AcceptReplacePicture` (queuing the replaced picture
for removal). The historical backfill can only identify the *direct* queue-for-removal
action in `log_event` (the replace-triggered call logs different text). This means a
moderator's counter grows slightly faster after the migration than the backfilled
starting point alone would suggest. Considered and rejected: threading a "direct vs.
incidental" flag through `QueueRemove` to keep the two paths in lockstep — not worth the
extra surface for a cosmetic consistency gain, especially given the backfill is already
explicitly an approximation.

### Historical backfill fidelity is not uniform, and that's disclosed rather than hidden

- **Exact** (ground truth already in the DB): Spec Master (`attrs_user_values` row
  count), Commentator (`comment_message` row count), Veteran (`users.reg_date`), Top
  Pictures Contributor (`users.pictures_total`).
- **Approximated**: Picture Inspector and Picture Buster, via `log_event`/`log_event_picture`
  text-matching against the exact `fmt.Sprintf` strings the application already logs for
  these actions (no better historical signal exists — moderator attribution for
  individual accept/queue actions isn't tracked anywhere else).
- The backfill migration does **not** send congratulation messages (raw SQL bypasses
  `Grant`'s notify step) — deliberately, to avoid flooding users with notifications about
  years-old activity the moment this feature deploys.

## Risks / Trade-offs

- **Approximated backfill counts** for Picture Inspector/Picture Buster could be off if
  `log_event.description` text ever changes without updating the migration's `LIKE`
  patterns — both patterns are documented at their source (`pictures-grpc.go`) and are
  currently the only occurrence of that exact phrase in the codebase.
- **`SetUserValue`'s "changed" semantics** — verified during implementation:
  `SetUserValue` returns `somethingChanged || valueChanged`, where `valueChanged` (from
  the per-type `setXUserValue` helper) specifically tracks whether the *acting user's*
  own value was newly written. The achievement callback is gated on `valueChanged`, not
  the combined return, confirmed correct by `TestSetUserValueCallsCallbackOnlyOnChange`
  (a resubmission of an identical value does not fire it a second time).
- **goqu upsert-with-RETURNING syntax** for `incrementAndGrant` — confirmed working as
  designed (`OnConflict(goqu.DoUpdate(...))` + `Returning(...).Executor().ScanValContext`),
  and additionally confirmed safe under concurrent access by
  `TestGrantConcurrentIncrementsAreNotLost` (20 goroutines incrementing the same
  `(userID, metric)` row concurrently; the counter lands on exactly 20, not less).

## Bug found and fixed during test-writing

Migration 26's original draft used one `WITH <cte> AS (...)` clause immediately followed
by two separate `INSERT` statements (one for `user_achievement`, one for
`user_achievement_progress`), both referencing the CTE. This is invalid SQL — a `WITH`
clause scopes to the single statement it's attached to, not to every statement in the
file. The migration failed with `relation "moderator_accept_counts" does not exist` the
first time it was actually run against Postgres (integration tests hadn't been run until
this point). Fixed by duplicating each CTE definition for both of its consuming
statements. This is the reason `openspec/changes/add-achievements/tasks.md` calls out
that the full test suite, not just `go build`/`go vet`, was needed to catch this — this
class of bug is invisible to static analysis and only surfaces when the SQL actually runs.

## Migration Plan

Three new migrations (`24`, `25`, `26`), additive only — no existing columns/tables
altered except a new index on `picture`. Rollback (`.down.sql`) drops the new tables/index
and deletes backfilled rows; safe since this feature never mutates pre-existing data.
