CREATE TABLE `item_type` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `item_type` VALUES 
(1,'vehicle'),
(2,'engine'),
(3,'category'),
(4,'twins'),
(5,'brand'),
(6,'factory'),
(7,'museum'),
(8,'person'),
(9,'copyright');

CREATE TABLE `car_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `catname` varchar(20) NOT NULL,
  `name` varchar(35) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `name_rp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `car_types` VALUES 
(3,NULL,'cabriolet','car-type/cabriolet',3,'car-type-rp/cabriolet'),
(9,NULL,'crossover','car-type/crossover',16,'car-type-rp/crossover'),
(10,NULL,'universal','car-type/universal',14,'car-type-rp/universal'),
(11,NULL,'limousine','car-type/limousine',21,'car-type-rp/limousine'),
(12,NULL,'pickup','car-type/pickup',20,'car-type-rp/pickup'),
(14,NULL,'offroad','car-type/offroad',17,'car-type-rp/offroad'),
  (33,14,'offroad-short','car-type/offroad-short',77,'car-type-rp/offroad-short'),
(15,NULL,'minivan','car-type/minivan',22,'car-type-rp/minivan'),
(16,NULL,'van','car-type/van',23,'car-type-rp/van'),
(17,NULL,'truck','car-type/truck',24,'car-type-rp/truck'),
(19,NULL,'bus','car-type/bus',25,'car-type-rp/bus'),
  (28,19,'minibus','car-type/minibus',0,'car-type-rp/minibus'),
  (32,19,'multiplex-bus','car-type/multiplex-bus',5,'car-type-rp/multiplex-bus'),
  (39,19,'2-floor-bus','car-type/2-floor-bus',6,'car-type-rp/2-floor-bus'),
(29,NULL,'car','car-type/car',1,'car-type-rp/car'),
  (1,29,'roadster','car-type/roadster',1,'car-type-rp/roadster'),
  (2,29,'spyder','car-type/spyder',2,'car-type-rp/spyder'),
  (4,29,'cabrio-coupe','car-type/cabrio-coupe',4,'car-type-rp/cabrio-coupe'),
  (5,29,'targa','car-type/targa',5,'car-type-rp/targa'),
  (6,29,'coupe','car-type/coupe',8,'car-type-rp/coupe'),
    (25,6,'liftback-coupe','car-type/liftback-coupe',26,'car-type-rp/liftback-coupe'),
    (27,6,'2door-hardtop','car-type/2door-hardtop',11,'car-type-rp/2door-hardtop'),
    (37,6,'fastback-coupe','car-type/fastback-coupe',49,'car-type-rp/fastback-coupe'),
  (7,29,'sedan','car-type/sedan',9,'car-type-rp/sedan'),
    (21,7,'4door-hardtop','car-type/4door-hardtop',10,'car-type-rp/4door-hardtop'),
    (26,7,'liftback-sedan','car-type/liftback-sedan',27,'car-type-rp/liftback-sedan'),
    (36,7,'fastback-sedan','car-type/fastback-sedan',50,'car-type-rp/fastback-sedan'),
  (8,29,'hatchback','car-type/hatchback',13,'car-type-rp/hatchback'),
  (20,29,'phaeton','car-type/phaeton',7,'car-type-rp/phaeton'),
  (22,29,'landau','car-type/landau',6,'car-type-rp/landau'),
  (13,29,'caravan','car-type/caravan',15,'car-type-rp/caravan'),
  (34,29,'brougham','car-type/brougham',22,'car-type-rp/brougham'),
  (38,29,'tonneau','car-type/tonneau',43,'car-type-rp/tonneau'),
  (40,29,'town-car','car-type/town-car',70,'car-type-rp/town-car'),
  (41,29,'barchetta','car-type/barchetta',99,'car-type-rp/barchetta'),
(43,NULL,'moto','car-type/moto',100,'car-type-rp/moto'),
(44,NULL,'tractor','car-type/tractor',101,'car-type-rp/tractor'),
(45,NULL,'tracked','car-type/tracked',102,'car-type-rp/tracked');

CREATE TABLE `car_types_parents` (
  `id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `car_types_parents_ibfk_1` FOREIGN KEY (`id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `car_types_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `car_types_parents` VALUES (1,1,1),(1,29,0),(2,2,1),(2,29,0),(3,3,0),(4,4,1),(4,29,0),(5,5,1),
(5,29,0),(6,6,1),(6,29,0),(7,7,1),(7,29,0),(8,8,1),(8,29,0),(9,9,0),(10,10,0),(11,11,0),(12,12,0),(13,13,1),
(13,29,0),(14,14,0),(15,15,0),(16,16,0),(17,17,0),(19,19,0),(20,20,1),(20,29,0),(21,7,1),(21,21,2),(21,29,0),
(22,22,1),(22,29,0),(25,6,1),(25,25,2),(25,29,0),(26,7,1),(26,26,2),(26,29,0),(27,6,1),(27,27,2),(27,29,0),
(28,19,0),(28,28,1),(29,29,0),(32,19,0),(32,32,1),(33,14,0),(33,33,1),(34,29,0),(34,34,1),(36,7,1),(37,6,1),
(38,29,0),(38,38,1),(39,19,0),(39,39,1),(40,29,0),(40,40,1),(41,29,0),(41,41,1),(43,43,0),(44,44,0),(45,45,0);

CREATE TABLE `item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `begin_year` smallint(5) unsigned DEFAULT NULL,
  `end_year` smallint(5) unsigned DEFAULT NULL,
  `body` varchar(20) NOT NULL,
  `spec_id` int(10) unsigned DEFAULT NULL,
  `spec_inherit` tinyint(1) NOT NULL DEFAULT '1',
  `produced` int(10) unsigned DEFAULT NULL,
  `produced_exactly` tinyint(3) unsigned NOT NULL,
  `is_concept` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `car_type_id` int(10) unsigned DEFAULT NULL,
  `today` tinyint(3) unsigned DEFAULT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL COMMENT 'Дата создания записи',
  `begin_month` tinyint(3) unsigned DEFAULT NULL,
  `end_month` tinyint(3) unsigned DEFAULT NULL,
  `begin_order_cache` date DEFAULT NULL,
  `end_order_cache` date DEFAULT NULL,
  `begin_model_year` smallint(5) unsigned DEFAULT NULL,
  `end_model_year` smallint(5) DEFAULT NULL,
  `is_group` tinyint(4) NOT NULL DEFAULT '0',
  `car_type_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `is_concept_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `engine_inherit` tinyint(4) NOT NULL DEFAULT '1',
  `item_type_id` int(11) NOT NULL DEFAULT '1',
  `engine_item_id` int(10) unsigned DEFAULT NULL,
  `catname` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `position` tinyint(4) NOT NULL DEFAULT '0',
  `logo_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caption` (`name`,`begin_year`,`body`,`end_year`,`begin_model_year`,`end_model_year`,`is_group`),
  UNIQUE KEY `catname` (`catname`,`item_type_id`) USING BTREE,
  KEY `fullCaptionOrder` (`name`,`body`,`begin_year`,`end_year`),
  KEY `car_type_id` (`car_type_id`),
  KEY `primary_and_sorting` (`id`,`begin_order_cache`),
  KEY `spec_id` (`spec_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `engine_item_id` (`engine_item_id`),
  KEY `logo_id` (`logo_id`),
  CONSTRAINT `item_ibfk_3` FOREIGN KEY (`spec_id`) REFERENCES `spec` (`id`),
  CONSTRAINT `item_ibfk_6` FOREIGN KEY (`engine_item_id`) REFERENCES `item` (`id`),
  CONSTRAINT `item_ibfk_7` FOREIGN KEY (`item_type_id`) REFERENCES `item_type` (`id`),
  CONSTRAINT `item_ibfk_8` FOREIGN KEY (`logo_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `brand_alias` (
  `name` varchar(255) NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`name`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `FK_brand_alias_brands_id` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_language` (
  `item_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  `full_text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`item_id`,`language`),
  KEY `language` (`language`),
  KEY `text_id` (`text_id`),
  KEY `full_text_id` (`full_text_id`),
  CONSTRAINT `item_language_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_language_ibfk_2` FOREIGN KEY (`language`) REFERENCES `language` (`code`),
  CONSTRAINT `item_language_ibfk_3` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  CONSTRAINT `item_language_ibfk_4` FOREIGN KEY (`full_text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_parent` (
  `item_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `catname` varchar(150) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT NULL,
  `~name` varchar(100) DEFAULT NULL,
  `manual_catname` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`,`parent_id`),
  UNIQUE KEY `unique_catname` (`parent_id`,`catname`),
  CONSTRAINT `item_parent_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`),
  CONSTRAINT `item_parent_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_parent_cache` (
  `item_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `diff` int(11) NOT NULL DEFAULT '0',
  `tuning` tinyint(4) NOT NULL DEFAULT '0',
  `sport` tinyint(4) NOT NULL DEFAULT '0',
  `design` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `item_parent_cache_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_parent_cache_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_parent_language` (
  `item_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_auto` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`,`parent_id`,`language`),
  KEY `parent_id` (`parent_id`),
  KEY `language` (`language`),
  CONSTRAINT `item_parent_language_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`),
  CONSTRAINT `item_parent_language_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `item` (`id`),
  CONSTRAINT `item_parent_language_ibfk_3` FOREIGN KEY (`language`) REFERENCES `language` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('default','official','helper','club') NOT NULL DEFAULT 'default' COMMENT 'Òèï',
  `url` varchar(150) NOT NULL COMMENT 'àäðåñ',
  `name` varchar(250) NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `links_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `vehicle_vehicle_type` (
  `vehicle_id` int(10) unsigned NOT NULL,
  `vehicle_type_id` int(10) unsigned NOT NULL,
  `inherited` tinyint(1) NOT NULL,
  PRIMARY KEY (`vehicle_id`,`vehicle_type_id`),
  KEY `vehicle_type_id` (`vehicle_type_id`),
  CONSTRAINT `vehicle_vehicle_type_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_vehicle_type_ibfk_2` FOREIGN KEY (`vehicle_type_id`) REFERENCES `car_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `picture_item` (
  `picture_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `type` int(11) NOT NULL DEFAULT '1',
  `perspective_id` int(10) unsigned DEFAULT NULL,
  `crop_left` smallint(5) unsigned DEFAULT NULL,
  `crop_top` smallint(5) unsigned DEFAULT NULL,
  `crop_width` smallint(5) unsigned DEFAULT NULL,
  `crop_height` smallint(5) unsigned DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`picture_id`,`item_id`,`type`),
  KEY `item_id` (`item_id`),
  KEY `perspective_id` (`perspective_id`),
  CONSTRAINT `picture_item_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `picture_item_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`),
  CONSTRAINT `picture_item_ibfk_3` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_item_subscribe` (
  `user_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`item_id`),
  KEY `car_id_index` (`item_id`),
  CONSTRAINT `user_item_subscribe_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_item_subscribe_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_point` (
  `item_id` int(10) unsigned NOT NULL,
  `point` point NOT NULL,
  PRIMARY KEY (`item_id`),
  SPATIAL KEY `point` (`point`),
  CONSTRAINT `item_point_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `of_day` (
  `day_date` date NOT NULL,
  `picture_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `twitter_sent` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `facebook_sent` tinyint(4) NOT NULL DEFAULT '0',
  `vk_sent` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`day_date`),
  KEY `of_day_fk` (`picture_id`),
  KEY `FK_of_day_cars_id` (`item_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `FK_of_day_cars_id` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `of_day_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `of_day_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;