ALTER TABLE users
    ADD COLUMN terms_version smallint,
    ADD COLUMN terms_accepted_at timestamptz;
