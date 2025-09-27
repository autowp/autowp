ALTER TABLE users
    ADD COLUMN green tinyint unsigned not null default 0,
    DROP COLUMN role;
