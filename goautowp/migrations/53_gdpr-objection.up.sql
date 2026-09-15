-- Internal suppression list: a person objected to / requested erasure of their name being used
-- as a public photo-author credit (GDPR Art. 17/21). Keeps only what is needed to recognise the
-- same name being reintroduced later and warn staff - never exposed publicly. See CreateItem
-- (person) and CreatePictureItem (author link) checks, and the EXIF copyrights-text guard in
-- pictures.Repository.processEXIF.
--
-- One row per case (not per name spelling): a person can be catalogued under several localized
-- name spellings (see items.Repository.ItemLanguageList), all of which need to be recognised as
-- the same suppression, share one hit counter, and link back to the same set of affected
-- pictures - see gdpr_objection_name below and picture.author_suppression_id (migration 54).
CREATE TABLE gdpr_objection
(
    id             serial PRIMARY KEY,
    reference      varchar(255) NOT NULL,
    contact_email  varchar(255) NOT NULL DEFAULT '',
    note           text         NOT NULL DEFAULT '',
    -- Original request correspondence (e.g. the erasure/objection email), kept separately from
    -- `note` on purpose: it is evidentiary (Art. 17(3)(e) - defence of legal claims) rather than
    -- part of the minimal suppression signal, so it is never selected by the name-matching
    -- queries and is fetched only when a moderator opens the record's detail view. Consider a
    -- bounded retention/review for this field specifically; the case itself is meant to persist
    -- as long as the catalogue does.
    source_text_id int          NULL REFERENCES textstorage_text (id),
    created_at     timestamp    NOT NULL DEFAULT NOW(),
    hit_count      integer      NOT NULL DEFAULT 0,
    last_hit_at    timestamp    NULL
);

-- Every known name spelling for a case (one row per localized variant). Matching (FindExact/
-- FindInText in compliance.Repository) is done against this table; a hit bumps the parent
-- gdpr_objection's counters regardless of which spelling matched.
CREATE TABLE gdpr_objection_name
(
    id              serial PRIMARY KEY,
    objection_id    integer      NOT NULL REFERENCES gdpr_objection (id) ON DELETE CASCADE,
    name            varchar(255) NOT NULL,
    normalized_name varchar(255) NOT NULL,
    -- normalized_name with its words alphabetically sorted, so a given-name/family-name swap
    -- ("Olaf Itrich" vs "Itrich Olaf" - common between EXIF conventions, cultures, and hand-typed
    -- entries) still matches. See compliance.CanonicalizeName.
    canonical_name  varchar(255) NOT NULL
);

CREATE INDEX gdpr_objection_name_objection_id_idx ON gdpr_objection_name (objection_id);
CREATE INDEX gdpr_objection_name_normalized_name_idx ON gdpr_objection_name (normalized_name);
CREATE INDEX gdpr_objection_name_canonical_name_idx ON gdpr_objection_name (canonical_name);
