ALTER TABLE pictures ADD CONSTRAINT pictures_change_status_user_fk FOREIGN KEY (change_status_user_id) REFERENCES users(id);
