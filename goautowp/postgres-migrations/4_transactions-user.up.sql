ALTER TABLE transaction ADD COLUMN user_id BIGINT NULL DEFAULT NULL;
CREATE INDEX transaction_date_index ON transaction (date DESC);
