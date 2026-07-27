DELETE FROM df_hash;

ALTER TABLE df_hash
    ALTER COLUMN hash TYPE bigint USING NULL::bigint;
