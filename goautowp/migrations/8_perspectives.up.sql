CREATE TABLE `perspectives_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `perspectives_pages` VALUES (1,''),(2,''),(3,''),(4,''),(5,''),(6,''),(7,''),(8,'');

CREATE TABLE `perspectives` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_2` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `perspectives` 
VALUES (1,'perspective/front',1),
(2,'perspective/back',9),
(3,'perspective/left',5),
(4,'perspective/right',7),
(5,'perspective/interior',14),
(6,'perspective/front-panel',11),
(7,'perspective/3/4-left',3),
(8,'perspective/3/4-right',4),
(9,'perspective/cutaway',21),
(10,'perspective/front-strict',2),
(11,'perspective/left-strict',6),
(12,'perspective/right-strict',8),
(13,'perspective/back-strict',10),
(14,'perspective/n/a',50),
(15,'perspective/label',17),
(16,'perspective/upper',19),
(17,'perspective/under-the-hood',16),
(18,'perspective/upper-strict',20),
(19,'perspective/bottom',18),
(20,'perspective/dashboard',12),
(21,'perspective/boot',15),
(22,'perspective/logo',22),
(23,'perspective/mascot',25),
(24,'perspective/sketch',26),
(25,'perspective/mixed',49),
(26,'perspective/exterior-details',27);

CREATE TABLE `perspectives_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `position` tinyint(11) unsigned NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_id` (`page_id`,`position`),
  CONSTRAINT `perspectives_groups_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `perspectives_pages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `perspectives_groups` VALUES 
(1,1,1,'Спереди'),
(2,1,2,'Сзади'),
(3,1,3,'Салон'),
(4,2,1,'спереди'),
(5,2,2,'сбоку'),
(6,2,3,'сзади'),
(7,2,4,'салон'),
(8,3,1,'спереди'),
(9,3,2,'сбоку'),
(10,3,3,'сзади'),
(11,3,4,'салон'),
(12,5,1,'спереди'),
(14,5,2,'сбоку'),
(15,5,4,'под капотом, шильдик, снизу, cutaway'),
(16,5,5,'салон, интерьер'),
(17,5,3,'сзади'),
(18,4,1,'спереди'),
(19,4,2,'сбоку'),
(20,4,3,'сзади'),
(21,4,4,'салон'),
(22,6,1,'спереди'),
(23,6,2,'левый бок'),
(24,6,3,'сзади'),
(25,6,4,'правый бок'),
(26,6,5,'под капотом, шильдик, снизу, cutaway'),
(27,6,6,'салон, интерьер'),
(28,7,1,'Спереди'),
(29,7,2,'Сбоку'),
(30,7,3,'Интерьер / Сзади'),
(31,8,1,'Api');

CREATE TABLE `perspectives_groups_perspectives` (
  `group_id` int(11) unsigned NOT NULL,
  `perspective_id` int(11) unsigned NOT NULL,
  `position` int(11) unsigned NOT NULL,
  PRIMARY KEY (`perspective_id`,`group_id`),
  UNIQUE KEY `position` (`position`,`group_id`),
  KEY `FK_perspectives_groups_perspectives_perspectives_groups_id` (`group_id`),
  CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_groups_id` FOREIGN KEY (`group_id`) REFERENCES `perspectives_groups` (`id`),
  CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_id` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `perspectives_groups_perspectives` VALUES 
(1,7,1),(2,13,1),(3,6,1),(4,10,1),(5,11,1),(6,13,1),(7,6,1),(8,10,1),(9,11,1),(10,13,1),(11,6,1),(12,10,1),(14,11,1),(15,17,1),
(16,6,1),(17,13,1),(18,10,1),(19,11,1),(20,13,1),(21,6,1),(22,10,1),(23,11,1),(24,13,1),(25,12,1),(26,17,1),(27,6,1),(28,10,1),
(29,11,1),(30,6,1),(31,7,1),(1,8,2),(2,2,2),(3,20,2),(4,1,2),(5,12,2),(6,2,2),(7,20,2),(8,1,2),(9,12,2),(10,2,2),(11,20,2),
(12,1,2),(14,12,2),(15,15,2),(16,20,2),(17,2,2),(18,1,2),(19,12,2),(20,2,2),(21,20,2),(22,1,2),(23,3,2),(24,2,2),(25,4,2),
(26,15,2),(27,20,2),(28,1,2),(29,12,2),(30,20,2),(31,8,2),(1,1,3),(2,3,3),(3,5,3),(4,7,3),(5,3,3),(7,5,3),(8,7,3),(9,3,3),
(11,5,3),(12,7,3),(14,3,3),(15,19,3),(16,5,3),(18,7,3),(19,3,3),(21,5,3),(22,7,3),(23,7,3),(25,8,3),(26,19,3),(27,5,3),
(28,7,3),(29,3,3),(30,5,3),(31,1,3),(1,10,4),(2,4,4),(3,15,4),(4,8,4),(5,4,4),(7,15,4),(8,8,4),(9,4,4),(11,15,4),(12,8,4),
(14,4,4),(15,9,4),(16,15,4),(19,4,4),(21,15,4),(22,8,4),(26,9,4),(27,15,4),(28,8,4),(29,4,4),(30,15,4),(31,2,4),(29,13,5),
(30,13,5),(31,3,5),(29,2,6),(30,2,6),(31,4,6),(18,8,7),(31,11,7),(31,12,8);