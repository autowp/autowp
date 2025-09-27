CREATE TABLE `comment_type` (
  `id` tinyint(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `comment_type` VALUES 
(2,'К группам близнецов и музеям'),
(1,'К картинкам'),
(3,'К опросам'),
(4,'К статьям'),
(5,'Форум');

CREATE TABLE `comment_topic` (
  `type_id` tinyint(3) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `messages` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`type_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `comment_topic_view` (
  `type_id` tinyint(3) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`,`item_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_topic_view_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `comment_message` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `type_id` tinyint(11) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `author_id` int(11) unsigned DEFAULT NULL,
  `datetime` timestamp NOT NULL,
  `message` mediumtext NOT NULL,
  `moderator_attention` tinyint(3) unsigned NOT NULL,
  `vote` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `replies_count` int(10) unsigned NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL,
  `delete_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `type_id` (`type_id`,`item_id`),
  KEY `datetime_sort` (`datetime`),
  KEY `deleted_by` (`deleted_by`),
  KEY `parent_id` (`parent_id`),
  KEY `moderator_attention` (`moderator_attention`),
  KEY `author_date` (`author_id`,`datetime`),
  KEY `author_vote` (`author_id`,`vote`),
  KEY `delete_date` (`delete_date`),
  CONSTRAINT `comment_message_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comment_message_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `comment_message_ibfk_3` FOREIGN KEY (`type_id`) REFERENCES `comment_type` (`id`),
  CONSTRAINT `comment_message_ibfk_4` FOREIGN KEY (`parent_id`) REFERENCES `comment_message` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `comment_vote` (
  `comment_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_vote_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comment_message` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_vote_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `comment_topic_subscribe` (
  `item_id` int(11) unsigned NOT NULL,
  `type_id` tinyint(4) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `sent` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`,`type_id`,`user_id`),
  KEY `comment_topic_subscribe_ibfk_1` (`user_id`),
  CONSTRAINT `comment_topic_subscribe_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;