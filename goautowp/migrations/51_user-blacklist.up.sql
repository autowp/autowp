-- Per-pair "blacklist" flag: when user A blacklists user B, B can no longer send A personal
-- messages, and A stops receiving reply-comment notifications about B (blacklisting implies the
-- existing disable_comments_notifications intent). Stored alongside the notification preference
-- because it is the same (user_id -> to_user_id) relation.
ALTER TABLE user_user_preferences ADD COLUMN blacklist boolean NOT NULL DEFAULT false;
