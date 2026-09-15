-- Marks a picture whose author credit was removed via the GDPR SuppressAuthor flow (see
-- migration 53's gdpr_objection table). Never exposed as anything but a plain boolean
-- ("author withheld") over the API - the FK itself, and thus the objection's name/reference/
-- contact email, stays server-side/admin-only. Purpose: without this, a moderator or the
-- picture's owner who does not know about the case could see a blank author field (perhaps
-- still visible in the image's own EXIF data) and innocently re-add the same author.
ALTER TABLE picture ADD COLUMN author_suppression_id integer NULL REFERENCES gdpr_objection (id);
