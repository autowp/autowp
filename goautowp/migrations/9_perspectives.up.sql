create table perspectives_pages
(
  id   int primary key,
  name varchar(100) not null
);

INSERT INTO perspectives_pages (id, name) VALUES (1, '');
INSERT INTO perspectives_pages (id, name) VALUES (2, '');
INSERT INTO perspectives_pages (id, name) VALUES (3, '');
INSERT INTO perspectives_pages (id, name) VALUES (4, '');
INSERT INTO perspectives_pages (id, name) VALUES (5, '');
INSERT INTO perspectives_pages (id, name) VALUES (6, '');
INSERT INTO perspectives_pages (id, name) VALUES (7, '');
INSERT INTO perspectives_pages (id, name) VALUES (8, '');


create table perspectives_groups
(
  id       int primary key,
  page_id  int not null,
  position int not null,
  name     varchar(50) null,
  constraint perspectives_groups_unique_page_id unique (page_id, position),
  constraint perspectives_groups_ibfk_1 foreign key (page_id) references perspectives_pages (id)
);

INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (1, 1, 1, 'Спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (2, 1, 2, 'Сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (3, 1, 3, 'Салон');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (4, 2, 1, 'спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (5, 2, 2, 'сбоку');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (6, 2, 3, 'сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (7, 2, 4, 'салон');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (8, 3, 1, 'спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (9, 3, 2, 'сбоку');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (10, 3, 3, 'сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (11, 3, 4, 'салон');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (12, 5, 1, 'спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (14, 5, 2, 'сбоку');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (15, 5, 4, 'под капотом, шильдик, снизу, cutaway');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (16, 5, 5, 'салон, интерьер');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (17, 5, 3, 'сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (18, 4, 1, 'спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (19, 4, 2, 'сбоку');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (20, 4, 3, 'сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (21, 4, 4, 'салон');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (22, 6, 1, 'спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (23, 6, 2, 'левый бок');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (24, 6, 3, 'сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (25, 6, 4, 'правый бок');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (26, 6, 5, 'под капотом, шильдик, снизу, cutaway');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (27, 6, 6, 'салон, интерьер');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (28, 7, 1, 'Спереди');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (29, 7, 2, 'Сбоку');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (30, 7, 3, 'Интерьер / Сзади');
INSERT INTO perspectives_groups (id, page_id, position, name) VALUES (31, 8, 1, 'Api');


create table perspectives
(
  id       int primary key,
  name     varchar(50) not null,
  position int not null unique
);

INSERT INTO perspectives (id, name, position) VALUES (1, 'perspective/front', 1);
INSERT INTO perspectives (id, name, position) VALUES (2, 'perspective/back', 9);
INSERT INTO perspectives (id, name, position) VALUES (3, 'perspective/left', 5);
INSERT INTO perspectives (id, name, position) VALUES (4, 'perspective/right', 7);
INSERT INTO perspectives (id, name, position) VALUES (5, 'perspective/interior', 14);
INSERT INTO perspectives (id, name, position) VALUES (6, 'perspective/front-panel', 11);
INSERT INTO perspectives (id, name, position) VALUES (7, 'perspective/3/4-left', 3);
INSERT INTO perspectives (id, name, position) VALUES (8, 'perspective/3/4-right', 4);
INSERT INTO perspectives (id, name, position) VALUES (9, 'perspective/cutaway', 21);
INSERT INTO perspectives (id, name, position) VALUES (10, 'perspective/front-strict', 2);
INSERT INTO perspectives (id, name, position) VALUES (11, 'perspective/left-strict', 6);
INSERT INTO perspectives (id, name, position) VALUES (12, 'perspective/right-strict', 8);
INSERT INTO perspectives (id, name, position) VALUES (13, 'perspective/back-strict', 10);
INSERT INTO perspectives (id, name, position) VALUES (14, 'perspective/n/a', 50);
INSERT INTO perspectives (id, name, position) VALUES (15, 'perspective/label', 17);
INSERT INTO perspectives (id, name, position) VALUES (16, 'perspective/upper', 19);
INSERT INTO perspectives (id, name, position) VALUES (17, 'perspective/under-the-hood', 16);
INSERT INTO perspectives (id, name, position) VALUES (18, 'perspective/upper-strict', 20);
INSERT INTO perspectives (id, name, position) VALUES (19, 'perspective/bottom', 18);
INSERT INTO perspectives (id, name, position) VALUES (20, 'perspective/dashboard', 12);
INSERT INTO perspectives (id, name, position) VALUES (21, 'perspective/boot', 15);
INSERT INTO perspectives (id, name, position) VALUES (22, 'perspective/logo', 22);
INSERT INTO perspectives (id, name, position) VALUES (23, 'perspective/mascot', 25);
INSERT INTO perspectives (id, name, position) VALUES (24, 'perspective/sketch', 26);
INSERT INTO perspectives (id, name, position) VALUES (25, 'perspective/mixed', 49);
INSERT INTO perspectives (id, name, position) VALUES (26, 'perspective/exterior-details', 27);
INSERT INTO perspectives (id, name, position) VALUES (27, 'perspective/mockup', 45);
INSERT INTO perspectives (id, name, position) VALUES (28, 'perspective/chassis', 30);


create table perspectives_groups_perspectives
(
  group_id       int not null,
  perspective_id int not null,
  position       int not null,
  primary key (perspective_id, group_id),
  constraint perspectives_groups_perspectives_unique_position unique (position, group_id),
  constraint FK_perspectives_groups_perspectives_perspectives_groups_id foreign key (group_id) references perspectives_groups (id),
  constraint FK_perspectives_groups_perspectives_perspectives_id foreign key (perspective_id) references perspectives (id)
);

create index perspectives_groups_perspectives_group_id_index on perspectives_groups_perspectives (group_id);

INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (1, 7, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (2, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (3, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (4, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (5, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (6, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (7, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (8, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (9, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (10, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (11, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (12, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (14, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (15, 17, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (16, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (17, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (18, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (19, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (20, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (21, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (22, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (23, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (24, 13, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (25, 12, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (26, 17, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (27, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (28, 10, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 11, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 6, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 7, 1);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (1, 8, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (2, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (3, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (4, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (5, 12, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (6, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (7, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (8, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (9, 12, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (10, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (11, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (12, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (14, 12, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (15, 15, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (16, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (17, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (18, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (19, 12, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (20, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (21, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (22, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (23, 3, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (24, 2, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (25, 4, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (26, 15, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (27, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (28, 1, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 12, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 20, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 8, 2);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (1, 1, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (2, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (3, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (4, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (5, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (7, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (8, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (9, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (11, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (12, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (14, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (15, 19, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (16, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (18, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (19, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (21, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (22, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (23, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (25, 8, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (26, 19, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (27, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (28, 7, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 3, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 5, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 1, 3);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (1, 10, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (2, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (3, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (4, 8, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (5, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (7, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (8, 8, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (9, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (11, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (12, 8, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (14, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (15, 9, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (16, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (19, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (21, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (22, 8, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (26, 9, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (27, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (28, 8, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 4, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 15, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 2, 4);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 13, 5);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 13, 5);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 3, 5);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (29, 2, 6);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (30, 2, 6);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 4, 6);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (18, 8, 7);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 11, 7);
INSERT INTO perspectives_groups_perspectives (group_id, perspective_id, position) VALUES (31, 12, 8);
