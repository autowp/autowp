-- Inspector: count of accept events performed by each moderator (log_event.user_id).
-- COUNT(DISTINCT log_event.id), not COUNT(*): defensive against a log_event ever being
-- joined to more than one picture.
-- Note: a WITH clause scopes to a single statement, so the CTE is repeated for each of
-- the two INSERTs that need it (the earned-badge rows and the progress-counter seed).
WITH moderator_accept_counts AS (
  SELECT log_event.user_id AS moderator_id, COUNT(DISTINCT log_event.id) AS accepted_count
  FROM log_event
  JOIN log_event_picture ON log_event_picture.log_event_id = log_event.id
  WHERE log_event.description LIKE 'Картинка `%` принята'
  GROUP BY log_event.user_id
)
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT moderator_id, tier.id, now()
FROM moderator_accept_counts
JOIN (VALUES (3, 100), (4, 1000), (5, 10000), (6, 100000), (7, 1000000)) AS tier(id, threshold)
  ON moderator_accept_counts.accepted_count >= tier.threshold
ON CONFLICT DO NOTHING;

WITH moderator_accept_counts AS (
  SELECT log_event.user_id AS moderator_id, COUNT(DISTINCT log_event.id) AS accepted_count
  FROM log_event
  JOIN log_event_picture ON log_event_picture.log_event_id = log_event.id
  WHERE log_event.description LIKE 'Картинка `%` принята'
  GROUP BY log_event.user_id
)
INSERT INTO user_achievement_progress (user_id, metric, count, updated_at)
SELECT moderator_id, 'inspector', accepted_count, now()
FROM moderator_accept_counts
ON CONFLICT (user_id, metric) DO UPDATE SET count = EXCLUDED.count, updated_at = now();

-- Picture Buster: count of direct queue-for-removal events performed by each moderator.
-- Does NOT include the incidental QueueRemove call inside AcceptReplacePicture, which
-- logs different text ("Замена %d на %d") and isn't distinguishable in the log. Live
-- counting going forward does include that path, so this seeded counter is a slight
-- (accepted) undercount relative to how the counter grows from here on.
WITH moderator_queue_counts AS (
  SELECT log_event.user_id AS moderator_id, COUNT(DISTINCT log_event.id) AS queued_count
  FROM log_event
  JOIN log_event_picture ON log_event_picture.log_event_id = log_event.id
  WHERE log_event.description LIKE 'Картинка `%` поставлена в очередь на удаление'
  GROUP BY log_event.user_id
)
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT moderator_id, tier.id, now()
FROM moderator_queue_counts
JOIN (VALUES (8, 100), (9, 1000), (10, 10000), (11, 100000), (12, 1000000)) AS tier(id, threshold)
  ON moderator_queue_counts.queued_count >= tier.threshold
ON CONFLICT DO NOTHING;

WITH moderator_queue_counts AS (
  SELECT log_event.user_id AS moderator_id, COUNT(DISTINCT log_event.id) AS queued_count
  FROM log_event
  JOIN log_event_picture ON log_event_picture.log_event_id = log_event.id
  WHERE log_event.description LIKE 'Картинка `%` поставлена в очередь на удаление'
  GROUP BY log_event.user_id
)
INSERT INTO user_achievement_progress (user_id, metric, count, updated_at)
SELECT moderator_id, 'picture-buster', queued_count, now()
FROM moderator_queue_counts
ON CONFLICT (user_id, metric) DO UPDATE SET count = EXCLUDED.count, updated_at = now();

-- Spec Master: exact count of attrs_user_values rows per user — ground truth, no
-- approximation needed.
WITH spec_value_counts AS (
  SELECT user_id, COUNT(*) AS value_count
  FROM attrs_user_values
  GROUP BY user_id
)
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT user_id, tier.id, now()
FROM spec_value_counts
JOIN (VALUES (13, 100), (14, 1000), (15, 10000), (16, 100000), (17, 1000000)) AS tier(id, threshold)
  ON spec_value_counts.value_count >= tier.threshold
ON CONFLICT DO NOTHING;

WITH spec_value_counts AS (
  SELECT user_id, COUNT(*) AS value_count
  FROM attrs_user_values
  GROUP BY user_id
)
INSERT INTO user_achievement_progress (user_id, metric, count, updated_at)
SELECT user_id, 'spec-master', value_count, now()
FROM spec_value_counts
ON CONFLICT (user_id, metric) DO UPDATE SET count = EXCLUDED.count, updated_at = now();

-- Commentator: exact count of non-deleted comment_message rows per author — ground
-- truth, same reasoning as Spec Master above.
WITH comment_counts AS (
  SELECT author_id, COUNT(*) AS comment_count
  FROM comment_message
  WHERE deleted = false
  GROUP BY author_id
)
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT author_id, tier.id, now()
FROM comment_counts
JOIN (VALUES (18, 100), (19, 1000), (20, 10000), (21, 100000), (22, 1000000)) AS tier(id, threshold)
  ON comment_counts.comment_count >= tier.threshold
ON CONFLICT DO NOTHING;

WITH comment_counts AS (
  SELECT author_id, COUNT(*) AS comment_count
  FROM comment_message
  WHERE deleted = false
  GROUP BY author_id
)
INSERT INTO user_achievement_progress (user_id, metric, count, updated_at)
SELECT author_id, 'commentator', comment_count, now()
FROM comment_counts
ON CONFLICT (user_id, metric) DO UPDATE SET count = EXCLUDED.count, updated_at = now();

-- Veteran: exact reg_date check — no progress counter needed (date-based, not tiered).
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT id, 23, now()
FROM users
WHERE deleted = false
  AND reg_date IS NOT NULL
  AND reg_date <= now() - INTERVAL '10 years'
ON CONFLICT DO NOTHING;

-- Top pictures contributor: current top 10 by accepted-picture count (mirrors the
-- ongoing daily job's own query, applied once at migration time). No progress counter —
-- user.pictures_total (already persisted) is itself the relevant metric for any future
-- leaderboard here, nothing new to seed.
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT id, 2, now()
FROM users
WHERE deleted = false
ORDER BY pictures_total DESC
LIMIT 10
ON CONFLICT DO NOTHING;
