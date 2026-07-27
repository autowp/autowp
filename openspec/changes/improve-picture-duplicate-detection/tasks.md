## 1. Database

- [x] 1.1 Write migration 28 (`df-hash-pdq`): truncate `df_hash`, change `hash` from
      `bigint` to `bit(256)`, with a `.down.sql` reverting both — verified against the
      real Postgres test instance (`ALTER TABLE ... USING NULL::bit(256)` on an emptied
      table), not just read from docs
- [x] 1.2 Update `goautowp/schema/df-hash.go` (single `hash` column, doc comment
      explaining the `bit(256)` choice)
- [x] 1.3 Confirm `df_distance` is untouched by the migration — `hide` (moderator
      "not a duplicate" decision) must survive the algorithm change

## 2. Backend core

- [x] 2.1 Add dependency `github.com/ajdnik/imghash/v2` (MIT, pure Go, actively
      maintained); rejected hand-porting PDQ's Jarosz-filter+DCT pipeline
- [x] 2.2 Rewrite `getFileHash` in `goautowp/duplicate-finder.go` to compute a 256-bit
      PDQ hash instead of a 64-bit pHash
- [x] 2.3 Add `bitString()` to render the 32-byte hash as 256-character Postgres
      bit-string text for storage (Postgres has no bytea↔bit(varying) cast, confirmed by
      direct experiment)
- [x] 2.4 Rewrite `updateDistance` to compute the Hamming distance with a self-join
      (`df_hash` aliased twice) and native `BIT_COUNT(own.hash # other.hash)`, filtered
      server-side — first draft fetched every other hash into the app and computed
      distance in Go, which doesn't scale to `df_hash`'s ~2M rows and was corrected
      before landing
- [x] 2.5 Raise `threshold` from 3 to 31 (Meta's published PDQ recommendation);
      empirically verified against `test/large.jpg` vs `test/small.jpg` (distance 2) and
      an unrelated fixture (distance 118)
- [x] 2.6 Add `hammingDistance()` as a pure, DB-free Go helper mirroring the SQL
      computation, for unit testing
- [x] 2.7 Add a constant 100ms `time.Sleep` (`indexDelay`) after each message processed
      in `ListenAMQP`'s consumer loop, so a full-catalogue backfill doesn't run
      `updateDistance`'s whole-table self-join back-to-back with no pause and saturate
      Postgres — the consumer was already single-threaded (no fan-out to throttle), so
      this is the whole fix

## 3. Tests

- [x] 3.1 `duplicate-finder-hash_test.go` (new): `TestHammingDistance` (pure bit-flip
      unit test), `TestGetFileHashSimilarity` (regression guard using real fixtures —
      same photo at two resolutions must stay well under `threshold`, an unrelated photo
      must stay well above it)
- [x] 3.2 Update `duplicate-finder_test.go`: drop the raw-hash-column assertions (no
      longer meaningful to read back the same way with `bit(256)`), keep the end-to-end
      distance assertion with a wider margin (≤10, was ≤2) to avoid overfitting to one
      exact value
- [x] 3.3 `go build ./...`, `go vet ./...`, `golangci-lint run ./... --new-from-rev=HEAD`
      — clean
- [x] 3.4 Run `TestPostgresMigrations`, `TestDuplicateFinder`, and the new hash tests
      against the real Postgres test instance; manually verified the down-migration
      round-trips correctly

## 4. Deployment / backfill

- [ ] 4.1 After deploying, run `goautowp pictures df-index` to enqueue every picture for
      re-hashing, **and** confirm the `df-amqp` worker (`serve --df-amqp`) is running to
      actually drain that queue — the CLI command only enqueues, it does not compute
      hashes itself
- [ ] 4.2 Spot-check a handful of known-duplicate picture pairs post-reindex to confirm
      they're still (or newly) flagged under the new threshold
- [ ] 4.3 Budget for the full backfill's wall-clock time: `indexDelay` alone puts a
      ~55-hour floor under a ~2M-picture catalogue (2M × 100ms), before counting the
      actual fetch/hash/DB work per picture — plan the reindex as a multi-day background
      operation, not a one-off deploy step
