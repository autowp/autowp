DROP INDEX users_uuid;
ALTER TABLE users ADD CONSTRAINT e_mail UNIQUE (e_mail);
