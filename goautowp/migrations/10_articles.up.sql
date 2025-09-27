CREATE TABLE `htmls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `html` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `html_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `catname` varchar(100) NOT NULL,
  `last_editor_id` int(10) unsigned DEFAULT NULL,
  `last_edit_date` timestamp NULL DEFAULT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author_id` int(10) unsigned DEFAULT NULL,
  `enabled` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `first_enabled_datetime` timestamp NULL DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `preview_width` tinyint(3) unsigned DEFAULT NULL,
  `preview_height` tinyint(3) unsigned DEFAULT NULL,
  `preview_filename` varchar(50) DEFAULT NULL,
  `ratio` float unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  KEY `html_id` (`html_id`),
  KEY `last_editor_id` (`last_editor_id`),
  KEY `author_id` (`author_id`),
  KEY `first_enabled_datetime` (`first_enabled_datetime`),
  CONSTRAINT `articles_fk` FOREIGN KEY (`last_editor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `articles_fk1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `articles_fk2` FOREIGN KEY (`html_id`) REFERENCES `htmls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;