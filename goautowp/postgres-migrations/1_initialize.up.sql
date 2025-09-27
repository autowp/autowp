CREATE TABLE ip_monitoring (
  ip inet NOT NULL,
  day_date date NOT NULL,
  hour smallint NOT NULL,
  tenminute smallint NOT NULL,
  minute smallint NOT NULL,
  count int NOT NULL,
  PRIMARY KEY (ip,day_date,hour,tenminute,minute)
);

CREATE TABLE ip_ban (
  until timestamptz NOT NULL,
  by_user_id int DEFAULT NULL,
  reason varchar(255) NOT NULL,
  ip inet NOT NULL,
  PRIMARY KEY (ip));

CREATE INDEX ON ip_ban (until);
CREATE INDEX ON ip_ban (by_user_id);

CREATE TABLE ip_whitelist (
  description varchar(255) NOT NULL,
  ip inet NOT NULL,
  PRIMARY KEY (ip)
);
