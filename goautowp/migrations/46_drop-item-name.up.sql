-- item.name is the legacy MySQL-era name column. Every read and write path was migrated onto
-- item_language(language='xx') earlier (see migrations 44, 45), and nothing in the Go code
-- references item.name any more - it's safe to drop.
ALTER TABLE item DROP COLUMN name;
