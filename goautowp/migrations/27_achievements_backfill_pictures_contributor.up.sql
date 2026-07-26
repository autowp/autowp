-- Pictures contributor: any user who owns at least one accepted picture — mirrors
-- achievements.Repository.GrantPictureAccepted, which grants this to picture.owner_id
-- on every first-time acceptance (idempotent, so "owns >= 1 accepted picture" is
-- equivalent to "was granted at least once"). Split into its own migration since
-- migration 26 was already applied before this backfill was added.
INSERT INTO user_achievement (user_id, achievement_id, created_at)
SELECT DISTINCT owner_id, 1, now()
FROM picture
WHERE owner_id IS NOT NULL
  AND status = 'accepted'
ON CONFLICT DO NOTHING;
