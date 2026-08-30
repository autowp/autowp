-- Rename the Japanese language code from the legacy `jp` (a country code) to the ISO 639-1
-- `ja`, so it matches the Angular locale, Keycloak's locale, and schema.JapaneseLanguageCode.
-- Japanese was seeded as a valid language in migration 16 but never exposed in the UI, so the
-- child tables below normally hold no `jp` rows; the UPDATEs are here for correctness if they do.
INSERT INTO language (code) VALUES ('ja') ON CONFLICT (code) DO NOTHING;

UPDATE users SET language = 'ja' WHERE language = 'jp';
UPDATE item_language SET language = 'ja' WHERE language = 'jp';
UPDATE item_parent_language SET language = 'ja' WHERE language = 'jp';

DELETE FROM language WHERE code = 'jp';
