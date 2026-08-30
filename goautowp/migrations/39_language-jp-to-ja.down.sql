INSERT INTO language (code) VALUES ('jp') ON CONFLICT (code) DO NOTHING;

UPDATE users SET language = 'jp' WHERE language = 'ja';
UPDATE item_language SET language = 'jp' WHERE language = 'ja';
UPDATE item_parent_language SET language = 'jp' WHERE language = 'ja';

DELETE FROM language WHERE code = 'ja';
