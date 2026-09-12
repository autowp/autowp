-- Backfill item_language_cache: for each of the site's selectable UI languages (see
-- config.Languages / items.languagePriority), pick the best available item_language row
-- through that language's fallback order. Mirrors the read path that
-- items.recomputeItemLanguageCache maintains incrementally going forward; xx and the
-- unselectable plain 'pt' are never cached, only used as fallback within these orderings.
CREATE TABLE item_language_cache (
    item_id  integer      NOT NULL REFERENCES item(id) ON DELETE CASCADE,
    language varchar(6)   NOT NULL,
    name     varchar(255) NOT NULL,
    PRIMARY KEY (item_id, language)
);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'uk', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['uk','ru','en','it','fr','de','es','pt','pt-br','be','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'de', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['de','en','it','fr','es','pt','pt-br','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'es', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['es','en','it','fr','de','pt','pt-br','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'zh', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['zh','en','it','fr','de','es','pt','pt-br','ru','be','uk','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'it', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['it','en','fr','de','es','pt','pt-br','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'fr', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['fr','en','it','de','es','pt','pt-br','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'be', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['be','ru','uk','en','it','fr','de','es','pt','pt-br','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'pt-br', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['pt-br','pt','en','it','fr','de','es','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'ja', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['ja','en','it','fr','de','es','pt','pt-br','ru','be','uk','zh','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'he', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['he','en','it','fr','de','es','pt','pt-br','ru','be','uk','zh','ja','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'ru', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['ru','en','it','fr','de','es','pt','pt-br','be','uk','zh','ja','he','xx']::varchar[], il.language);

INSERT INTO item_language_cache (item_id, language, name)
SELECT DISTINCT ON (il.item_id) il.item_id, 'en', il.name
FROM item_language il
WHERE LENGTH(il.name) > 0
ORDER BY il.item_id, array_position(ARRAY['en','it','fr','de','es','pt','pt-br','ru','be','uk','zh','ja','he','xx']::varchar[], il.language);
