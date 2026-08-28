-- Set when the author edits a comment after the silent grace period; drives the "edited" marker.
ALTER TABLE comment_message ADD COLUMN updated_at timestamptz;
