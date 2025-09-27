ALTER TABLE users
    DROP COLUMN green,
    ADD COLUMN role varchar(50) default 'user' not null;
