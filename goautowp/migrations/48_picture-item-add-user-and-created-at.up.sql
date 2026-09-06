ALTER TABLE picture_item
  ADD COLUMN add_user_id int NULL REFERENCES users (id),
  ADD COLUMN perspective_user_id int NULL REFERENCES users (id);
ALTER TABLE picture_item RENAME COLUMN "timestamp" TO created_at;
