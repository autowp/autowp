-- users.img was the only reference into the image table without a foreign key
-- (picture.image_id, item.logo_id, image_formatted.formated_image_id already have one). A removed
-- avatar could leave the column dangling, surfacing later as
-- `doFormatImage(): sql: no rows in result set` when that user was rendered.
--
-- Null any existing orphans, then add the constraint with the same NO ACTION delete behaviour as
-- the other image references: DeleteUser / DeletePhoto / SetPhoto all clear users.img before
-- calling RemoveImage, so a delete that violates this FK means a caller skipped that step and
-- should fail loudly rather than have the column silently nulled.
UPDATE users SET img = NULL
WHERE img IS NOT NULL AND img NOT IN (SELECT id FROM image);

ALTER TABLE users
  ADD CONSTRAINT users_img_fkey FOREIGN KEY (img) REFERENCES image (id);
