ALTER TABLE picture_item RENAME COLUMN created_at TO "timestamp";
ALTER TABLE picture_item
  DROP COLUMN perspective_user_id,
  DROP COLUMN add_user_id;
