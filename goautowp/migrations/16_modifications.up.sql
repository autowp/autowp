CREATE TABLE `modification_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `modification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `begin_year` smallint(5) unsigned DEFAULT NULL,
  `end_year` smallint(5) unsigned DEFAULT NULL,
  `begin_month` tinyint(3) unsigned DEFAULT NULL,
  `end_month` tinyint(3) unsigned DEFAULT NULL,
  `begin_model_year` smallint(5) unsigned DEFAULT NULL,
  `end_model_year` smallint(5) unsigned DEFAULT NULL,
  `today` tinyint(4) DEFAULT NULL,
  `produced` int(11) DEFAULT NULL,
  `produced_exactly` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `car_id` (`item_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `modification_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`),
  CONSTRAINT `modification_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `modification_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `modification_picture` (
  `modification_id` int(11) NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`modification_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `modification_picture_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modification_picture_ibfk_2` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `modification_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modification_id` int(11) NOT NULL,
  `value` varchar(100) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value` (`value`,`modification_id`),
  KEY `modification_id` (`modification_id`),
  CONSTRAINT `modification_value_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;