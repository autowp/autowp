-- Switch duplicate detection from a 64-bit pHash (goimagehash) to Meta's
-- PDQ algorithm, a 256-bit hash far more resistant to rescaling,
-- recompression, and minor edits.
--
-- df_hash is purely an internal cache of the computed hash, so it's safe to
-- truncate and rebuild; run `goautowp pictures df-index` afterwards to
-- re-queue all pictures for re-hashing under PDQ.
--
-- df_distance rows are NOT touched: `hide` records a moderator's decision
-- that a pair is not actually a duplicate, which must survive the
-- algorithm change even though `distance` itself was computed under the
-- old 64-bit hash and stops being comparable to freshly computed PDQ
-- distances. Reindexing recomputes and upserts `distance` for every pair
-- that still qualifies as the pictures involved get re-hashed.
DELETE FROM df_hash;

ALTER TABLE df_hash
    ALTER COLUMN hash TYPE bit(256) USING NULL::bit(256);
