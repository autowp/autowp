CREATE TABLE `telegram_chat` (
  `chat_id` int(11) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `token` varchar(50) DEFAULT NULL,
  `messages` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`chat_id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  CONSTRAINT `telegram_chat_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `telegram_brand` (
  `chat_id` int(11) NOT NULL,
  `inbox` tinyint(1) NOT NULL DEFAULT '0',
  `new` tinyint(1) NOT NULL DEFAULT '0',
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`chat_id`) USING BTREE,
  KEY `chat_id` (`chat_id`),
  CONSTRAINT `telegram_brand_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;