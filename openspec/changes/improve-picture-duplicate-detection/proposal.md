## Why

The existing similar-picture detection (`goautowp/duplicate-finder.go`) hashes every
picture with `goimagehash.PerceptionHash`, a 64-bit DCT-based pHash, and flags a pair as
a likely duplicate at Hamming distance ≤ 3. In practice this is a weak signal: 64 bits
gives little room to separate "the same photo, recompressed/resized" from "two different
but visually similar car photos," and the algorithm has no particular resistance to the
kinds of edits pictures on this site actually go through (re-export, watermarking, minor
crops). Moderators either miss real duplicates or get noisy false positives.

Meta's PDQ ("Perceptual DCT Quality") hash is a purpose-built evolution of the same
DCT-hash family — same core idea, 256 bits instead of 64, with a documented, calibrated
match threshold — designed specifically for large-scale near-duplicate photo detection
in production. It is a much better fit than switching to an entirely different detection
paradigm (CNN embeddings, keypoint matching): no new inference infrastructure, no new
runtime dependency beyond a single Go library, and it drops into the existing
hash-then-compare architecture.

## What Changes

- Replace the 64-bit pHash algorithm with Meta's 256-bit PDQ hash
  (`github.com/ajdnik/imghash/v2`), computed identically for every picture (JPEG, PNG,
  GIF, WebP, BMP, AVIF — the existing decode paths are unchanged).
- Store the hash as a native Postgres `bit(256)` column (`df_hash.hash`, previously
  `bigint`) instead of splitting it across multiple integer columns, so its Hamming
  distance can still be computed entirely on the Postgres side with the native `#` XOR
  operator and `bit_count()` — no rows are pulled into the application to compare
  against the ~2M-row `df_hash` table.
- Raise the match threshold from 3 (out of 64 bits, tuned for the old algorithm) to 31
  (out of 256 bits) — Meta's published recommendation for PDQ, and empirically
  confirmed against this repo's own test fixtures (same photo at two resolutions:
  distance 2; unrelated photo: distance 118).
- Preserve all existing `df_distance` rows, including moderator `hide` decisions, across
  the algorithm change — only the internal `df_hash` cache is rebuilt from scratch.
- Add a migration (`28_df-hash-pdq`) that truncates `df_hash` and changes its `hash`
  column to `bit(256)`; re-indexing (`goautowp pictures df-index` + the `df-amqp`
  worker) is required after deploying to repopulate it under the new algorithm.

## Impact

- **Affected specs**: `picture-duplicate-detection` (new spec; first time this
  pre-existing capability is documented via OpenSpec)
- **Affected code**:
  - `goautowp/duplicate-finder.go` — hashing algorithm, storage encoding, distance query
  - `goautowp/schema/df-hash.go` — column definition
  - `goautowp/migrations/28_df-hash-pdq.{up,down}.sql` (new)
  - `goautowp/duplicate-finder_test.go`, `goautowp/duplicate-finder-hash_test.go` (new)
  - `goautowp/go.mod`/`go.sum` — new dependency `github.com/ajdnik/imghash/v2`
- **No API/schema-facing breaking changes**: `df_distance`'s shape and semantics
  (`src_picture_id`, `dst_picture_id`, `distance`, `hide`) are unchanged; only how
  `distance` values are computed changes. No gRPC/proto changes.
- **Operational impact**: after this deploys, similar-picture detection effectively goes
  cold until the catalogue is re-indexed — see `tasks.md` §4 and `design.md`'s Migration
  Plan.
