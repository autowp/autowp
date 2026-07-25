CREATE TABLE achievement (
  id    smallint PRIMARY KEY,
  code  varchar(64) NOT NULL,
  label varchar(128) NOT NULL,
  CONSTRAINT achievement_code_unique UNIQUE (code)
);

INSERT INTO achievement (id, code, label) VALUES
  (1, 'pictures-contributor', 'Pictures contributor'),
  (2, 'top-pictures-contributor', 'Top pictures contributor'),
  (3, 'inspector-rookie', 'Rookie Inspector'),
  (4, 'inspector-practicing', 'Practicing Inspector'),
  (5, 'inspector-regular', 'Regular Inspector'),
  (6, 'inspector-expert', 'Expert Inspector'),
  (7, 'inspector-god', 'God of Inspectors'),
  (8, 'picture-buster-rookie', 'Rookie Picture Buster'),
  (9, 'picture-buster-practicing', 'Practicing Picture Buster'),
  (10, 'picture-buster-regular', 'Regular Picture Buster'),
  (11, 'picture-buster-expert', 'Expert Picture Buster'),
  (12, 'picture-buster-god', 'God of Picture Busters'),
  (13, 'spec-master-rookie', 'Rookie Spec Master'),
  (14, 'spec-master-practicing', 'Practicing Spec Master'),
  (15, 'spec-master-regular', 'Regular Spec Master'),
  (16, 'spec-master-expert', 'Expert Spec Master'),
  (17, 'spec-master-god', 'Spec Master God'),
  (18, 'commentator-rookie', 'Rookie Commentator'),
  (19, 'commentator-practicing', 'Practicing Commentator'),
  (20, 'commentator-regular', 'Regular Commentator'),
  (21, 'commentator-expert', 'Expert Commentator'),
  (22, 'commentator-god', 'Commentator God'),
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
