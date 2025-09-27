ALTER TABLE users DROP CONSTRAINT e_mail, ADD COLUMN uuid binary(16) DEFAULT NULL;
CREATE UNIQUE INDEX users_uuid ON users(uuid);
UPDATE users JOIN user_account ON users.id = user_account.user_id AND user_account.service_id="keycloak"
    SET users.uuid = UUID_TO_BIN(user_account.external_id);
