-- The `icq` profile field is a leftover from the MySQL era: nothing in the backend or the
-- frontend reads or writes it, and an ICQ number is personal data we have no reason to keep.
ALTER TABLE users DROP COLUMN icq;
