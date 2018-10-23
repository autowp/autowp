CREATE TABLE `ip_monitoring4` (
  `day_date` date NOT NULL,
  `hour` tinyint(3) unsigned NOT NULL,
  `tenminute` tinyint(3) unsigned NOT NULL,
  `minute` tinyint(3) unsigned NOT NULL,
  `count` int(10) unsigned NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`,`day_date`,`hour`,`tenminute`,`minute`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

CREATE TABLE `banned_ip` (
  `up_to` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `by_user_id` int(10) unsigned DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`),
  KEY `up_to` (`up_to`),
  KEY `by_user_id` (`by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ip_whitelist` (
  `description` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `referer` (
  `host` varchar(255) DEFAULT NULL,
  `url` varchar(1000) NOT NULL,
  `count` int(11) unsigned NOT NULL DEFAULT '0',
  `last_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `accept` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`url`),
  KEY `UK_referer_host` (`host`,`last_date`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

CREATE TABLE `referer_whitelist` (
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `referer_blacklist` (
  `host` varchar(255) NOT NULL,
  `hard` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
