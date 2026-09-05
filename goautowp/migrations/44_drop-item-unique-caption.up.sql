-- item_unique_caption enforced no-two-items-with-the-same-(name, years, body, model-years,
-- is_group, spec_id) at the DB level, keyed on the legacy item.name column. Nothing in the Go
-- code handles its unique-violation specially (a collision just bubbled up as a raw internal
-- error), and no query relies on its backing index for lookups (0 index scans in
-- pg_stat_user_indexes, and no WHERE/ORDER BY in the codebase matches this column combination) -
-- dropping it removes both the guarantee and its now-unnecessary tie to item.name.
ALTER TABLE item DROP CONSTRAINT item_unique_caption;
