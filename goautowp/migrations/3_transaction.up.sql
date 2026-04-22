CREATE TABLE transaction (
    id SERIAL PRIMARY KEY,
    sum INTEGER NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL,
    date TIMESTAMP WITH TIME ZONE not null DEFAULT now(),
    contributor VARCHAR(50) NOT NULL DEFAULT '',
    purpose VARCHAR(100) NOT NULL DEFAULT ''
);
