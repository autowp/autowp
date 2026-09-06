ALTER TABLE picture
  ADD COLUMN change_source_url_user_id int NULL REFERENCES users (id),
  ADD COLUMN change_source_url_date timestamptz NULL;
