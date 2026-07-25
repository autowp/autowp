## Why

wheelsage/autowp has no way to recognize sustained user contribution — uploading and
having pictures accepted, moderating, filling in vehicle specs, commenting, or simply
sticking around for years. A gamification layer (achievements/badges) rewards this
activity, makes it visible on public profiles, and gives moderators in particular a
sense of progression through a large volume of repetitive review work.

## What Changes

- Add a DB-backed achievement dictionary (23 initial achievements) identified by a
  stable `code`, with display name/description living only in the Angular i18n layer
  (not the database).
- Add a permanent, idempotent per-user grant record (`user_achievement`) — achievements
  are never revoked once earned.
- Add a persisted, ever-incrementing per-user/per-metric progress counter
  (`user_achievement_progress`) backing the four tiered achievement series, so grant
  checks are O(1) instead of re-deriving counts from source tables, and so a future
  rating/leaderboard feature can query per-metric standings directly.
- Grant achievements inline at the relevant action (picture accepted, picture queued for
  removal, spec/attribute value set, comment posted), or via a daily scheduled job for
  the two relative/time-based achievements (top-10 leaderboard, 10-year veteran badge).
- Send a system-originated personal message congratulating the user on every new grant.
- Backfill historical grants and progress counters via migration, using exact
  ground-truth counts where available (spec values, comments, registration date, current
  picture totals) and an approximation from `log_event`/`log_event_picture` text-matching
  where no better signal exists (moderator accept/queue-removal history).
- Expose two public, unauthenticated gRPC endpoints: a user's earned achievements +
  in-progress tiers (for the profile page), and per-achievement earned-counts (for a new
  public catalog page).
- Add a public user profile "Achievements" section (earned badges + progress toward the
  next unearned tier) and a new `/achievements` catalog page listing all 23 definitions,
  their "how to earn" description, and how many users have earned each one.

## Impact

- **Affected specs**: `achievements` (new capability)
- **Affected code**:
  - `spec.proto` — new `Achievements` service and messages
  - `goautowp/migrations/24_achievements.*`, `25_picture-moderator-status-index.*`,
    `26_achievements_backfill.*`
  - `goautowp/schema/achievement.go` (new)
  - `goautowp/achievements/` (new package: `repository.go`)
  - `goautowp/achievements-grpc.go` (new)
  - `goautowp/pictures/repository.go`, `goautowp/attrs/repository.go`,
    `goautowp/comments/repository.go` — new callback hooks on the write paths that feed
    achievement counters
  - `goautowp/container.go`, `goautowp/app.go` — DI wiring and scheduler job
  - `goautowp/i18nbundle/*.json` — one new personal-message template key, all locales
  - `frontend/src/app/utils/translations.ts` — achievement code → name/description maps
  - `frontend/src/app/users/user/user.component.*` — profile Achievements section
  - `frontend/src/app/achievements/` (new) — `/achievements` catalog page
  - `frontend/src/assets/achievements/*.svg` (new) — per-code icon assets
- **No breaking changes** — purely additive; no existing endpoints, messages, or schema
  are modified in an incompatible way (the one existing-table touch is a new index on
  `picture`, not a column/behavior change).
