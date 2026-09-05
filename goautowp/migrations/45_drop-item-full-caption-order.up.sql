-- Dead index: 0 scans in pg_stat_user_indexes, and no query in the codebase orders or filters by
-- (name, body, begin_year, end_year) - the "age" sorts use begin_order_cache/end_order_cache
-- instead. Keyed on the legacy item.name column, same as item_unique_caption (migration 44).
DROP INDEX item_full_caption_order;
