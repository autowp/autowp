CREATE TABLE achievement (
  id    smallint PRIMARY KEY,
  code  varchar(64) NOT NULL,
  label varchar(128) NOT NULL,
  CONSTRAINT achievement_code_unique UNIQUE (code)
);

INSERT INTO achievement (id, code, label) VALUES
  (1, 'pictures-contributor', 'Pictures contributor'),
  (2, 'top-pictures-contributor', 'Top pictures contributor'),
  (3, 'picture-inspector-bronze', 'Bronze Picture Inspector'),
  (4, 'picture-inspector-silver', 'Silver Picture Inspector'),
  (5, 'picture-inspector-gold', 'Gold Picture Inspector'),
  (6, 'picture-inspector-platinum', 'Platinum Picture Inspector'),
  (7, 'picture-inspector-diamond', 'Diamond Picture Inspector'),
  (8, 'picture-buster-bronze', 'Bronze Picture Buster'),
  (9, 'picture-buster-silver', 'Silver Picture Buster'),
  (10, 'picture-buster-gold', 'Gold Picture Buster'),
  (11, 'picture-buster-platinum', 'Platinum Picture Buster'),
  (12, 'picture-buster-diamond', 'Diamond Picture Buster'),
  (13, 'spec-master-bronze', 'Bronze Spec Master'),
  (14, 'spec-master-silver', 'Silver Spec Master'),
  (15, 'spec-master-gold', 'Gold Spec Master'),
  (16, 'spec-master-platinum', 'Platinum Spec Master'),
  (17, 'spec-master-diamond', 'Diamond Spec Master'),
  (18, 'commentator-bronze', 'Bronze Commentator'),
  (19, 'commentator-silver', 'Silver Commentator'),
  (20, 'commentator-gold', 'Gold Commentator'),
  (21, 'commentator-platinum', 'Platinum Commentator'),
  (22, 'commentator-diamond', 'Diamond Commentator'),
  (23, 'veteran', 'Veteran');

CREATE TABLE user_achievement (
  user_id        int NOT NULL REFERENCES users (id),
  achievement_id smallint NOT NULL REFERENCES achievement (id),
  created_at     timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (user_id, achievement_id)
);

CREATE TABLE user_achievement_progress (
  user_id    int NOT NULL REFERENCES users (id),
  metric     varchar(32) NOT NULL,
  count      bigint NOT NULL DEFAULT 0,
  updated_at timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (user_id, metric)
);
CREATE INDEX ON user_achievement_progress (metric, count DESC);
