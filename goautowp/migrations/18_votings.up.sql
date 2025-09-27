CREATE TABLE `voting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `multivariant` tinyint(1) NOT NULL DEFAULT '0',
  `begin_date` date NOT NULL,
  `end_date` date NOT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT '0',
  `text` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `voting_variant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voting_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(3) unsigned NOT NULL,
  `text` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voting_id` (`voting_id`,`name`),
  UNIQUE KEY `unique_position` (`voting_id`,`position`),
  KEY `voting_id_2` (`voting_id`),
  CONSTRAINT `voting_variants_ibfk_1` FOREIGN KEY (`voting_id`) REFERENCES `voting` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `voting_variant_vote` (
  `voting_variant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`voting_variant_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `voting_variant_id` (`voting_variant_id`),
  CONSTRAINT `voting_variant_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `voting_variant_votes_ibfk_2` FOREIGN KEY (`voting_variant_id`) REFERENCES `voting_variant` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;