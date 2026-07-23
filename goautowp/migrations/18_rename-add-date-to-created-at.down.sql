ALTER INDEX log_events_created_at RENAME TO log_events_add_datetime;

ALTER TABLE pictures RENAME COLUMN created_at TO add_date;
ALTER TABLE personal_messages RENAME COLUMN created_at TO add_datetime;
ALTER TABLE log_events RENAME COLUMN created_at TO add_datetime;
ALTER TABLE forums_topics RENAME COLUMN created_at TO add_datetime;
ALTER TABLE item RENAME COLUMN created_at TO add_datetime;
ALTER TABLE comment_vote RENAME COLUMN created_at TO add_date;
ALTER TABLE attrs_user_values RENAME COLUMN created_at TO add_date;
ALTER TABLE article RENAME COLUMN created_at TO add_date;
