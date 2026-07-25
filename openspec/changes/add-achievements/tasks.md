## 1. Database

- [x] 1.1 Write migrations 24 (`achievement`, `user_achievement`,
      `user_achievement_progress` tables + seed data), 25 (picture moderator/status
      index), 26 (historical backfill), with `.down.sql` for each
- [x] 1.2 Write `goautowp/schema/achievement.go` (table/column consts+vars, row structs,
      `AchievementID*` constants 1–23)

## 2. Backend core

- [x] 2.1 Write `goautowp/achievements` package: `Grant`, `incrementAndGrant`, the four
      tiered `Grant*` methods, `RecomputeTopPicturesContributors`,
      `RecomputeVeteranBadges`, `Progress`, `UserAchievements`, `AchievementCounts`
- [x] 2.2 Add `pictures.Repository` callbacks: `afterPictureAcceptedAchievements` (on
      `Accept`), `afterPictureQueuedForRemoval` (on `QueueRemove`)
- [x] 2.3 Add `attrs.Repository` callback: `afterUserValueSet` on `SetUserValue`, gated
      on the value actually being new/changed
- [x] 2.4 Add `comments.Repository` callback: `afterCommentAdded` on `Add`

## 3. API surface

- [x] 3.1 Add `Achievements` service to `spec.proto` (`GetUserAchievements`,
      `GetAchievementStats`) and supporting messages; regenerate via `./protoc.sh`
- [x] 3.2 Write `goautowp/achievements-grpc.go` (`AchievementsGRPCServer`, both methods
      unauthenticated/public)
- [x] 3.3 Wire `goautowp/container.go`: `AchievementsRepository`/`AchievementsGRPCServer`
      accessors, closures into pictures/attrs/comments repositories, service
      registration
- [x] 3.4 Wire `goautowp/app.go`: call `RecomputeTopPicturesContributors` and
      `RecomputeVeteranBadges` from `SchedulerDaily`

## 4. Notifications

- [x] 4.1 Add `pm/achievement-granted` message key to `goautowp/i18nbundle/en.json` and
      the other 9 locale files (translated for all 10 locales; non-English translations
      are best-effort and should get a native-speaker review pass, especially zh/he)

## 5. Frontend

- [x] 5.1 Add achievement code → name/description maps to
      `frontend/src/app/utils/translations.ts`
- [x] 5.2 Add an Achievements section (earned badges + in-progress tiers) to the public
      user profile page
- [x] 5.3 Add a new `/achievements` catalog page listing all 23 achievements with icon,
      description, and per-achievement earned-count
- [x] 5.4 Add placeholder SVG icon assets under `frontend/src/assets/achievements/`
      (real artwork still needed — these are visual placeholders only)
- [x] 5.5 Run `ng extract-i18n` and translate newly-extracted tokens into all
      `messages.*.xlf` files (same best-effort-translation caveat as 4.1)

## 6. Verification

- [x] 6.1 `go build ./...`, `go vet ./...`, `golangci-lint run ./...` — all clean
- [x] 6.2 Add/extend unit and integration tests: `achievements/repository_test.go`
      (Grant idempotency, deleted-user exclusion, tier-crossing, concurrent-increment
      safety, Progress omission rules, AchievementCounts, RecomputeVeteranBadges,
      RecomputeTopPicturesContributors); callback-firing tests added to
      `pictures/repository_test.go` (Accept/QueueRemove), `attrs/repository_test.go`
      (SetUserValue changed-gating), `comments/repository_test.go` (Add). All passing
      against the real Postgres test DB. Found and fixed a real bug in the process: a
      `WITH` CTE in migration 26 was written as if shared across two separate `INSERT`
      statements, which is not valid SQL (a CTE scopes to the one statement it's attached
      to) — fixed by duplicating each CTE for both consuming statements.
- [x] 6.3 `ng lint`, `ng build --configuration production` — clean, zero
      missing-translation warnings
- [ ] 6.4 Manual end-to-end pass against local `docker-compose` stack (see plan §3) — not
      yet done; recommended before shipping (moderator accept/queue flows, profile page
      rendering, `/achievements` page, congratulation message delivery)
