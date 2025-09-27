CREATE TABLE `picture_vote` (
  `picture_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `value` tinyint(4) NOT NULL,
  `timestamp` timestamp NOT NULL,
  PRIMARY KEY (`picture_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `picture_vote_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `picture_vote_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `picture_vote_summary` (
  `picture_id` int(10) unsigned NOT NULL,
  `positive` int(11) NOT NULL DEFAULT '0',
  `negative` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`picture_id`),
  CONSTRAINT `picture_vote_summary_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;