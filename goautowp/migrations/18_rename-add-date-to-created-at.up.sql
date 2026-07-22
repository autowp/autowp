ALTER TABLE article RENAME COLUMN add_date TO created_at;
ALTER TABLE attrs_user_values RENAME COLUMN add_date TO created_at;
ALTER TABLE comment_vote RENAME COLUMN add_date TO created_at;
ALTER TABLE item RENAME COLUMN add_datetime TO created_at;
ALTER TABLE forums_topics RENAME COLUMN add_datetime TO created_at;
ALTER TABLE log_events RENAME COLUMN add_datetime TO created_at;
ALTER TABLE personal_messages RENAME COLUMN add_datetime TO created_at;
ALTER TABLE pictures RENAME COLUMN add_date TO created_at;

ALTER INDEX log_events_add_datetime RENAME TO log_events_created_at;
