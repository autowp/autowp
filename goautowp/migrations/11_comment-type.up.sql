create table comment_type (
  id   int primary key,
  name varchar(50) not null unique
);

INSERT INTO comment_type (id, name) VALUES (2, 'К группам близнецов и музеям');
INSERT INTO comment_type (id, name) VALUES (1, 'К картинкам');
INSERT INTO comment_type (id, name) VALUES (3, 'К опросам');
INSERT INTO comment_type (id, name) VALUES (4, 'К статьям');
INSERT INTO comment_type (id, name) VALUES (5, 'Форум');
