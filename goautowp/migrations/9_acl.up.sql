CREATE TABLE `acl_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_resources` 
VALUES (12,'attrs'),
(1,'brand'),
(4,'car'),
(6,'comment'),
(21,'factory'),
(10,'forums'),
(15,'hotlinks'),
(2,'model'),
(19,'museums'),
(7,'page'),
(5,'picture'),
(9,'rights'),
(17,'specifications'),
(18,'status'),
(11,'twins'),
(13,'user'),
(20,'website');

CREATE TABLE `acl_resources_privileges` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`resource_id`,`name`),
  CONSTRAINT `acl_resources_privileges_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `acl_resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_resources_privileges` 
VALUES (15,1,'add'),
(32,1,'edit'),
(37,1,'logo'),
(33,2,'add'),
(34,2,'delete'),
(35,2,'edit'),
(14,4,'add'),
(1,4,'edit_meta'),
(24,4,'move'),
(21,5,'accept'),
(9,5,'add'),
(43,5,'crop'),
(30,5,'decrease-resolution'),
(31,5,'flop'),
(19,5,'moder_vote'),
(17,5,'move'),
(23,5,'normalize'),
(36,5,'remove'),
(20,5,'remove_by_vote'),
(86,5,'restore'),
(22,5,'unaccept'),
(3,6,'add'),
(91,6,'moderator-attention'),
(5,6,'remove'),
(4,6,'remove-own'),
(7,7,'add'),
(6,7,'edit'),
(8,7,'remove'),
(13,9,'edit'),
(29,10,'moderate'),
(38,11,'edit'),
(39,12,'edit'),
(41,13,'ban'),
(79,13,'delete'),
(42,13,'ip'),
(81,15,'manage'),
(80,15,'view'),
(87,17,'admin'),
(82,17,'edit'),
(88,17,'edit-engine'),
(83,18,'be-green'),
(84,19,'manage'),
(85,20,'unlimited-traffic'),
(89,21,'add'),
(90,21,'edit');


CREATE TABLE `acl_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_roles` 
VALUES (1,'abstract-user'),
(5,'admin'),
(6,'user'),
(7,'guest'),
(8,'comments-writer'),
(9,'pictures-moder'),
(10,'cars-moder'),
(11,'brands-moder'),
(12,'articles-moder'),
(13,'pages-moder'),
(14,'moder'),
(16,'forums-moder'),
(17,'models-moder'),
(49,'green-user'),
(50,'museum-moder'),
(58,'factory-moder');

CREATE TABLE `acl_roles_parents` (
  `role_id` int(10) unsigned NOT NULL,
  `parent_role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`parent_role_id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `acl_roles_parents_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_parents_ibfk_2` FOREIGN KEY (`parent_role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_roles_parents` 
VALUES (6,1),(7,1),(5,6),(49,6),(6,8),(5,9),(5,10),(5,11),(5,12),(5,13),(9,14),(10,14),(11,14),(12,14),(13,14),(16,14),(17,14),(50,14),(58,14),
(5,16),(5,17),(5,49),(14,49),(5,58);

CREATE TABLE `acl_roles_privileges_allowed` (
  `role_id` int(10) unsigned NOT NULL,
  `privilege_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `acl_roles_privileges_allowed_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_privileges_allowed_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_roles_privileges_allowed` 
VALUES (5,1),(10,1),(8,3),(5,4),(5,5),(13,6),(13,7),(13,8),(5,13),(10,14),
(11,15),(9,17),(9,19),(9,20),(9,21),(5,22),(9,22),(5,23),(9,23),(10,24),(16,29),(5,30),
(5,31),(11,32),(5,33),(17,33),(17,34),(17,35),(5,37),
(10,38),(5,39),(5,41),(5,42),(14,42),(9,43),(5,79),(5,80),(5,81),(6,82),
(49,83),(14,85),(5,86),(5,87),(5,88),(10,88),(58,89),(58,90),(6,91);

CREATE TABLE `acl_roles_privileges_denied` (
  `role_id` int(10) unsigned NOT NULL,
  `privilege_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `acl_roles_privileges_denied_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_privileges_denied_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

