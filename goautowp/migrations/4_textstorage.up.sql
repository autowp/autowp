CREATE TABLE `textstorage_text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text NOT NULL,
  `last_updated` timestamp NOT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `textstorage_revision` (
  `text_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `text` text NOT NULL,
  `timestamp` timestamp NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`text_id`,`revision`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `textstorage_revision_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  CONSTRAINT `textstorage_revision_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;