## Context

`goautowp/duplicate-finder.go` is an AMQP-consumer background job: on picture upload it
receives `(pictureID, url)`, downloads the image, hashes it, and records it against
every other already-hashed picture within a Hamming-distance threshold in `df_distance`,
which the moderation UI reads to surface "these might be duplicates" candidates. This
change swaps the hashing algorithm and the on-disk hash representation; the AMQP
plumbing, the `df_distance` table shape, and the moderator `hide` workflow are untouched.

Three other engines were considered and rejected for now (CNN embeddings + pgvector,
classical keypoint matching via OpenCV/gocv) in favor of PDQ specifically because it's a
drop-in replacement for the existing hash-and-compare architecture — no new inference
runtime, no new infrastructure, one new pure-Go dependency.

## Goals / Non-Goals

- **Goal**: meaningfully reduce false positives/negatives from the old 64-bit hash by
  moving to a 256-bit hash with a calibrated, published match threshold.
- **Goal**: keep distance computation entirely on the Postgres side — `df_hash` has
  ~2M rows; pulling all of them into the application on every single picture indexed is
  not viable.
- **Goal**: never lose a moderator's "not a duplicate" (`hide`) decision as a side effect
  of this change.
- **Non-goal**: EXIF-orientation normalization before hashing. A same-photo-different-
  EXIF-rotation pair will still hash differently under PDQ, same as it did under pHash.
  Worth doing, deliberately left out of this change's scope.
- **Non-goal**: dihedral (rotation/mirror) hash matching. PDQ supports this in principle
  by permuting the hash's bit grid, but a mirrored photo may legitimately be a different
  photo in this catalogue (e.g. opposite side of the same car), so auto-matching mirrors
  was judged more likely to produce unwanted false positives than to catch real
  duplicates. Left for a future, deliberate decision rather than bundled in here.
- **Non-goal**: cleaning up pre-existing `df_distance` rows that no longer qualify as a
  match under the new threshold. See Risks below.

## Decisions

### Hash storage: a single `bit(256)` column, not a bytea or split bigint columns

Three representations were tried, in order:

1. **`bytea(32)`**, computing the Hamming distance by fetching every other picture's
   hash into the application and XOR/popcounting in Go. Rejected: `df_hash` has ~2M
   rows; fetching all of them on every single `Index()` call doesn't scale, and shifts
   load from the database (which can filter server-side) to the application and network.
2. **Four `bigint` columns** (`hash_0`..`hash_3`), reusing the old algorithm's exact
   working pattern — native bigint `#` (XOR) cast to `bytea`, then `bit_count(bytea)`
   (Postgres 14+) — summed across all four segments. This works and stays entirely
   server-side, but is a more awkward schema (four columns for one logical value) and
   more verbose SQL.
3. **A single `bit(256)` column** (chosen). Confirmed by direct experiment against the
   real Postgres instance that `bit`/`bit varying` natively supports the `#` operator
   and `bit_count()` between two same-length values, and that a Go `string` of 256
   `'0'`/`'1'` characters round-trips through `lib/pq` correctly as a `bit(256)`
   parameter — including with **no explicit cast** on `INSERT ... VALUES ($1, $2)`,
   because Postgres infers the parameter's type from the target column.

Postgres has **no cast between `bytea` and `bit`/`bit varying`** in either direction
(confirmed directly: `'...'::bytea::bit(32)` and `hash::bytea` where `hash` is
`bit(256)` both fail with `cannot cast type ... to ...`). This is why the hash can't just
be passed through as a `[]byte` end to end — `getFileHash` still returns `[]byte` (a
natural, testable representation), and `bitString()` converts it to text only at the
point it's written to the database.

### Distance computation: a self-join, not fetch-then-compare

`updateDistance` joins `df_hash` against itself (aliased `own`/`other`), computing
`BIT_COUNT(own.hash # other.hash)` in an inner query and filtering `WHERE distance <=
threshold` in the outer one (the filter can't reference the inner alias directly in
standard SQL, hence the two-level query — the same shape the old pHash-era code already
used). This never brings a hash value into the application at all outside of the write
path (`Index`), and Postgres can push the `picture_id` equality/inequality and the
`hash` comparison down without a network round-trip per candidate row.

An existence check (`SELECT 1 FROM df_hash WHERE picture_id = $1`) is still done up
front so `updateDistance` continues to fail loudly (`sql.ErrNoRows`) if called for a
picture that hasn't been hashed yet, matching the old behavior — a bare self-join would
have silently no-op'd in that case instead.

### Threshold: 31 out of 256 bits

Meta's own PDQ documentation recommends ≤ 31 as the default "likely match" cutoff.
Verified this is sound for this catalogue's actual images rather than trusting the
number blindly: hashing `test/large.jpg` and `test/small.jpg` (same source photo,
600×429 vs 200×143 — a real resize pair already used as a fixture in this repo) gives a
distance of 2; hashing either against `test/test.jpg` (an unrelated photo) gives 118.
The margin between "real duplicate" and "unrelated" is large enough that 31 has
substantial headroom on both sides for this data.

### A constant 100ms delay paces the AMQP consumer loop

`updateDistance`'s self-join scans the entire `df_hash` table on every picture indexed
(see above), and during a full-catalogue backfill that table grows toward ~2M rows over
the course of the run — so the per-picture cost keeps climbing as the backfill
progresses. `ListenAMQP`'s consumer loop was already single-threaded (one message
processed at a time, no goroutine fan-out), so there was no concurrency to throttle; the
risk was simply running that growing self-join back-to-back with zero pause, which would
peg Postgres CPU/IO continuously and could starve the live site's normal queries against
the same database.

Fix: a constant `time.Sleep(indexDelay)` (100ms) after every message processed,
regardless of success or failure. Considered and rejected: a configurable delay (extra
config surface for a value that doesn't need tuning per-environment) and RabbitMQ
QoS/prefetch limiting (doesn't actually pace *work*, only how many messages sit buffered
in the local channel — the consumer already pulls and fully processes one message before
looking at the next, with auto-ack, so prefetch has no effect on throughput here).

This does not reduce the total DB work a full backfill does — it only spreads it out
over more wall-clock time so it doesn't monopolize the database continuously. See Risks
below for the resulting time budget.

### `df_distance` rows are never deleted by the migration

`hide = true` on a `df_distance` row is a moderator's permanent judgment that a
candidate pair is *not* actually a duplicate. That judgment must survive an internal
algorithm swap. The migration therefore only truncates `df_hash` (a pure internal cache,
no moderator-facing meaning) — `df_distance` is left completely alone. As pictures get
re-hashed under PDQ, `updateDistance`'s `INSERT ... ON CONFLICT DO UPDATE` refreshes
`distance` for every pair that still qualifies, without touching `hide`.

## Risks / Trade-offs

- **Stale `df_distance` rows for pairs that no longer qualify.** A pair flagged by the
  old 64-bit algorithm whose real PDQ distance is, say, 150, will keep its old small
  `distance` value indefinitely unless something re-triggers `updateDistance` for one of
  the two pictures — the upsert only ever adds/refreshes rows that currently qualify, it
  never removes ones that stop qualifying. In practice this self-heals as the catalogue
  gets re-indexed (every picture is re-hashed once, per the migration), but any pair
  where *neither* picture is ever re-indexed again after that pass will carry a
  misleadingly small old-scale `distance` forever. Not fixed in this change — flagged
  as a known follow-up if it turns out to matter in practice.
- **Full cold restart of detection, now measured in days, not hours.** Every picture
  needs re-hashing after this deploys; until the `df-amqp` worker finishes draining the
  `pictures df-index` queue, no new duplicate candidates will surface at all (existing
  `df_distance` rows, including `hide` decisions, remain visible throughout). `indexDelay`
  alone puts a ~55-hour floor under a ~2M-picture backfill (2M × 100ms), before counting
  the actual fetch/hash/DB work per picture — this is a deliberate trade of wall-clock
  time for not saturating production Postgres, not an oversight. See `tasks.md` §4.3.
- **New third-party dependency** (`github.com/ajdnik/imghash/v2`). Chosen over a hand
  port specifically to avoid subtly reimplementing PDQ's Jarosz-filter downsampling
  incorrectly; mitigated by the `TestGetFileHashSimilarity` regression test pinning
  expected behavior against this repo's own fixtures rather than trusting the library
  blindly.

## Migration Plan

One migration (`28_df-hash-pdq`), reversible: `up` truncates `df_hash` and changes
`hash` from `bigint` to `bit(256)`; `down` reverses both. `df_distance` is untouched in
both directions. Because the old hash values are meaningless under the new algorithm,
there is no sensible row-by-row up-conversion — truncation is intentional, not an
oversight. See `tasks.md` §4 for the required post-deploy re-indexing step.
