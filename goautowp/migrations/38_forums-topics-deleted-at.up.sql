-- When a topic was soft-deleted (status = 'deleted'), so a scheduled job can permanently purge it
-- and its comments after a retention period.
ALTER TABLE forums_topics ADD COLUMN deleted_at timestamptz;

-- Topics already deleted before this migration have no real deletion timestamp to backfill; stamp
-- them as deleted now, so they get one full retention period of grace before being purged instead
-- of being purged immediately (deleted_at IS NULL) or never (if the purge query required a value).
UPDATE forums_topics SET deleted_at = NOW() WHERE status = 'deleted' AND deleted_at IS NULL;
