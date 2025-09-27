CREATE TABLE user_user_preferences (
    user_id bigint NOT NULL,
    to_user_id bigint NOT NULL,
    disable_comments_notifications boolean NOT NULL DEFAULT false,
    PRIMARY KEY (user_id, to_user_id)
)