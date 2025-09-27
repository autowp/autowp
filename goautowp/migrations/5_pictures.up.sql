CREATE TABLE `pictures_types` (
  `id` tinyint(3) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `pictures_types` 
VALUES (1,'Автомобиль'),
(4,'Двигатель'),
(7,'Завод'),
(6,'Интерьер'),
(2,'Логотип бренда'),
(5,'Модель'),
(0,'Несортировано'),
(3,'Разное');

CREATE TABLE `pictures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `width` smallint(5) unsigned NOT NULL DEFAULT '0',
  `height` smallint(5) unsigned NOT NULL DEFAULT '0',
  `filesize` int(8) unsigned NOT NULL DEFAULT '0',
  `owner_id` int(10) unsigned DEFAULT '0',
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('new','accepted','removing','removed','inbox') NOT NULL,
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `removing_date` date DEFAULT NULL,
  `change_status_user_id` int(10) unsigned DEFAULT NULL,
  `accept_datetime` timestamp NULL DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `identity` varchar(10) NOT NULL,
  `replace_picture_id` int(10) unsigned DEFAULT NULL,
  `image_id` int(10) unsigned DEFAULT NULL,
  `ip` varbinary(16) NOT NULL,
  `copyrights_text_id` int(11) DEFAULT NULL,
  `point` point DEFAULT NULL,
  `dpi_x` int(11) DEFAULT NULL,
  `dpi_y` int(11) DEFAULT NULL,
  `content_count` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity` (`identity`),
  UNIQUE KEY `image_id` (`image_id`),
  KEY `dateAndIdOrdering` (`status`,`add_date`,`id`),
  KEY `car_id` (`type`,`status`),
  KEY `owner_id` (`owner_id`,`status`),
  KEY `accept_datetime` (`status`,`accept_datetime`),
  KEY `pictures_fk5` (`type`),
  KEY `pictures_fk6` (`replace_picture_id`),
  KEY `width` (`width`,`height`,`add_date`,`id`),
  KEY `copyrights_text_id` (`copyrights_text_id`),
  KEY `id` (`id`,`status`,`type`) USING BTREE,
  CONSTRAINT `pictures_fk` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pictures_fk5` FOREIGN KEY (`type`) REFERENCES `pictures_types` (`id`),
  CONSTRAINT `pictures_fk6` FOREIGN KEY (`replace_picture_id`) REFERENCES `pictures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pictures_fk7` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `pictures_ibfk_2` FOREIGN KEY (`copyrights_text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `picture_view` (
  `picture_id` int(10) unsigned NOT NULL,
  `views` int(10) unsigned NOT NULL,
  PRIMARY KEY (`picture_id`),
  CONSTRAINT `picture_view_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pictures_moder_votes` (
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `picture_id` int(10) unsigned NOT NULL DEFAULT '0',
  `day_date` timestamp NOT NULL,
  `reason` varchar(80) NOT NULL,
  `vote` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `picture_id_ref` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pictures_moder_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `picture_moder_vote_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `reason` varchar(80) NOT NULL,
  `vote` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;