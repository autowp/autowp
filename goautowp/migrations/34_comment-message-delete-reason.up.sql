-- The moderator's reason for removing a comment, so it can be shown to the author (DSA Art. 17).
ALTER TABLE comment_message ADD COLUMN delete_reason varchar(255);
