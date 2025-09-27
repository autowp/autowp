CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(20) DEFAULT NULL,
  `password` varchar(50) NOT NULL DEFAULT '',
  `e_mail` varchar(50) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `reg_date` timestamp NULL DEFAULT NULL,
  `last_online` timestamp NULL DEFAULT NULL,
  `icq` int(10) unsigned NOT NULL DEFAULT '0',
  `url` varchar(50) NOT NULL DEFAULT '',
  `own_car` varchar(100) NOT NULL DEFAULT '',
  `dream_car` varchar(100) NOT NULL DEFAULT '',
  `forums_topics` int(10) unsigned NOT NULL DEFAULT '0',
  `forums_messages` int(10) unsigned NOT NULL DEFAULT '0',
  `pictures_added` int(10) unsigned NOT NULL DEFAULT '0',
  `pictures_total` int(11) NOT NULL DEFAULT '0',
  `e_mail_checked` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `hide_e_mail` int(11) DEFAULT NULL,
  `authority` float DEFAULT '0',
  `pictures_ratio` double unsigned DEFAULT NULL,
  `email_to_check` varchar(50) DEFAULT NULL,
  `email_check_code` varchar(32) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `messaging_interval` int(10) unsigned NOT NULL DEFAULT '10',
  `last_message_time` timestamp NULL DEFAULT NULL,
  `deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `identity` varchar(50) DEFAULT NULL,
  `img` int(10) unsigned DEFAULT NULL,
  `votes_per_day` int(10) unsigned NOT NULL DEFAULT '1',
  `votes_left` int(10) unsigned NOT NULL DEFAULT '0',
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `specs_volume` int(11) NOT NULL DEFAULT '0',
  `specs_volume_valid` tinyint(4) NOT NULL DEFAULT '0',
  `specs_positives` int(11) DEFAULT NULL,
  `specs_negatives` int(11) DEFAULT NULL,
  `specs_weight` double NOT NULL DEFAULT '0',
  `last_ip` varbinary(16) NOT NULL,
  `language` varchar(5) NOT NULL DEFAULT 'ru',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `e_mail` (`e_mail`),
  UNIQUE KEY `identity` (`identity`),
  KEY `password` (`password`),
  KEY `email_check_code` (`email_check_code`),
  KEY `role` (`role`),
  KEY `specs_volume` (`specs_volume`),
  KEY `last_ip` (`last_ip`),
  KEY `language` (`language`),
  KEY `pictures_total` (`pictures_total`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`language`) REFERENCES `language` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `contact` (
  `user_id` int(10) unsigned NOT NULL,
  `contact_user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL,
  PRIMARY KEY (`user_id`,`contact_user_id`),
  KEY `contact_user_id` (`contact_user_id`),
  CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `login_state` (
  `state` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `language` varchar(5) NOT NULL,
  `time` timestamp NOT NULL,
  `service` varchar(50) NOT NULL,
  PRIMARY KEY (`state`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `personal_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_user_id` int(10) unsigned DEFAULT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `contents` mediumtext NOT NULL,
  `add_datetime` timestamp NOT NULL,
  `readen` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `deleted_by_from` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `deleted_by_to` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`,`readen`),
  KEY `IX_personal_messages` (`from_user_id`,`to_user_id`,`readen`,`deleted_by_to`),
  KEY `IX_personal_messages2` (`to_user_id`,`from_user_id`,`deleted_by_to`),
  CONSTRAINT `personal_messages_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `personal_messages_fk1` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `used_for_reg` tinyint(3) unsigned NOT NULL,
  `service_id` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_id` (`service_id`,`external_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_account_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_password_remind` (
  `hash` varchar(255) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`),
  KEY `FK_user_password_remind_users_id` (`user_id`),
  CONSTRAINT `FK_user_password_remind_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_remember` (
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_remember_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_renames` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `old_name` varchar(255) NOT NULL,
  `new_name` varchar(255) NOT NULL,
  `date` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  CONSTRAINT `user_renames_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;