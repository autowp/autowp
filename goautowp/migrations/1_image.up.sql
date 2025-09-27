CREATE TABLE `image_dir` (
  `dir` varchar(255) NOT NULL,
  `count` int(10) unsigned NOT NULL,
  PRIMARY KEY (`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `image_dir` VALUES ('brand',0),('format',0),('museum',0),('picture',0),('user',0);

CREATE TABLE `image` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filepath` varchar(255) NOT NULL,
  `filesize` int(10) unsigned NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dir` varchar(255) NOT NULL,
  `crop_left` smallint(5) unsigned NOT NULL DEFAULT '0',
  `crop_top` smallint(5) unsigned NOT NULL DEFAULT '0',
  `crop_width` smallint(5) unsigned NOT NULL DEFAULT '0',
  `crop_height` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filepath`,`dir`),
  KEY `image_dir_id` (`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `formated_image` (
  `image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(255) NOT NULL,
  `formated_image_id` int(10) unsigned DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`,`format`),
  KEY `formated_image_id` (`formated_image_id`,`image_id`) USING BTREE,
  CONSTRAINT `formated_image_ibfk_1` FOREIGN KEY (`formated_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;