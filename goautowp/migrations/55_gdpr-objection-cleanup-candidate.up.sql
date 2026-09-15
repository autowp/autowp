-- Content that mentions a suppressed author's name but isn't something the GDPR SuppressAuthor
-- flow can safely touch automatically: a picture's freeform copyrights text (may legitimately
-- name other people too) and visitor comments (third-party speech, not site-generated metadata -
-- removing it is a moderation decision, not an automated one). Found by a site-wide text search
-- at SuppressAuthor time (and potentially again later), persisted here so a moderator can review
-- and resolve each one from the suppression-list page rather than losing the list the moment a
-- one-off toast is dismissed.
CREATE TABLE gdpr_objection_cleanup_candidate
(
    id           serial PRIMARY KEY,
    objection_id integer   NOT NULL REFERENCES gdpr_objection (id) ON DELETE CASCADE,
    -- 1 = picture whose copyrights_text_id text matched; 2 = comment_message whose text matched.
    entity_type  smallint  NOT NULL,
    entity_id    bigint    NOT NULL,
    found_at     timestamp NOT NULL DEFAULT NOW(),
    resolved_at  timestamp NULL,
    resolved_by  integer   NULL,
    UNIQUE (objection_id, entity_type, entity_id)
);

CREATE INDEX gdpr_objection_cleanup_candidate_objection_id_idx
    ON gdpr_objection_cleanup_candidate (objection_id);
