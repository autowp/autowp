CREATE TABLE `log_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `add_datetime` timestamp NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `add_datetime` (`add_datetime`),
  CONSTRAINT `log_events_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log_events_articles` (
  `log_event_id` int(10) unsigned NOT NULL,
  `article_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`article_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `log_events_articles_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_articles_fk1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log_events_item` (
  `log_event_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`item_id`),
  KEY `car_id` (`item_id`),
  CONSTRAINT `log_events_cars_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_cars_fk1` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log_events_pictures` (
  `log_event_id` int(10) unsigned NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `log_events_pictures_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_pictures_fk1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log_events_user` (
  `log_event_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`user_id`),
  KEY `FK_log_events_user_users_id` (`user_id`),
  CONSTRAINT `FK_log_events_user_log_events_id` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`),
  CONSTRAINT `FK_log_events_user_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;