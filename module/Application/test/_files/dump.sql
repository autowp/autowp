USE autowp_test;
SET FOREIGN_KEY_CHECKS = 0;
-- --------------------------------------------------------

--
-- Table structure for table `acl_resources`
--

drop table if exists acl_resources;
CREATE TABLE IF NOT EXISTS `acl_resources` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

REPLACE INTO `acl_resources` (`id`, `name`) VALUES
(12, 'attrs'),
(1, 'brand'),
(4, 'car'),
(14, 'category'),
(6, 'comment'),
(8, 'engine'),
(21, 'factory'),
(10, 'forums'),
(15, 'hotlinks'),
(2, 'model'),
(19, 'museums'),
(7, 'page'),
(5, 'picture'),
(9, 'rights'),
(17, 'specifications'),
(18, 'status'),
(11, 'twins'),
(13, 'user'),
(20, 'website');

-- --------------------------------------------------------

--
-- Table structure for table `acl_resources_privileges`
--

CREATE TABLE IF NOT EXISTS `acl_resources_privileges` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`resource_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8;

replace into acl_resources_privileges (id, resource_id, name)
values (1, 4, "edit_meta"), (2, 11, "edit"), (3, 13, "ban"), (4, 4, "add"), 
(5, 4, "move"), (6, 21, "edit"), (7, 17, "edit");

-- --------------------------------------------------------

--
-- Table structure for table `acl_roles`
--

CREATE TABLE IF NOT EXISTS `acl_roles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8;

INSERT INTO `acl_roles` (`id`, `name`) VALUES
(1, 'abstract-user'),
(5, 'admin'),
(12, 'articles-moder'),
(11, 'brands-moder'),
(10, 'cars-moder'),
(8, 'comments-writer'),
(15, 'engines-moder'),
(23, 'expert'),
(58, 'factory-moder'),
(16, 'forums-moder'),
(49, 'green-user'),
(7, 'guest'),
(17, 'models-moder'),
(14, 'moder'),
(50, 'museum-moder'),
(13, 'pages-moder'),
(9, 'pictures-moder'),
(47, 'specifications-editor'),
(6, 'user');

-- --------------------------------------------------------

--
-- Table structure for table `acl_roles_parents`
--

CREATE TABLE IF NOT EXISTS `acl_roles_parents` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `parent_role_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`parent_role_id`),
  KEY `parent_role_id` (`parent_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO `acl_roles_parents` (`role_id`, `parent_role_id`) VALUES
(5, 14),
(10, 14),
(5, 10),
(5, 58),
(14, 6);

-- --------------------------------------------------------

--
-- Table structure for table `acl_roles_privileges_allowed`
--

CREATE TABLE IF NOT EXISTS `acl_roles_privileges_allowed` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `privilege_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

replace into acl_roles_privileges_allowed (role_id, privilege_id)
values (10, 1), (10, 2), (10, 3), (10, 4), (10, 5), (58, 6), (6, 7);

-- --------------------------------------------------------

--
-- Table structure for table `acl_roles_privileges_denied`
--

CREATE TABLE IF NOT EXISTS `acl_roles_privileges_denied` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `privilege_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `html_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `catname` varchar(100) NOT NULL,
  `last_editor_id` int(10) UNSIGNED DEFAULT NULL,
  `last_edit_date` timestamp NULL DEFAULT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author_id` int(10) UNSIGNED DEFAULT NULL,
  `enabled` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `first_enabled_datetime` timestamp NULL DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `preview_width` tinyint(3) UNSIGNED DEFAULT NULL,
  `preview_height` tinyint(3) UNSIGNED DEFAULT NULL,
  `preview_filename` varchar(50) DEFAULT NULL,
  `ratio` float UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  KEY `html_id` (`html_id`),
  KEY `last_editor_id` (`last_editor_id`),
  KEY `author_id` (`author_id`),
  KEY `first_enabled_datetime` (`first_enabled_datetime`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`last_editor_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_brands`
--

CREATE TABLE IF NOT EXISTS `articles_brands` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `brand_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`brand_id`),
  KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_brands_cache`
--

CREATE TABLE IF NOT EXISTS `articles_brands_cache` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `brand_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`brand_id`),
  KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_cars`
--

CREATE TABLE IF NOT EXISTS `articles_cars` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`car_id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_criterias_votes`
--

CREATE TABLE IF NOT EXISTS `articles_criterias_votes` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `criteria_id` tinyint(3) UNSIGNED NOT NULL,
  `votes_count` int(10) UNSIGNED NOT NULL,
  `summary_vote` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`criteria_id`),
  KEY `criteria_id` (`criteria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `articles_criterias_votes_ips`
--

CREATE TABLE IF NOT EXISTS `articles_criterias_votes_ips` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `criteria_id` tinyint(3) UNSIGNED NOT NULL,
  `ip` varchar(15) NOT NULL,
  `vote_datetime` timestamp NOT NULL,
  PRIMARY KEY (`article_id`,`criteria_id`,`ip`),
  KEY `criteria_id` (`criteria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `articles_engines`
--

CREATE TABLE IF NOT EXISTS `articles_engines` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `engine_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`engine_id`),
  KEY `engine_id` (`engine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`article_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_sources`
--

CREATE TABLE IF NOT EXISTS `articles_sources` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(100) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`article_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_twins_groups`
--

CREATE TABLE IF NOT EXISTS `articles_twins_groups` (
  `article_id` int(10) UNSIGNED NOT NULL,
  `twins_group_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`,`twins_group_id`),
  KEY `twins_group_id` (`twins_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`twins_group_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `articles_votings_criterias`
--

CREATE TABLE IF NOT EXISTS `articles_votings_criterias` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_attributes`
--
drop table if exists attrs_attributes;
CREATE TABLE IF NOT EXISTS `attrs_attributes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type_id` smallint(5) UNSIGNED DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `unit_id` int(10) UNSIGNED ZEROFILL DEFAULT NULL,
  `description` text,
  `precision` smallint(5) UNSIGNED DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL,
  `multiple` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`parent_id`),
  UNIQUE KEY `position` (`position`,`parent_id`),
  KEY `type` (`type_id`),
  KEY `unit_id` (`unit_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=208 DEFAULT CHARSET=utf8;

INSERT INTO `attrs_attributes` (`id`, `name`, `type_id`, `parent_id`, `unit_id`, `description`, `precision`, `position`, `multiple`) VALUES
(1, 'specs/attrs/14/17/1', 2, 17, 0000000001, NULL, NULL, 1, 0),
(2, 'specs/attrs/14/17/2', 2, 17, 0000000001, NULL, NULL, 2, 0),
(3, 'specs/attrs/14/17/3', 2, 17, 0000000001, NULL, NULL, 3, 0),
(4, 'specs/attrs/14/4', 2, 14, 0000000001, NULL, NULL, 4, 0),
(5, 'specs/attrs/14/18/5', 2, 18, 0000000001, NULL, NULL, 5, 0),
(6, 'specs/attrs/14/18/6', 2, 18, 0000000001, NULL, NULL, 6, 0),
(7, 'specs/attrs/14/167/7', 2, 167, 0000000001, '', NULL, 8, 0),
(8, 'specs/attrs/15/8', 1, 15, NULL, '', NULL, 9, 0),
(9, 'specs/attrs/15/9', 1, 15, NULL, '', NULL, 10, 0),
(10, 'specs/attrs/15/10', 1, 15, NULL, NULL, NULL, 11, 0),
(11, 'specs/attrs/54/195/11', 3, 195, 0000000003, 'specs/attrs/54/195/11/description', 1, 11, 0),
(12, 'specs/attrs/16/12', NULL, 16, NULL, NULL, NULL, 12, 0),
(13, 'specs/attrs/16/13', 2, 16, NULL, NULL, NULL, 13, 0),
(14, 'specs/attrs/14', NULL, NULL, NULL, NULL, NULL, 16, 0),
(15, 'specs/attrs/15', NULL, NULL, NULL, NULL, NULL, 46, 0),
(16, 'specs/attrs/16', NULL, NULL, NULL, NULL, NULL, 15, 0),
(17, 'specs/attrs/14/17', NULL, 14, NULL, 'specs/attrs/14/17/description', NULL, 17, 0),
(18, 'specs/attrs/14/18', NULL, 14, NULL, NULL, NULL, 18, 0),
(19, 'specs/attrs/22/19', NULL, 22, NULL, NULL, NULL, 19, 0),
(20, 'specs/attrs/22/19/20', 6, 19, NULL, NULL, NULL, 20, 0),
(21, 'specs/attrs/22/19/21', 6, 19, NULL, NULL, NULL, 21, 0),
(22, 'specs/attrs/22', NULL, NULL, NULL, NULL, NULL, 40, 0),
(23, 'specs/attrs/22/23', 7, 22, NULL, NULL, NULL, 23, 0),
(24, 'specs/attrs/22/24', NULL, 22, NULL, NULL, NULL, 24, 0),
(25, 'specs/attrs/22/24/25', 2, 24, NULL, NULL, NULL, 25, 0),
(26, 'specs/attrs/22/24/26', 6, 24, NULL, NULL, NULL, 26, 0),
(27, 'specs/attrs/22/24/27', 2, 24, NULL, NULL, NULL, 27, 0),
(28, 'specs/attrs/22/24/28', 3, 24, 0000000001, NULL, NULL, 28, 0),
(29, 'specs/attrs/22/24/29', 3, 24, 0000000001, NULL, NULL, 29, 0),
(30, 'specs/attrs/22/30', 3, 22, NULL, '', 2, 30, 0),
(31, 'specs/attrs/22/31', 2, 22, 0000000004, NULL, NULL, 31, 0),
(32, 'specs/attrs/22/32', NULL, 22, NULL, NULL, NULL, 32, 0),
(33, 'specs/attrs/22/32/33', 2, 32, 0000000005, 'specs/attrs/22/32/33/description', NULL, 33, 0),
(34, 'specs/attrs/22/32/34', 2, 32, 0000000006, NULL, NULL, 34, 0),
(35, 'specs/attrs/22/32/35', 2, 32, 0000000006, NULL, NULL, 35, 0),
(36, 'specs/attrs/22/36', NULL, 22, NULL, NULL, NULL, 36, 0),
(37, 'specs/attrs/22/36/37', 2, 36, 0000000007, NULL, NULL, 37, 0),
(38, 'specs/attrs/22/36/38', 2, 36, 0000000006, NULL, NULL, 38, 0),
(39, 'specs/attrs/22/36/39', 2, 36, 0000000006, NULL, NULL, 39, 0),
(40, 'specs/attrs/40', NULL, NULL, NULL, NULL, NULL, 45, 0),
(41, 'specs/attrs/40/41', 7, 40, NULL, '', NULL, 41, 0),
(42, 'specs/attrs/40/42', NULL, 40, NULL, NULL, NULL, 42, 0),
(43, 'specs/attrs/40/42/43', 7, 42, NULL, '', NULL, 43, 0),
(44, 'specs/attrs/40/42/44', 2, 42, NULL, NULL, NULL, 45, 0),
(45, 'specs/attrs/45', 1, NULL, NULL, NULL, NULL, 0, 0),
(46, 'specs/attrs/46', NULL, NULL, NULL, NULL, NULL, 74, 0),
(47, 'specs/attrs/46/47', 3, 46, 0000000008, NULL, 1, 47, 0),
(48, 'specs/attrs/46/48', 3, 46, 0000000009, '', 1, 49, 0),
(49, 'specs/attrs/46/49', 3, 46, 0000000009, '', 1, 51, 0),
(50, 'specs/attrs/46/50', 3, 46, 0000000009, '', 1, 52, 0),
(51, 'specs/attrs/46/51', 3, 46, 0000000009, NULL, 1, 53, 0),
(52, 'specs/attrs/46/52', 3, 46, 0000000009, NULL, 1, 54, 0),
(53, 'specs/attrs/46/53', 5, 46, NULL, NULL, NULL, 55, 0),
(54, 'specs/attrs/54', NULL, NULL, NULL, NULL, NULL, 82, 0),
(55, 'specs/attrs/54/55', 2, 54, 0000000011, NULL, NULL, 55, 0),
(56, 'specs/attrs/54/56', 2, 54, 0000000011, NULL, NULL, 56, 0),
(57, 'specs/attrs/54/57', NULL, 54, NULL, NULL, NULL, 57, 0),
(58, 'specs/attrs/54/57/58', 2, 57, 0000000012, NULL, NULL, 58, 0),
(59, 'specs/attrs/54/57/59', 2, 57, 0000000012, NULL, NULL, 59, 0),
(60, 'specs/attrs/54/60', NULL, 54, 0000000012, '', NULL, 60, 0),
(61, 'specs/attrs/54/60/61', 2, 60, 0000000012, NULL, NULL, 61, 0),
(62, 'specs/attrs/54/60/62', 2, 60, 0000000012, NULL, NULL, 62, 0),
(63, 'specs/attrs/14/63', NULL, 14, NULL, NULL, NULL, 63, 0),
(64, 'specs/attrs/14/63/64', 3, 63, NULL, NULL, 3, 64, 0),
(65, 'specs/attrs/14/63/65', 3, 63, NULL, NULL, 3, 65, 0),
(66, 'specs/attrs/16/66', 6, 16, NULL, '', NULL, 66, 0),
(67, 'specs/attrs/16/12/67', 2, 12, NULL, 'specs/attrs/16/12/67/description', NULL, 67, 0),
(68, 'specs/attrs/16/12/68', 2, 12, NULL, NULL, NULL, 68, 0),
(69, 'specs/attrs/16/12/69', 2, 12, NULL, NULL, NULL, 69, 0),
(70, 'specs/attrs/70', NULL, NULL, NULL, NULL, NULL, 22, 0),
(71, 'specs/attrs/70/71', 2, 70, 0000000002, '', NULL, 71, 0),
(72, 'specs/attrs/70/72', 2, 70, 0000000002, '', NULL, 72, 0),
(73, 'specs/attrs/70/73', 2, 70, 0000000002, '', NULL, 73, 0),
(74, 'specs/attrs/74', NULL, NULL, NULL, NULL, NULL, 54, 0),
(75, 'specs/attrs/74/142/75', 1, 142, NULL, '', NULL, 75, 0),
(76, 'specs/attrs/74/143/76', 1, 143, NULL, '', NULL, 76, 0),
(77, 'specs/attrs/74/77', 5, 74, NULL, '', NULL, 77, 0),
(78, 'specs/attrs/54/78', NULL, 54, NULL, '', NULL, 78, 0),
(79, 'specs/attrs/54/78/183/79', 3, 183, 0000000013, '', 1, 79, 0),
(80, 'specs/attrs/54/78/183/80', 3, 183, 0000000013, '', 1, 80, 0),
(81, 'specs/attrs/54/78/183/81', 3, 183, 0000000013, '', 1, 81, 0),
(82, 'specs/attrs/82', 2, NULL, 0000000014, '', NULL, 95, 0),
(83, 'specs/attrs/40/83', 1, 40, NULL, '', NULL, 83, 0),
(84, 'specs/attrs/84', NULL, NULL, NULL, NULL, NULL, 84, 0),
(85, 'specs/attrs/84/85', NULL, 84, NULL, '', NULL, 85, 0),
(86, 'specs/attrs/84/86', NULL, 84, NULL, '', NULL, 86, 0),
(87, 'specs/attrs/84/85/87', 2, 85, 0000000001, '', NULL, 87, 0),
(88, 'specs/attrs/84/85/88', 3, 85, 0000000015, '', NULL, 89, 0),
(89, 'specs/attrs/84/85/89', 3, 85, 0000000015, '', 1, 90, 0),
(90, 'specs/attrs/84/85/90', 2, 85, 0000000010, '', NULL, 88, 0),
(91, 'specs/attrs/84/86/91', 2, 86, 0000000001, '', NULL, 91, 0),
(92, 'specs/attrs/84/86/92', 3, 86, 0000000015, '', NULL, 93, 0),
(93, 'specs/attrs/84/86/93', 3, 86, 0000000015, '', 1, 94, 0),
(94, 'specs/attrs/84/86/94', 2, 86, 0000000010, '', NULL, 92, 0),
(95, 'specs/attrs/95', NULL, NULL, NULL, NULL, NULL, 14, 0),
(96, 'specs/attrs/95/96', 2, 95, 0000000016, '', NULL, 96, 0),
(97, 'specs/attrs/95/97', 2, 95, 0000000016, '', NULL, 97, 0),
(98, 'specs/attrs/22/98', 7, 22, NULL, NULL, NULL, 18, 1),
(99, 'specs/attrs/22/99', 7, 22, NULL, NULL, NULL, 558, 0),
(100, 'specs/attrs/22/100', 1, 22, NULL, NULL, NULL, 17, 0),
(103, 'specs/attrs/16/12/103', 2, 12, NULL, 'specs/attrs/16/12/103/description', NULL, 70, 0),
(104, 'specs/attrs/95/104', NULL, 95, NULL, '', NULL, 99, 0),
(106, 'specs/attrs/95/106', NULL, 95, NULL, '', NULL, 98, 0),
(107, 'specs/attrs/95/107', NULL, 95, NULL, '', NULL, 100, 0),
(108, 'specs/attrs/95/108', NULL, 95, NULL, '', NULL, 101, 0),
(109, 'specs/attrs/95/106/109', NULL, 106, NULL, '', NULL, 1, 0),
(111, 'specs/attrs/95/106/111', 2, 106, 0000000016, '', NULL, 3, 0),
(113, 'specs/attrs/95/104/113', 2, 104, 0000000016, '', NULL, 1, 0),
(114, 'specs/attrs/95/104/114', 2, 104, 0000000016, '', NULL, 2, 0),
(118, 'specs/attrs/95/107/118', 2, 107, 0000000016, '', NULL, 1, 0),
(119, 'specs/attrs/95/107/119', 2, 107, NULL, '', NULL, 2, 0),
(120, 'specs/attrs/95/107/120', 2, 107, NULL, '', NULL, 3, 0),
(121, 'specs/attrs/95/108/121', NULL, 108, NULL, '', NULL, 1, 0),
(122, 'specs/attrs/95/108/122', NULL, 108, NULL, '', NULL, 2, 0),
(123, 'specs/attrs/95/108/121/123', 2, 121, 0000000016, '', NULL, 1, 0),
(124, 'specs/attrs/95/108/121/124', 2, 121, NULL, '', NULL, 2, 0),
(125, 'specs/attrs/95/108/121/125', 2, 121, NULL, '', NULL, 3, 0),
(126, 'specs/attrs/95/108/122/126', 2, 122, 0000000016, '', NULL, 1, 0),
(127, 'specs/attrs/95/108/122/127', 2, 122, NULL, '', NULL, 2, 0),
(128, 'specs/attrs/95/108/122/128', 2, 122, NULL, '', NULL, 3, 0),
(129, 'specs/attrs/95/106/109/129', 2, 109, 0000000016, '', NULL, 1, 0),
(130, 'specs/attrs/95/106/109/130', 2, 109, NULL, '', NULL, 2, 0),
(131, 'specs/attrs/95/106/109/131', 2, 109, NULL, '', NULL, 3, 0),
(132, 'specs/attrs/95/106/111/132', 2, 111, 0000000016, '', NULL, 1, 0),
(133, 'specs/attrs/95/106/111/133', 2, 111, NULL, '', NULL, 2, 0),
(134, 'specs/attrs/95/106/111/134', 2, 111, NULL, '', NULL, 3, 0),
(135, 'specs/attrs/95/135', NULL, 95, NULL, '', NULL, 102, 0),
(136, 'specs/attrs/95/135/136', 2, 135, 0000000016, '', NULL, 1, 0),
(137, 'specs/attrs/95/135/137', 2, 135, 0000000016, '', NULL, 2, 0),
(138, 'specs/attrs/54/138', 5, 54, NULL, '', NULL, 79, 0),
(139, 'specs/attrs/40/42/139', 1, 42, NULL, '', NULL, 44, 0),
(140, 'specs/attrs/14/17/140', 2, 17, 0000000001, '', NULL, 4, 0),
(141, 'specs/attrs/14/17/141', 2, 17, 0000000001, '', NULL, 5, 0),
(142, 'specs/attrs/74/142', NULL, 74, NULL, '', NULL, 78, 0),
(143, 'specs/attrs/74/143', NULL, 74, NULL, '', NULL, 79, 0),
(144, 'specs/attrs/74/142/144', 6, 142, NULL, '', NULL, 76, 0),
(145, 'specs/attrs/74/143/145', 6, 143, NULL, '', NULL, 77, 0),
(146, 'specs/attrs/74/142/146', 3, 142, 0000000001, '', NULL, 77, 0),
(147, 'specs/attrs/74/143/147', 3, 143, 0000000001, '', NULL, 78, 0),
(148, 'specs/attrs/74/142/148', 3, 142, 0000000001, '', NULL, 78, 0),
(149, 'specs/attrs/74/143/149', 3, 143, 0000000001, '', NULL, 79, 0),
(150, 'specs/attrs/74/142/150', 6, 142, NULL, '', NULL, 79, 0),
(151, 'specs/attrs/74/143/151', 6, 143, NULL, '', NULL, 80, 0),
(152, 'specs/attrs/74/142/152', 5, 142, NULL, '', NULL, 80, 0),
(153, 'specs/attrs/74/142/153', 5, 142, NULL, '', NULL, 81, 0),
(154, 'specs/attrs/74/143/154', 5, 143, NULL, '', NULL, 81, 0),
(155, 'specs/attrs/74/143/155', 5, 143, NULL, '', NULL, 82, 0),
(156, 'specs/attrs/22/156', 6, 22, NULL, '', NULL, 559, 0),
(157, 'specs/attrs/157', 6, NULL, NULL, NULL, NULL, 103, 0),
(158, 'specs/attrs/54/158', 3, 54, 0000000002, '', NULL, 80, 0),
(159, 'specs/attrs/22/24/159', 2, 24, 0000000011, '', NULL, 30, 0),
(160, 'specs/attrs/46/160', 3, 46, 0000000009, '', NULL, 56, 0),
(161, 'specs/attrs/46/161', 3, 46, 0000000003, '', NULL, 57, 0),
(162, 'specs/attrs/84/85/162', 3, 85, 0000000001, '', NULL, 91, 0),
(163, 'specs/attrs/84/86/163', 3, 86, 0000000001, '', NULL, 95, 0),
(164, 'specs/attrs/84/164', 1, 84, NULL, '', NULL, 87, 0),
(165, 'specs/attrs/84/165', 6, 84, NULL, '', NULL, 88, 0),
(167, 'specs/attrs/14/167', NULL, 14, NULL, 'specs/attrs/14/167/description', NULL, 64, 0),
(168, 'specs/attrs/14/167/168', 2, 167, 0000000001, '', NULL, 9, 0),
(170, 'specs/attrs/170', 1, NULL, NULL, '', NULL, 104, 255),
(171, 'specs/attrs/22/32/171', 3, 32, 0000000017, 'specs/attrs/22/32/171/description', NULL, 36, 0),
(172, 'specs/attrs/22/32/172', 3, 32, 0000000005, 'specs/attrs/22/32/172/description', NULL, 37, 0),
(173, 'specs/attrs/22/32/173', 3, 32, 0000000005, 'specs/attrs/22/32/173/description', NULL, 38, 0),
(174, 'specs/attrs/22/32/174', 3, 32, 0000000005, 'specs/attrs/22/32/174/description', NULL, 39, 0),
(175, 'specs/attrs/46/175', 3, 46, 0000000009, '', 1, 50, 0),
(176, 'specs/attrs/14/167/176', 2, 167, 0000000001, '', NULL, 7, 0),
(177, 'specs/attrs/22/32/177', 3, 32, 0000000005, '', 1, 40, 0),
(178, 'specs/attrs/22/32/178', 3, 32, 0000000005, 'specs/attrs/22/32/178/description', 1, 41, 0),
(179, 'specs/attrs/22/179', 6, 22, NULL, '', NULL, 560, 0),
(180, 'specs/attrs/46/180', 3, 46, 0000000009, '', NULL, 48, 0),
(181, 'specs/attrs/181', NULL, NULL, NULL, '', NULL, 70, 0),
(182, 'specs/attrs/181/182', 3, 181, 0000000019, '', NULL, 1, 0),
(183, 'specs/attrs/54/78/183', NULL, 78, NULL, '', NULL, 82, 0),
(184, 'specs/attrs/54/78/184', NULL, 78, NULL, '', NULL, 83, 0),
(185, 'specs/attrs/54/78/184/185', 3, 184, 0000000013, '', 1, 1, 0),
(186, 'specs/attrs/54/78/184/186', 3, 184, 0000000013, '', 1, 2, 0),
(187, 'specs/attrs/54/78/184/187', 3, 184, 0000000013, '', 1, 3, 0),
(188, 'specs/attrs/54/78/184/188', 3, 184, 0000000013, '', 1, 4, 0),
(189, 'specs/attrs/54/78/189', NULL, 78, NULL, '', NULL, 84, 0),
(190, 'specs/attrs/54/78/189/190', 3, 189, 0000000013, '', 1, 1, 0),
(191, 'specs/attrs/54/78/189/191', 3, 189, 0000000013, '', 1, 2, 0),
(192, 'specs/attrs/54/78/192', NULL, 78, NULL, '', NULL, 85, 0),
(193, 'specs/attrs/54/78/192/193', 3, 192, 0000000013, '', 1, 1, 0),
(194, 'specs/attrs/54/78/192/194', 3, 192, 0000000013, '', 1, 2, 0),
(195, 'specs/attrs/54/195', NULL, 54, NULL, '', NULL, 83, 0),
(196, 'specs/attrs/54/195/196', 3, 195, 0000000003, 'specs/attrs/54/195/196/description', 1, 12, 0),
(197, 'specs/attrs/54/195/197', 3, 195, 0000000003, 'specs/attrs/54/195/197/description', 1, 13, 0),
(198, 'specs/attrs/54/198', 3, 54, NULL, '', 1, 84, 0),
(199, 'specs/attrs/54/78/199', NULL, 78, NULL, '', NULL, 86, 0),
(200, 'specs/attrs/54/78/199/200', 3, 199, 0000000013, '', 1, 1, 0),
(201, 'specs/attrs/54/78/199/201', 3, 199, 0000000013, '', 1, 2, 0),
(202, 'specs/attrs/54/78/199/202', 3, 199, 0000000013, 'specs/attrs/54/78/199/202/description', 1, 3, 0),
(203, 'specs/attrs/14/17/203', 2, 17, 0000000001, '', NULL, 6, 0),
(204, 'specs/attrs/16/204', 6, 16, NULL, '', NULL, 67, 0),
(205, 'specs/attrs/54/205', 3, 54, 0000000002, '', NULL, 81, 0),
(206, 'specs/attrs/22/206', 7, 22, NULL, '', NULL, 561, 0),
(207, 'specs/attrs/22/207', 7, 22, NULL, '', NULL, 562, 0),
(208, 'specs/attrs/15/208', NULL, 15, NULL, '', NULL, 8, 0),
(209, 'specs/attrs/15/208/209', 7, 208, NULL, '', NULL, 1, 0),
(210, 'specs/attrs/15/208/210', 7, 208, NULL, '', NULL, 2, 0),
(211, 'specs/attrs/15/208/211', NULL, 208, NULL, '', NULL, 3, 0),
(212, 'specs/attrs/15/208/212', 5, 208, NULL, '', NULL, 4, 0),
(213, 'specs/attrs/15/208/211/213', 5, 211, NULL, '', NULL, 1, 0),
(214, 'specs/attrs/15/208/211/214', 6, 211, NULL, '', NULL, 2, 0),
(215, 'specs/attrs/15/208/211/215', 7, 211, NULL, '', NULL, 3, 0),
(216, 'specs/attrs/15/208/211/216', 5, 211, NULL, '', NULL, 4, 0),
(217, 'specs/attrs/15/217', NULL, 15, NULL, '', NULL, 12, 0),
(218, 'specs/attrs/15/217/218', 7, 217, NULL, '', NULL, 1, 0),
(219, 'specs/attrs/15/217/219', 7, 217, NULL, '', NULL, 2, 0),
(220, 'specs/attrs/15/217/220', NULL, 217, NULL, '', NULL, 3, 0),
(221, 'specs/attrs/15/217/221', 5, 217, NULL, '', NULL, 4, 0),
(222, 'specs/attrs/15/217/220/222', 5, 220, NULL, '', NULL, 1, 0),
(223, 'specs/attrs/15/217/220/223', 6, 220, NULL, '', NULL, 2, 0),
(224, 'specs/attrs/15/217/220/224', 7, 220, NULL, '', NULL, 3, 0),
(225, 'specs/attrs/15/217/220/225', 5, 220, NULL, '', NULL, 4, 0),
(226, 'specs/attrs/54/226', 3, 54, 0000000020, '', NULL, 82, 0);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_item_types`
--

CREATE TABLE IF NOT EXISTS `attrs_item_types` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

REPLACE INTO `attrs_item_types` (`id`, `name`) VALUES
(1, 'Автомобиль'),
(3, 'Двигатель'),
(2, 'Модификация автомобиля');

-- --------------------------------------------------------

--
-- Table structure for table `attrs_list_options`
--
drop table if exists attrs_list_options;
CREATE TABLE IF NOT EXISTS `attrs_list_options` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position` (`position`,`attribute_id`,`parent_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8;

INSERT INTO `attrs_list_options` (`id`, `attribute_id`, `name`, `position`, `parent_id`) VALUES
(1, 20, 'specs/attrs/22/19/20/options/1', 1, NULL),
(2, 20, 'specs/attrs/22/19/20/options/2', 2, NULL),
(3, 20, 'specs/attrs/22/19/20/options/3', 3, NULL),
(4, 21, 'specs/attrs/22/19/21/options/4', 1, NULL),
(5, 21, 'specs/attrs/22/19/21/options/5', 2, NULL),
(6, 23, 'specs/attrs/22/23/options/6', 1, NULL),
(7, 26, 'specs/attrs/22/24/26/options/7', 1, NULL),
(8, 26, 'specs/attrs/22/24/26/options/8', 2, NULL),
(9, 26, 'specs/attrs/22/24/26/options/9', 3, NULL),
(10, 26, 'specs/attrs/22/24/26/options/10', 4, NULL),
(11, 66, 'specs/attrs/16/66/options/11', 1, NULL),
(12, 66, 'specs/attrs/16/66/options/12', 2, NULL),
(13, 66, 'specs/attrs/16/66/options/13', 3, NULL),
(14, 41, 'specs/attrs/40/41/options/14', 1, NULL),
(15, 41, 'specs/attrs/40/41/options/15', 2, NULL),
(16, 41, 'specs/attrs/40/41/options/16', 3, NULL),
(17, 41, 'specs/attrs/40/41/options/17', 4, 16),
(18, 41, 'specs/attrs/40/41/options/18', 5, 16),
(19, 41, 'specs/attrs/40/41/options/19', 6, 16),
(20, 43, 'specs/attrs/40/42/43/options/20', 1, NULL),
(21, 43, 'specs/attrs/40/42/43/options/21', 2, NULL),
(22, 43, 'specs/attrs/40/42/43/options/22', 3, NULL),
(23, 43, 'specs/attrs/40/42/43/options/23', 4, NULL),
(24, 23, 'specs/attrs/22/23/options/24', 2, NULL),
(25, 23, 'specs/attrs/22/23/options/25', 1, 24),
(26, 23, 'specs/attrs/22/23/options/26', 4, 24),
(27, 23, 'specs/attrs/22/23/options/27', 5, 24),
(28, 98, 'specs/attrs/22/98/options/28', 1, NULL),
(29, 98, 'specs/attrs/22/98/options/29', 2, NULL),
(30, 98, 'specs/attrs/22/98/options/30', 3, NULL),
(31, 98, 'specs/attrs/22/98/options/31', 4, NULL),
(32, 98, 'specs/attrs/22/98/options/32', 5, NULL),
(33, 98, 'specs/attrs/22/98/options/33', 6, NULL),
(34, 98, 'specs/attrs/22/98/options/34', 1, 32),
(35, 98, 'specs/attrs/22/98/options/35', 2, 32),
(36, 98, 'specs/attrs/22/98/options/36', 1, 28),
(37, 98, 'specs/attrs/22/98/options/37', 2, 28),
(38, 98, 'specs/attrs/22/98/options/38', 3, 28),
(39, 98, 'specs/attrs/22/98/options/39', 4, 28),
(40, 98, 'specs/attrs/22/98/options/40', 5, 28),
(41, 98, 'specs/attrs/22/98/options/41', 6, 28),
(42, 98, 'specs/attrs/22/98/options/42', 7, 28),
(43, 98, 'specs/attrs/22/98/options/43', 8, 28),
(44, 98, 'specs/attrs/22/98/options/44', 9, 28),
(45, 98, 'specs/attrs/22/98/options/45', 10, 28),
(46, 99, 'specs/attrs/22/99/options/46', 1, NULL),
(47, 99, 'specs/attrs/22/99/options/47', 2, NULL),
(48, 99, 'specs/attrs/22/99/options/48', 1, 47),
(49, 99, 'specs/attrs/22/99/options/49', 3, 47),
(50, 43, 'specs/attrs/40/42/43/options/50', 5, NULL),
(51, 43, 'specs/attrs/40/42/43/options/51', 1, 50),
(52, 43, 'specs/attrs/40/42/43/options/52', 2, 50),
(54, 99, 'specs/attrs/22/99/options/54', 2, 47),
(55, 23, 'specs/attrs/22/23/options/55', 3, 24),
(56, 41, 'specs/attrs/40/41/options/56', 7, NULL),
(57, 41, 'specs/attrs/40/41/options/57', 8, NULL),
(58, 144, 'specs/attrs/74/142/144/options/58', 1, NULL),
(59, 144, 'specs/attrs/74/142/144/options/59', 2, NULL),
(60, 145, 'specs/attrs/74/143/145/options/60', 1, NULL),
(61, 145, 'specs/attrs/74/143/145/options/61', 2, NULL),
(62, 150, 'specs/attrs/74/142/150/options/62', 1, NULL),
(63, 150, 'specs/attrs/74/142/150/options/63', 2, NULL),
(64, 150, 'specs/attrs/74/142/150/options/64', 3, NULL),
(65, 151, 'specs/attrs/74/143/151/options/65', 1, NULL),
(66, 151, 'specs/attrs/74/143/151/options/66', 2, NULL),
(67, 151, 'specs/attrs/74/143/151/options/67', 3, NULL),
(68, 156, 'specs/attrs/22/156/options/68', 1, NULL),
(69, 156, 'specs/attrs/22/156/options/69', 2, NULL),
(70, 156, 'specs/attrs/22/156/options/70', 3, NULL),
(71, 157, 'specs/attrs/157/options/71', 1, NULL),
(72, 157, 'specs/attrs/157/options/72', 2, NULL),
(73, 157, 'specs/attrs/157/options/73', 3, NULL),
(74, 157, 'specs/attrs/157/options/74', 4, NULL),
(75, 157, 'specs/attrs/157/options/75', 5, NULL),
(76, 157, 'specs/attrs/157/options/76', 6, NULL),
(77, 157, 'specs/attrs/157/options/77', 7, NULL),
(78, 165, 'specs/attrs/84/165/options/78', 1, NULL),
(79, 165, 'specs/attrs/84/165/options/79', 2, NULL),
(80, 165, 'specs/attrs/84/165/options/80', 3, NULL),
(81, 179, 'specs/attrs/22/179/options/81', 1, NULL),
(82, 179, 'specs/attrs/22/179/options/82', 2, NULL),
(83, 156, 'specs/attrs/22/156/options/83', 4, NULL),
(84, 98, 'specs/attrs/22/98/options/84', 11, NULL),
(85, 204, 'specs/attrs/16/204/options/85', 1, NULL),
(86, 204, 'specs/attrs/16/204/options/86', 2, NULL),
(87, 43, 'specs/attrs/40/42/43/options/87', 6, NULL),
(88, 206, 'specs/attrs/22/206/options/88', 1, NULL),
(89, 206, 'specs/attrs/22/206/options/89', 2, NULL),
(90, 206, 'specs/attrs/22/206/options/90', 3, NULL),
(91, 206, 'specs/attrs/22/206/options/91', 4, NULL),
(92, 206, 'specs/attrs/22/206/options/92', 5, NULL),
(93, 206, 'specs/attrs/22/206/options/93', 6, 88),
(94, 206, 'specs/attrs/22/206/options/94', 7, 88),
(95, 206, 'specs/attrs/22/206/options/95', 8, 88),
(96, 206, 'specs/attrs/22/206/options/96', 9, 89),
(97, 206, 'specs/attrs/22/206/options/97', 10, 89),
(98, 206, 'specs/attrs/22/206/options/98', 11, 89),
(99, 206, 'specs/attrs/22/206/options/99', 12, 89),
(100, 206, 'specs/attrs/22/206/options/100', 13, 88),
(101, 26, 'specs/attrs/22/24/26/options/101', 5, NULL),
(102, 207, 'specs/attrs/22/207/options/102', 1, NULL),
(103, 207, 'specs/attrs/22/207/options/103', 2, NULL),
(104, 207, 'specs/attrs/22/207/options/104', 3, NULL),
(105, 207, 'specs/attrs/22/207/options/105', 4, 103),
(106, 207, 'specs/attrs/22/207/options/106', 5, 103),
(107, 207, 'specs/attrs/22/207/options/107', 6, 103),
(108, 209, 'specs/attrs/15/208/209/options/108', 1, NULL),
(109, 209, 'specs/attrs/15/208/209/options/109', 2, NULL),
(110, 209, 'specs/attrs/15/208/209/options/110', 3, NULL),
(111, 209, 'specs/attrs/15/208/209/options/111', 4, NULL),
(112, 209, 'specs/attrs/15/208/209/options/112', 5, NULL),
(113, 209, 'specs/attrs/15/208/209/options/113', 6, NULL),
(114, 209, 'specs/attrs/15/208/209/options/114', 7, 108),
(115, 209, 'specs/attrs/15/208/209/options/115', 8, 108),
(116, 209, 'specs/attrs/15/208/209/options/116', 9, 109),
(117, 209, 'specs/attrs/15/208/209/options/117', 10, 109),
(118, 209, 'specs/attrs/15/208/209/options/118', 11, 117),
(119, 209, 'specs/attrs/15/208/209/options/119', 12, 117),
(120, 209, 'specs/attrs/15/208/209/options/120', 13, 117),
(121, 209, 'specs/attrs/15/208/209/options/121', 14, 117),
(122, 209, 'specs/attrs/15/208/209/options/122', 15, 117),
(123, 209, 'specs/attrs/15/208/209/options/123', 16, 117),
(124, 209, 'specs/attrs/15/208/209/options/124', 17, 112),
(125, 209, 'specs/attrs/15/208/209/options/125', 18, 112),
(126, 210, 'specs/attrs/15/208/210/options/126', 1, NULL),
(127, 210, 'specs/attrs/15/208/210/options/127', 2, NULL),
(128, 210, 'specs/attrs/15/208/210/options/128', 3, NULL),
(129, 210, 'specs/attrs/15/208/210/options/129', 4, 126),
(130, 210, 'specs/attrs/15/208/210/options/130', 5, 126),
(131, 210, 'specs/attrs/15/208/210/options/131', 6, 126),
(132, 210, 'specs/attrs/15/208/210/options/132', 7, 127),
(133, 210, 'specs/attrs/15/208/210/options/133', 8, 127),
(134, 210, 'specs/attrs/15/208/210/options/134', 9, 127),
(135, 210, 'specs/attrs/15/208/210/options/135', 10, 127),
(136, 210, 'specs/attrs/15/208/210/options/136', 11, 127),
(137, 210, 'specs/attrs/15/208/210/options/137', 12, 127),
(138, 210, 'specs/attrs/15/208/210/options/138', 13, 127),
(139, 210, 'specs/attrs/15/208/210/options/139', 14, 128),
(140, 210, 'specs/attrs/15/208/210/options/140', 15, 128),
(141, 210, 'specs/attrs/15/208/210/options/141', 16, 130),
(142, 210, 'specs/attrs/15/208/210/options/142', 17, 130),
(143, 210, 'specs/attrs/15/208/210/options/143', 18, 130),
(144, 210, 'specs/attrs/15/208/210/options/144', 19, 131),
(145, 210, 'specs/attrs/15/208/210/options/145', 20, 131),
(146, 210, 'specs/attrs/15/208/210/options/146', 21, 131),
(147, 210, 'specs/attrs/15/208/210/options/147', 22, 138),
(148, 210, 'specs/attrs/15/208/210/options/148', 23, 147),
(149, 210, 'specs/attrs/15/208/210/options/149', 24, 140),
(150, 210, 'specs/attrs/15/208/210/options/150', 25, 140),
(151, 210, 'specs/attrs/15/208/210/options/151', 26, 140),
(152, 214, 'specs/attrs/15/208/211/214/options/152', 1, NULL),
(153, 214, 'specs/attrs/15/208/211/214/options/153', 2, NULL),
(154, 215, 'specs/attrs/15/208/211/215/options/154', 1, NULL),
(155, 215, 'specs/attrs/15/208/211/215/options/155', 2, NULL),
(156, 215, 'specs/attrs/15/208/211/215/options/156', 3, 155),
(157, 215, 'specs/attrs/15/208/211/215/options/157', 4, 155),
(158, 215, 'specs/attrs/15/208/211/215/options/158', 5, 155),
(159, 215, 'specs/attrs/15/208/211/215/options/159', 6, NULL),
(160, 218, 'specs/attrs/15/217/218/options/160', 1, NULL),
(161, 218, 'specs/attrs/15/217/218/options/161', 2, 160),
(162, 218, 'specs/attrs/15/217/218/options/162', 3, 160),
(163, 218, 'specs/attrs/15/217/218/options/163', 4, NULL),
(164, 218, 'specs/attrs/15/217/218/options/164', 5, 163),
(165, 218, 'specs/attrs/15/217/218/options/165', 6, 163),
(166, 218, 'specs/attrs/15/217/218/options/166', 7, 165),
(167, 218, 'specs/attrs/15/217/218/options/167', 8, 165),
(168, 218, 'specs/attrs/15/217/218/options/168', 9, 165),
(169, 218, 'specs/attrs/15/217/218/options/169', 10, 165),
(170, 218, 'specs/attrs/15/217/218/options/170', 11, 165),
(171, 218, 'specs/attrs/15/217/218/options/171', 12, 165),
(172, 218, 'specs/attrs/15/217/218/options/172', 13, NULL),
(173, 218, 'specs/attrs/15/217/218/options/173', 14, NULL),
(174, 218, 'specs/attrs/15/217/218/options/174', 15, NULL),
(175, 218, 'specs/attrs/15/217/218/options/175', 16, 174),
(176, 218, 'specs/attrs/15/217/218/options/176', 17, 174),
(177, 218, 'specs/attrs/15/217/218/options/177', 18, NULL),
(178, 209, 'specs/attrs/15/208/209/options/178', 19, NULL),
(179, 218, 'specs/attrs/15/217/218/options/179', 19, NULL),
(180, 219, 'specs/attrs/15/217/219/options/180', 1, NULL),
(181, 219, 'specs/attrs/15/217/219/options/181', 2, 180),
(182, 219, 'specs/attrs/15/217/219/options/182', 3, 180),
(183, 219, 'specs/attrs/15/217/219/options/183', 4, 182),
(184, 219, 'specs/attrs/15/217/219/options/184', 5, 182),
(185, 219, 'specs/attrs/15/217/219/options/185', 6, 182),
(186, 219, 'specs/attrs/15/217/219/options/186', 7, 180),
(187, 219, 'specs/attrs/15/217/219/options/187', 8, 186),
(188, 219, 'specs/attrs/15/217/219/options/188', 9, 186),
(189, 219, 'specs/attrs/15/217/219/options/189', 10, 186),
(190, 219, 'specs/attrs/15/217/219/options/190', 11, NULL),
(191, 219, 'specs/attrs/15/217/219/options/191', 12, 190),
(192, 219, 'specs/attrs/15/217/219/options/192', 13, 190),
(193, 219, 'specs/attrs/15/217/219/options/193', 14, 190),
(194, 219, 'specs/attrs/15/217/219/options/194', 15, 190),
(195, 219, 'specs/attrs/15/217/219/options/195', 16, 190),
(196, 219, 'specs/attrs/15/217/219/options/196', 17, 190),
(197, 219, 'specs/attrs/15/217/219/options/197', 18, 190),
(198, 219, 'specs/attrs/15/217/219/options/198', 19, 197),
(199, 219, 'specs/attrs/15/217/219/options/199', 20, 198),
(200, 219, 'specs/attrs/15/217/219/options/200', 21, NULL),
(201, 219, 'specs/attrs/15/217/219/options/201', 22, 200),
(202, 219, 'specs/attrs/15/217/219/options/202', 23, 200),
(203, 219, 'specs/attrs/15/217/219/options/203', 24, 202),
(204, 219, 'specs/attrs/15/217/219/options/204', 25, 202),
(205, 219, 'specs/attrs/15/217/219/options/205', 26, 202),
(206, 223, 'specs/attrs/15/217/220/223/options/206', 1, NULL),
(207, 223, 'specs/attrs/15/217/220/223/options/207', 2, NULL),
(208, 224, 'specs/attrs/15/217/220/224/options/208', 1, NULL),
(209, 224, 'specs/attrs/15/217/220/224/options/209', 2, NULL),
(210, 224, 'specs/attrs/15/217/220/224/options/210', 3, 209),
(211, 224, 'specs/attrs/15/217/220/224/options/211', 4, 209),
(212, 224, 'specs/attrs/15/217/220/224/options/212', 5, 209),
(213, 224, 'specs/attrs/15/217/220/224/options/213', 6, NULL),
(214, 179, 'specs/attrs/22/179/options/liquid-air', 3, NULL),
(215, 99, 'specs/attrs/engine/turbo/options/x6', 4, 47);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_types`
--

CREATE TABLE IF NOT EXISTS `attrs_types` (
  `id` smallint(6) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `element` varchar(30) NOT NULL,
  `maxlength` int(10) UNSIGNED DEFAULT NULL,
  `size` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO `attrs_types` (`id`, `name`, `element`, `maxlength`, `size`) VALUES
(000001, 'Строка', 'text', 255, 60),
(000002, 'Целое число', 'text', 15, 5),
(000003, 'Число с плавающей точкой', 'text', 20, 5),
(000004, 'Текст', 'textarea', 0, NULL),
(000005, 'Флаг', 'select', 0, NULL),
(000006, 'Список значений', 'select', 0, NULL),
(000007, 'Дерево значений', 'select', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_units`
--

CREATE TABLE IF NOT EXISTS `attrs_units` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `abbr` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `abbr` (`abbr`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `conflict` tinyint(4) NOT NULL DEFAULT '0',
  `weight` double DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  KEY `update_date` (`update_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';

replace into attrs_user_values (attribute_id, item_id, item_type_id, user_id, add_date, update_date, conflict, weight)
values (20, 1, 1, 1, NOW(), NOW(), 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values_float`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values_float` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` double DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values_int`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values_int` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` int(11) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values_list`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values_list` (
  `attribute_id` int(11) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(4) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `value` int(11) UNSIGNED DEFAULT NULL,
  `ordering` int(11) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`,`ordering`),
  KEY `FK_attrs_user_values_list_attrs_item_types_id` (`item_type_id`),
  KEY `FK_attrs_user_values_list_users_id` (`user_id`),
  KEY `FK_attrs_user_values_list_attrs_list_options_id` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

replace into attrs_user_values_list (attribute_id, item_id, item_type_id, value, user_id, ordering)
values (20, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values_string`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values_string` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `attrs_user_values_text`
--

CREATE TABLE IF NOT EXISTS `attrs_user_values_text` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` text,
  `user_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values`
--

CREATE TABLE IF NOT EXISTS `attrs_values` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `conflict` tinyint(4) NOT NULL DEFAULT '0',
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`,`item_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

replace into attrs_values (attribute_id, item_id, item_type_id, update_date, conflict)
values (20, 1, 1, NOW(), 0);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values_float`
--

CREATE TABLE IF NOT EXISTS `attrs_values_float` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `IX_attrs_values_float_value` (`item_type_id`,`attribute_id`,`value`,`item_id`)
) ENGINE=InnoDB AVG_ROW_LENGTH=79 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values_int`
--

CREATE TABLE IF NOT EXISTS `attrs_values_int` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` int(11) DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values_list`
--

CREATE TABLE IF NOT EXISTS `attrs_values_list` (
  `attribute_id` int(11) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(4) UNSIGNED NOT NULL,
  `value` int(11) UNSIGNED DEFAULT NULL,
  `ordering` int(11) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`ordering`),
  KEY `FK_attrs_values_list_attrs_item_types_id` (`item_type_id`),
  KEY `FK_attrs_values_list_attrs_list_options_id` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

replace into attrs_values_list (attribute_id, item_id, item_type_id, value, ordering)
values (20, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values_string`
--

CREATE TABLE IF NOT EXISTS `attrs_values_string` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_values_text`
--

CREATE TABLE IF NOT EXISTS `attrs_values_text` (
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  `value` text,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_zone_attributes`
--

CREATE TABLE IF NOT EXISTS `attrs_zone_attributes` (
  `zone_id` int(10) UNSIGNED NOT NULL,
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `position` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`attribute_id`,`zone_id`),
  UNIQUE KEY `zone_id` (`zone_id`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `attrs_zones`
--

CREATE TABLE IF NOT EXISTS `attrs_zones` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `item_type_id` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`item_type_id`),
  KEY `item_type_id` (`item_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

INSERT INTO `attrs_zones` (`id`, `name`, `item_type_id`) VALUES
(3, 'Автобусы', 1),
(2, 'Грузовые автомобили', 1),
(5, 'Двигатели', 3),
(1, 'Легковые автомобили', 1),
(4, 'Модификации', 2);

-- --------------------------------------------------------

--
-- Table structure for table `banned_ip`
--

CREATE TABLE IF NOT EXISTS `banned_ip` (
  `up_to` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`),
  KEY `up_to` (`up_to`),
  KEY `by_user_id` (`by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_alias`
--

CREATE TABLE IF NOT EXISTS `brand_alias` (
  `name` varchar(255) NOT NULL,
  `brand_id` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`name`),
  KEY `FK_brand_alias_brands_id` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_engine`
--

CREATE TABLE IF NOT EXISTS `brand_engine` (
  `brand_id` int(10) UNSIGNED NOT NULL,
  `engine_id` int(10) UNSIGNED NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`,`engine_id`),
  KEY `engine_id` (`engine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_language`
--

CREATE TABLE IF NOT EXISTS `brand_language` (
  `brand_id` int(11) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`brand_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_link_types`
--

CREATE TABLE IF NOT EXISTS `brand_link_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_type_language`
--

CREATE TABLE IF NOT EXISTS `brand_type_language` (
  `brand_type_id` int(11) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `index_description` varchar(255) NOT NULL,
  PRIMARY KEY (`brand_type_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brand_types`
--

CREATE TABLE IF NOT EXISTS `brand_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  `index_items` smallint(5) UNSIGNED NOT NULL DEFAULT '10',
  `index_description` varchar(255) NOT NULL,
  `ordering` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE IF NOT EXISTS `brands` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder` varchar(50) NOT NULL DEFAULT '',
  `caption` varchar(50) NOT NULL DEFAULT '',
  `position` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `group_id` int(10) UNSIGNED DEFAULT NULL,
  `type_id` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `activepictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `_description` mediumtext,
  `full_caption` varchar(50) DEFAULT NULL,
  `engines_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `carpictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `enginepictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `logopictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `unsortedpictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `mixedpictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `cars_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `new_style` tinyint(1) NOT NULL DEFAULT '0',
  `manual_sort` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `new_models_style` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `conceptcars_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `parent_brand_id` int(10) UNSIGNED DEFAULT NULL,
  `twins_groups_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `new_twins_groups_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `from_year` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `to_year` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `img` int(10) UNSIGNED DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder` (`folder`),
  KEY `group_id` (`group_id`),
  KEY `position` (`position`,`caption`),
  KEY `type_id` (`type_id`,`position`,`caption`),
  KEY `parent_brand_id` (`parent_brand_id`),
  KEY `text_id` (`text_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1893 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`parent_brand_id`)';

REPLACE into brands (id, folder, caption, position)
values (1, 'bmw', 'BMW', 0), (2, 'test-brand', 'Test brand', 0);

-- --------------------------------------------------------

--
-- Table structure for table `brands_cars`
--

CREATE TABLE IF NOT EXISTS `brands_cars` (
  `brand_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `car_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `catname` varchar(70) DEFAULT NULL,
  PRIMARY KEY (`brand_id`,`car_id`),
  UNIQUE KEY `brand_id` (`brand_id`,`catname`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`car_id`)';

REPLACE into brands_cars (brand_id, car_id, type, catname)
values (1, 1, 0, "first-car"),
(1, 2, 0, "second-car"),
(1, 3, 0, 'test-car-3'),
(1, 4, 0, 'test-car-4'),
(1, 5, 0, 'test-car-5'),
(1, 6, 0, 'test-car-6'),
(1, 7, 0, 'test-car-7'),
(1, 8, 0, 'test-car-8'),
(1, 9, 0, 'test-car-9'),
(1, 10, 0, 'test-car-10'),
(1, 11, 0, 'test-car-11'),
(1, 12, 0, 'test-car-12'),
(1, 13, 0, 'test-car-13'),
(1, 14, 0, 'test-car-14'),
(1, 15, 0, 'test-car-15'),
(1, 16, 0, 'test-car-16'),
(1, 17, 0, 'test-car-17'),
(1, 18, 0, 'test-car-18'),
(1, 19, 0, 'test-car-19'),
(1, 20, 0, 'test-car-20'),
(1, 21, 0, 'test-car-21'),
(1, 22, 0, 'test-car-22'),
(1, 23, 0, 'test-car-23'),
(1, 24, 0, 'test-car-24'),
(1, 25, 0, 'test-car-25'),
(1, 26, 0, 'test-car-26'),
(1, 27, 0, 'test-car-27'),
(1, 28, 0, 'test-car-28'),
(1, 29, 0, 'test-car-29'),
(1, 30, 0, 'test-car-30'),
(1, 31, 0, 'test-car-31'),
(1, 32, 0, 'test-car-32'),
(1, 33, 0, 'test-car-33'),
(1, 34, 0, 'test-car-34'),
(1, 35, 0, 'test-car-35'),
(1, 36, 0, 'test-car-36'),
(1, 37, 0, 'test-car-37'),
(1, 38, 0, 'test-car-38'),
(1, 39, 0, 'test-car-39'),
(1, 40, 0, 'test-car-40'),
(1, 41, 0, 'test-car-41'),
(1, 42, 0, 'test-car-42'),
(1, 43, 0, 'test-car-43'),
(1, 44, 0, 'test-car-44'),
(1, 45, 0, 'test-car-45'),
(1, 46, 0, 'test-car-46'),
(1, 47, 0, 'test-car-47'),
(1, 48, 0, 'test-car-48'),
(1, 49, 0, 'test-car-49'),
(1, 50, 0, 'test-car-50'),
(1, 51, 0, 'test-car-51'),
(1, 52, 0, 'test-car-52'),
(1, 53, 0, 'test-car-53'),
(1, 54, 0, 'test-car-54'),
(1, 55, 0, 'test-car-55'),
(1, 56, 0, 'test-car-56'),
(1, 57, 0, 'test-car-57'),
(1, 58, 0, 'test-car-58'),
(1, 59, 0, 'test-car-59'),
(1, 60, 0, 'test-car-60'),
(1, 61, 0, 'test-car-61'),
(1, 62, 0, 'test-car-62'),
(1, 63, 0, 'test-car-63'),
(1, 64, 0, 'test-car-64'),
(1, 65, 0, 'test-car-65'),
(1, 66, 0, 'test-car-66'),
(1, 67, 0, 'test-car-67'),
(1, 68, 0, 'test-car-68'),
(1, 69, 0, 'test-car-69'),
(1, 70, 0, 'test-car-70'),
(1, 71, 0, 'test-car-71'),
(1, 72, 0, 'test-car-72'),
(1, 73, 0, 'test-car-73'),
(1, 74, 0, 'test-car-74'),
(1, 75, 0, 'test-car-75'),
(1, 76, 0, 'test-car-76'),
(1, 77, 0, 'test-car-77'),
(1, 78, 0, 'test-car-78'),
(1, 79, 0, 'test-car-79'),
(1, 80, 0, 'test-car-80'),
(1, 81, 0, 'test-car-81'),
(1, 82, 0, 'test-car-82'),
(1, 83, 0, 'test-car-83'),
(1, 84, 0, 'test-car-84'),
(1, 85, 0, 'test-car-85'),
(1, 86, 0, 'test-car-86'),
(1, 87, 0, 'test-car-87'),
(1, 88, 0, 'test-car-88'),
(1, 89, 0, 'test-car-89'),
(1, 90, 0, 'test-car-90'),
(1, 91, 0, 'test-car-91'),
(1, 92, 0, 'test-car-92'),
(1, 93, 0, 'test-car-93'),
(1, 94, 0, 'test-car-94'),
(1, 95, 0, 'test-car-95'),
(1, 96, 0, 'test-car-96'),
(1, 97, 0, 'test-car-97'),
(1, 98, 0, 'test-car-98'),
(1, 99, 0, 'test-car-99'),
(1, 100, 0, 'test-car-100'),
(1, 101, 0, 'test-car-101'),
(1, 102, 0, 'test-car-102'),
(1, 103, 0, 'test-car-103'),
(1, 104, 0, 'test-car-104'),
(1, 105, 0, 'test-car-105'),
(1, 106, 0, 'test-car-106'),
(1, 107, 0, 'test-car-107'),
(1, 108, 0, 'test-car-108'),
(1, 109, 0, 'test-car-109'),
(1, 110, 0, 'test-car-110'),
(1, 111, 0, 'test-car-111'),
(1, 112, 0, 'test-car-112'),
(1, 113, 0, 'test-car-113'),
(1, 114, 0, 'test-car-114'),
(1, 115, 0, 'test-car-115'),
(1, 116, 0, 'test-car-116'),
(1, 117, 0, 'test-car-117'),
(1, 118, 0, 'test-car-118'),
(1, 119, 0, 'test-car-119'),
(1, 120, 0, 'test-car-120'),
(1, 121, 0, 'test-car-121'),
(1, 122, 0, 'test-car-122'),
(1, 123, 0, 'test-car-123'),
(1, 124, 0, 'test-car-124'),
(1, 125, 0, 'test-car-125'),
(1, 126, 0, 'test-car-126'),
(1, 127, 0, 'test-car-127'),
(1, 128, 0, 'test-car-128'),
(1, 129, 0, 'test-car-129'),
(1, 130, 0, 'test-car-130'),
(1, 131, 0, 'test-car-131'),
(1, 132, 0, 'test-car-132'),
(1, 133, 0, 'test-car-133'),
(1, 134, 0, 'test-car-134'),
(1, 135, 0, 'test-car-135'),
(1, 136, 0, 'test-car-136'),
(1, 137, 0, 'test-car-137'),
(1, 138, 0, 'test-car-138'),
(1, 139, 0, 'test-car-139'),
(1, 140, 0, 'test-car-140'),
(1, 141, 0, 'test-car-141'),
(1, 142, 0, 'test-car-142'),
(1, 143, 0, 'test-car-143'),
(1, 144, 0, 'test-car-144'),
(1, 145, 0, 'test-car-145'),
(1, 146, 0, 'test-car-146'),
(1, 147, 0, 'test-car-147'),
(1, 148, 0, 'test-car-148'),
(1, 149, 0, 'test-car-149'),
(1, 150, 0, 'test-car-150'),
(1, 151, 0, 'test-car-151'),
(1, 152, 0, 'test-car-152'),
(1, 153, 0, 'test-car-153'),
(1, 154, 0, 'test-car-154'),
(1, 155, 0, 'test-car-155'),
(1, 156, 0, 'test-car-156'),
(1, 157, 0, 'test-car-157'),
(1, 158, 0, 'test-car-158'),
(1, 159, 0, 'test-car-159'),
(1, 160, 0, 'test-car-160'),
(1, 161, 0, 'test-car-161'),
(1, 162, 0, 'test-car-162'),
(1, 163, 0, 'test-car-163'),
(1, 164, 0, 'test-car-164'),
(1, 165, 0, 'test-car-165'),
(1, 166, 0, 'test-car-166'),
(1, 167, 0, 'test-car-167'),
(1, 168, 0, 'test-car-168'),
(1, 169, 0, 'test-car-169'),
(1, 170, 0, 'test-car-170'),
(1, 171, 0, 'test-car-171'),
(1, 172, 0, 'test-car-172'),
(1, 173, 0, 'test-car-173'),
(1, 174, 0, 'test-car-174'),
(1, 175, 0, 'test-car-175'),
(1, 176, 0, 'test-car-176'),
(1, 177, 0, 'test-car-177'),
(1, 178, 0, 'test-car-178'),
(1, 179, 0, 'test-car-179'),
(1, 180, 0, 'test-car-180'),
(1, 181, 0, 'test-car-181'),
(1, 182, 0, 'test-car-182'),
(1, 183, 0, 'test-car-183'),
(1, 184, 0, 'test-car-184'),
(1, 185, 0, 'test-car-185'),
(1, 186, 0, 'test-car-186'),
(1, 187, 0, 'test-car-187'),
(1, 188, 0, 'test-car-188'),
(1, 189, 0, 'test-car-189'),
(1, 190, 0, 'test-car-190'),
(1, 191, 0, 'test-car-191'),
(1, 192, 0, 'test-car-192'),
(1, 193, 0, 'test-car-193'),
(1, 194, 0, 'test-car-194'),
(1, 195, 0, 'test-car-195'),
(1, 196, 0, 'test-car-196'),
(1, 197, 0, 'test-car-197'),
(1, 198, 0, 'test-car-198'),
(1, 199, 0, 'test-car-199'),
(1, 200, 0, 'test-car-200'),
(1, 201, 0, 'test-car-201'),
(1, 202, 0, 'test-car-202'),
(1, 203, 0, 'test-car-203');

-- --------------------------------------------------------

--
-- Table structure for table `brands_pictures_cache`
--

CREATE TABLE IF NOT EXISTS `brands_pictures_cache` (
  `brand_id` int(10) UNSIGNED NOT NULL,
  `picture_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`brand_id`,`picture_id`),
  KEY `picture_id` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `car_language`
--

CREATE TABLE IF NOT EXISTS `car_language` (
  `car_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`car_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `car_parent`
--

CREATE TABLE IF NOT EXISTS `car_parent` (
  `car_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `catname` varchar(50) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `manual_catname` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`car_id`,`parent_id`),
  UNIQUE KEY `unique_catname` (`parent_id`,`catname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `car_parent_cache`
--

CREATE TABLE IF NOT EXISTS `car_parent_cache` (
  `car_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `diff` int(11) NOT NULL DEFAULT '0',
  `tuning` tinyint(4) NOT NULL DEFAULT '0',
  `sport` tinyint(4) NOT NULL DEFAULT '0',
  `design` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`car_id`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE into car_parent_cache (car_id, parent_id, diff, tuning, sport, design)
values (1, 1, 0, 0, 0, 0), 
(2, 2, 0, 0, 0, 0),
(3, 3, 0, 0, 0, 0),
(4, 4, 0, 0, 0, 0),
(5, 5, 0, 0, 0, 0),
(6, 6, 0, 0, 0, 0),
(7, 7, 0, 0, 0, 0),
(8, 8, 0, 0, 0, 0),
(9, 9, 0, 0, 0, 0),
(10, 10, 0, 0, 0, 0),
(11, 11, 0, 0, 0, 0),
(12, 12, 0, 0, 0, 0),
(13, 13, 0, 0, 0, 0),
(14, 14, 0, 0, 0, 0),
(15, 15, 0, 0, 0, 0),
(16, 16, 0, 0, 0, 0),
(17, 17, 0, 0, 0, 0),
(18, 18, 0, 0, 0, 0),
(19, 19, 0, 0, 0, 0),
(20, 20, 0, 0, 0, 0),
(21, 21, 0, 0, 0, 0),
(22, 22, 0, 0, 0, 0),
(23, 23, 0, 0, 0, 0),
(24, 24, 0, 0, 0, 0),
(25, 25, 0, 0, 0, 0),
(26, 26, 0, 0, 0, 0),
(27, 27, 0, 0, 0, 0),
(28, 28, 0, 0, 0, 0),
(29, 29, 0, 0, 0, 0),
(30, 30, 0, 0, 0, 0),
(31, 31, 0, 0, 0, 0),
(32, 32, 0, 0, 0, 0),
(33, 33, 0, 0, 0, 0),
(34, 34, 0, 0, 0, 0),
(35, 35, 0, 0, 0, 0),
(36, 36, 0, 0, 0, 0),
(37, 37, 0, 0, 0, 0),
(38, 38, 0, 0, 0, 0),
(39, 39, 0, 0, 0, 0),
(40, 40, 0, 0, 0, 0),
(41, 41, 0, 0, 0, 0),
(42, 42, 0, 0, 0, 0),
(43, 43, 0, 0, 0, 0),
(44, 44, 0, 0, 0, 0),
(45, 45, 0, 0, 0, 0),
(46, 46, 0, 0, 0, 0),
(47, 47, 0, 0, 0, 0),
(48, 48, 0, 0, 0, 0),
(49, 49, 0, 0, 0, 0),
(50, 50, 0, 0, 0, 0),
(51, 51, 0, 0, 0, 0),
(52, 52, 0, 0, 0, 0),
(53, 53, 0, 0, 0, 0),
(54, 54, 0, 0, 0, 0),
(55, 55, 0, 0, 0, 0),
(56, 56, 0, 0, 0, 0),
(57, 57, 0, 0, 0, 0),
(58, 58, 0, 0, 0, 0),
(59, 59, 0, 0, 0, 0),
(60, 60, 0, 0, 0, 0),
(61, 61, 0, 0, 0, 0),
(62, 62, 0, 0, 0, 0),
(63, 63, 0, 0, 0, 0),
(64, 64, 0, 0, 0, 0),
(65, 65, 0, 0, 0, 0),
(66, 66, 0, 0, 0, 0),
(67, 67, 0, 0, 0, 0),
(68, 68, 0, 0, 0, 0),
(69, 69, 0, 0, 0, 0),
(70, 70, 0, 0, 0, 0),
(71, 71, 0, 0, 0, 0),
(72, 72, 0, 0, 0, 0),
(73, 73, 0, 0, 0, 0),
(74, 74, 0, 0, 0, 0),
(75, 75, 0, 0, 0, 0),
(76, 76, 0, 0, 0, 0),
(77, 77, 0, 0, 0, 0),
(78, 78, 0, 0, 0, 0),
(79, 79, 0, 0, 0, 0),
(80, 80, 0, 0, 0, 0),
(81, 81, 0, 0, 0, 0),
(82, 82, 0, 0, 0, 0),
(83, 83, 0, 0, 0, 0),
(84, 84, 0, 0, 0, 0),
(85, 85, 0, 0, 0, 0),
(86, 86, 0, 0, 0, 0),
(87, 87, 0, 0, 0, 0),
(88, 88, 0, 0, 0, 0),
(89, 89, 0, 0, 0, 0),
(90, 90, 0, 0, 0, 0),
(91, 91, 0, 0, 0, 0),
(92, 92, 0, 0, 0, 0),
(93, 93, 0, 0, 0, 0),
(94, 94, 0, 0, 0, 0),
(95, 95, 0, 0, 0, 0),
(96, 96, 0, 0, 0, 0),
(97, 97, 0, 0, 0, 0),
(98, 98, 0, 0, 0, 0),
(99, 99, 0, 0, 0, 0),
(100, 100, 0, 0, 0, 0),
(101, 101, 0, 0, 0, 0),
(102, 102, 0, 0, 0, 0),
(103, 103, 0, 0, 0, 0),
(104, 104, 0, 0, 0, 0),
(105, 105, 0, 0, 0, 0),
(106, 106, 0, 0, 0, 0),
(107, 107, 0, 0, 0, 0),
(108, 108, 0, 0, 0, 0),
(109, 109, 0, 0, 0, 0),
(110, 110, 0, 0, 0, 0),
(111, 111, 0, 0, 0, 0),
(112, 112, 0, 0, 0, 0),
(113, 113, 0, 0, 0, 0),
(114, 114, 0, 0, 0, 0),
(115, 115, 0, 0, 0, 0),
(116, 116, 0, 0, 0, 0),
(117, 117, 0, 0, 0, 0),
(118, 118, 0, 0, 0, 0),
(119, 119, 0, 0, 0, 0),
(120, 120, 0, 0, 0, 0),
(121, 121, 0, 0, 0, 0),
(122, 122, 0, 0, 0, 0),
(123, 123, 0, 0, 0, 0),
(124, 124, 0, 0, 0, 0),
(125, 125, 0, 0, 0, 0),
(126, 126, 0, 0, 0, 0),
(127, 127, 0, 0, 0, 0),
(128, 128, 0, 0, 0, 0),
(129, 129, 0, 0, 0, 0),
(130, 130, 0, 0, 0, 0),
(131, 131, 0, 0, 0, 0),
(132, 132, 0, 0, 0, 0),
(133, 133, 0, 0, 0, 0),
(134, 134, 0, 0, 0, 0),
(135, 135, 0, 0, 0, 0),
(136, 136, 0, 0, 0, 0),
(137, 137, 0, 0, 0, 0),
(138, 138, 0, 0, 0, 0),
(139, 139, 0, 0, 0, 0),
(140, 140, 0, 0, 0, 0),
(141, 141, 0, 0, 0, 0),
(142, 142, 0, 0, 0, 0),
(143, 143, 0, 0, 0, 0),
(144, 144, 0, 0, 0, 0),
(145, 145, 0, 0, 0, 0),
(146, 146, 0, 0, 0, 0),
(147, 147, 0, 0, 0, 0),
(148, 148, 0, 0, 0, 0),
(149, 149, 0, 0, 0, 0),
(150, 150, 0, 0, 0, 0),
(151, 151, 0, 0, 0, 0),
(152, 152, 0, 0, 0, 0),
(153, 153, 0, 0, 0, 0),
(154, 154, 0, 0, 0, 0),
(155, 155, 0, 0, 0, 0),
(156, 156, 0, 0, 0, 0),
(157, 157, 0, 0, 0, 0),
(158, 158, 0, 0, 0, 0),
(159, 159, 0, 0, 0, 0),
(160, 160, 0, 0, 0, 0),
(161, 161, 0, 0, 0, 0),
(162, 162, 0, 0, 0, 0),
(163, 163, 0, 0, 0, 0),
(164, 164, 0, 0, 0, 0),
(165, 165, 0, 0, 0, 0),
(166, 166, 0, 0, 0, 0),
(167, 167, 0, 0, 0, 0),
(168, 168, 0, 0, 0, 0),
(169, 169, 0, 0, 0, 0),
(170, 170, 0, 0, 0, 0),
(171, 171, 0, 0, 0, 0),
(172, 172, 0, 0, 0, 0),
(173, 173, 0, 0, 0, 0),
(174, 174, 0, 0, 0, 0),
(175, 175, 0, 0, 0, 0),
(176, 176, 0, 0, 0, 0),
(177, 177, 0, 0, 0, 0),
(178, 178, 0, 0, 0, 0),
(179, 179, 0, 0, 0, 0),
(180, 180, 0, 0, 0, 0),
(181, 181, 0, 0, 0, 0),
(182, 182, 0, 0, 0, 0),
(183, 183, 0, 0, 0, 0),
(184, 184, 0, 0, 0, 0),
(185, 185, 0, 0, 0, 0),
(186, 186, 0, 0, 0, 0),
(187, 187, 0, 0, 0, 0),
(188, 188, 0, 0, 0, 0),
(189, 189, 0, 0, 0, 0),
(190, 190, 0, 0, 0, 0),
(191, 191, 0, 0, 0, 0),
(192, 192, 0, 0, 0, 0),
(193, 193, 0, 0, 0, 0),
(194, 194, 0, 0, 0, 0),
(195, 195, 0, 0, 0, 0),
(196, 196, 0, 0, 0, 0),
(197, 197, 0, 0, 0, 0),
(198, 198, 0, 0, 0, 0),
(199, 199, 0, 0, 0, 0),
(200, 200, 0, 0, 0, 0),
(201, 201, 0, 0, 0, 0),
(202, 202, 0, 0, 0, 0),
(203, 203, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `car_type_language`
--

CREATE TABLE IF NOT EXISTS `car_type_language` (
  `car_type_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `name_rp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`car_type_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `car_types`
--

CREATE TABLE IF NOT EXISTS `car_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `catname` varchar(20) NOT NULL,
  `name` varchar(35) NOT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL,
  `name_rp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB';

-- --------------------------------------------------------

--
-- Table structure for table `car_types_parents`
--

CREATE TABLE IF NOT EXISTS `car_types_parents` (
  `id` int(11) UNSIGNED NOT NULL,
  `parent_id` int(11) UNSIGNED NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE IF NOT EXISTS `cars` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `caption` varchar(255) NOT NULL DEFAULT '',
  `begin_year` smallint(5) UNSIGNED DEFAULT NULL,
  `end_year` smallint(5) UNSIGNED DEFAULT NULL,
  `body` varchar(15) NOT NULL,
  `spec_id` int(10) UNSIGNED DEFAULT NULL,
  `spec_inherit` tinyint(1) NOT NULL DEFAULT '1',
  `produced` int(10) UNSIGNED DEFAULT NULL,
  `produced_exactly` tinyint(3) UNSIGNED NOT NULL,
  `is_concept` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  `pictures_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `today` tinyint(3) UNSIGNED DEFAULT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL COMMENT 'Р”Р°С‚Р° СЃРѕР·РґР°РЅРёСЏ Р·Р°РїРёСЃРё',
  `begin_month` tinyint(3) UNSIGNED DEFAULT NULL,
  `end_month` tinyint(3) UNSIGNED DEFAULT NULL,
  `begin_order_cache` date DEFAULT NULL,
  `end_order_cache` date DEFAULT NULL,
  `begin_model_year` smallint(5) UNSIGNED DEFAULT NULL,
  `end_model_year` smallint(5) DEFAULT NULL,
  `_html` text,
  `is_group` tinyint(4) NOT NULL DEFAULT '0',
  `car_type_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `is_concept_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `engine_id` int(10) UNSIGNED DEFAULT NULL,
  `engine_inherit` tinyint(4) NOT NULL DEFAULT '1',
  `text_id` int(11) DEFAULT NULL,
  `full_text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caption` (`caption`,`begin_year`,`body`,`end_year`,`begin_model_year`,`end_model_year`,`is_group`),
  KEY `fullCaptionOrder` (`caption`,`body`,`begin_year`,`end_year`),
  KEY `primary_and_sorting` (`id`,`begin_order_cache`),
  KEY `engine_id` (`engine_id`),
  KEY `spec_id` (`spec_id`),
  KEY `text_id` (`text_id`),
  KEY `full_text_id` (`full_text_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99781 AVG_ROW_LENGTH=152 DEFAULT CHARSET=utf8;

REPLACE into cars (id, caption, body, begin_year, begin_month, 
    end_year, end_month, today, is_concept, engine_id, spec_id, produced, 
    produced_exactly, add_datetime, begin_model_year, end_model_year, is_group)
values (1, 'test car', '', 1999, 2, null, null, 1, 0, 1, null, 100, 1, NOW(), 2000, null, 0),
(2, 'test concept car', '', 1999, 6, 2005, 4, 0, 1, NULL, null, 233, 0, NOW(), 1999, 2005, 0),
(3, 'test car 3', '', 1923, 6, 1927, 1, 0, 0, null, null, 50752, 1, NOW(), 1923, 1927, 0),
(4, 'test car 4', '', 1931, 6, 1937, 5, 1, 0, null, null, 51365, 0, NOW(), 1932, 1937, 0),
(5, 'test car 5', '', 1984, 7, 1984, 4, 1, 0, null, null, 79927, 0, NOW(), 1985, 1984, 0),
(6, 'test car 6', '', 1908, 4, 1908, 9, 0, 0, null, null, 73949, 0, NOW(), 1908, 1908, 0),
(7, 'test car 7', '', 1997, 1, 1997, 12, 1, 0, null, null, 73664, 0, NOW(), 1997, 1997, 0),
(8, 'test car 8', '', 1915, 2, 1920, 5, 1, 0, null, null, 90611, 0, NOW(), 1916, 1920, 0),
(9, 'test car 9', '', 2007, 5, 2017, 12, 1, 0, null, null, 66221, 1, NOW(), 2008, 2017, 0),
(10, 'test car 10', '', 1914, 5, 1919, 12, 1, 0, null, null, 39531, 1, NOW(), 1915, 1919, 0),
(11, 'test car 11', '', 1941, 6, 1946, 1, 1, 0, null, null, 2689, 1, NOW(), 1942, 1946, 0),
(12, 'test car 12', '', 1995, 2, 2005, 5, 1, 0, null, null, 3852, 0, NOW(), 1996, 2005, 0),
(13, 'test car 13', '', 1946, 7, 1947, 7, 0, 0, null, null, 43108, 1, NOW(), 1946, 1947, 0),
(14, 'test car 14', '', 1998, 10, 2006, 7, 1, 0, null, null, 50167, 1, NOW(), 1998, 2006, 0),
(15, 'test car 15', '', 1963, 2, 1966, 7, 1, 0, null, null, 83109, 1, NOW(), 1963, 1966, 0),
(16, 'test car 16', '', 1973, 11, 1975, 8, 1, 0, null, null, 629, 0, NOW(), 1973, 1975, 0),
(17, 'test car 17', '', 2001, 7, 2010, 3, 0, 0, null, null, 14989, 1, NOW(), 2001, 2010, 0),
(18, 'test car 18', '', 2011, 4, 2018, 8, 1, 0, null, null, 91731, 0, NOW(), 2011, 2018, 0),
(19, 'test car 19', '', 1931, 1, 1937, 11, 0, 0, null, null, 99928, 1, NOW(), 1931, 1937, 0),
(20, 'test car 20', '', 1951, 9, 1960, 11, 0, 0, null, null, 10416, 1, NOW(), 1951, 1960, 0),
(21, 'test car 21', '', 1908, 10, 1908, 5, 0, 0, null, null, 48926, 0, NOW(), 1908, 1908, 0),
(22, 'test car 22', '', 1965, 7, 1973, 4, 1, 0, null, null, 89032, 0, NOW(), 1965, 1973, 0),
(23, 'test car 23', '', 1970, 5, 1973, 1, 0, 0, null, null, 81666, 0, NOW(), 1970, 1973, 0),
(24, 'test car 24', '', 1955, 9, 1964, 12, 0, 0, null, null, 86179, 0, NOW(), 1955, 1964, 0),
(25, 'test car 25', '', 1970, 1, 1974, 12, 1, 0, null, null, 69277, 1, NOW(), 1970, 1974, 0),
(26, 'test car 26', '', 1931, 11, 1934, 11, 1, 0, null, null, 34539, 1, NOW(), 1931, 1934, 0),
(27, 'test car 27', '', 2005, 7, 2014, 11, 1, 0, null, null, 86922, 1, NOW(), 2005, 2014, 0),
(28, 'test car 28', '', 1948, 10, 1953, 8, 1, 0, null, null, 79648, 0, NOW(), 1949, 1953, 0),
(29, 'test car 29', '', 1985, 4, 1985, 10, 0, 0, null, null, 5579, 0, NOW(), 1986, 1985, 0),
(30, 'test car 30', '', 1906, 12, 1916, 3, 1, 0, null, null, 90636, 0, NOW(), 1907, 1916, 0),
(31, 'test car 31', '', 1985, 6, 1988, 9, 1, 0, null, null, 30356, 1, NOW(), 1986, 1988, 0),
(32, 'test car 32', '', 2010, 2, 2010, 11, 1, 0, null, null, 48412, 1, NOW(), 2011, 2010, 0),
(33, 'test car 33', '', 2003, 1, 2008, 10, 0, 0, null, null, 1648, 1, NOW(), 2004, 2008, 0),
(34, 'test car 34', '', 1943, 12, 1951, 4, 0, 0, null, null, 61029, 1, NOW(), 1944, 1951, 0),
(35, 'test car 35', '', 2005, 8, 2011, 8, 0, 0, null, null, 39854, 0, NOW(), 2006, 2011, 0),
(36, 'test car 36', '', 2015, 10, 2018, 12, 1, 0, null, null, 14150, 1, NOW(), 2016, 2018, 0),
(37, 'test car 37', '', 1972, 5, 1977, 8, 1, 0, null, null, 74150, 0, NOW(), 1973, 1977, 0),
(38, 'test car 38', '', 1940, 12, 1947, 8, 1, 0, null, null, 345, 0, NOW(), 1940, 1947, 0),
(39, 'test car 39', '', 1999, 6, 2009, 4, 1, 0, null, null, 39394, 0, NOW(), 1999, 2009, 0),
(40, 'test car 40', '', 1994, 5, 1994, 11, 0, 0, null, null, 9381, 1, NOW(), 1995, 1994, 0),
(41, 'test car 41', '', 1966, 6, 1970, 5, 0, 0, null, null, 19488, 0, NOW(), 1966, 1970, 0),
(42, 'test car 42', '', 1916, 4, 1916, 12, 1, 0, null, null, 12317, 0, NOW(), 1916, 1916, 0),
(43, 'test car 43', '', 1931, 1, 1941, 8, 0, 0, null, null, 33576, 1, NOW(), 1931, 1941, 0),
(44, 'test car 44', '', 2013, 7, 2022, 9, 1, 0, null, null, 67233, 1, NOW(), 2013, 2022, 0),
(45, 'test car 45', '', 2015, 6, 2023, 8, 0, 0, null, null, 31369, 1, NOW(), 2015, 2023, 0),
(46, 'test car 46', '', 1966, 2, 1972, 12, 0, 0, null, null, 48828, 0, NOW(), 1967, 1972, 0),
(47, 'test car 47', '', 1910, 6, 1915, 10, 0, 0, null, null, 12491, 0, NOW(), 1910, 1915, 0),
(48, 'test car 48', '', 1933, 7, 1934, 9, 0, 0, null, null, 80426, 0, NOW(), 1933, 1934, 0),
(49, 'test car 49', '', 1992, 5, 1996, 8, 0, 0, null, null, 81731, 0, NOW(), 1992, 1996, 0),
(50, 'test car 50', '', 1962, 4, 1971, 3, 1, 0, null, null, 69462, 0, NOW(), 1963, 1971, 0),
(51, 'test car 51', '', 1944, 12, 1954, 3, 1, 0, null, null, 98610, 0, NOW(), 1945, 1954, 0),
(52, 'test car 52', '', 2008, 9, 2016, 12, 1, 0, null, null, 97334, 1, NOW(), 2009, 2016, 0),
(53, 'test car 53', '', 2011, 10, 2016, 10, 0, 0, null, null, 71675, 1, NOW(), 2012, 2016, 0),
(54, 'test car 54', '', 1993, 9, 1994, 3, 1, 0, null, null, 62097, 0, NOW(), 1993, 1994, 0),
(55, 'test car 55', '', 1904, 3, 1910, 3, 0, 0, null, null, 7350, 0, NOW(), 1904, 1910, 0),
(56, 'test car 56', '', 1993, 11, 1993, 4, 0, 0, null, null, 36391, 1, NOW(), 1993, 1993, 0),
(57, 'test car 57', '', 2002, 11, 2003, 1, 1, 0, null, null, 85544, 1, NOW(), 2002, 2003, 0),
(58, 'test car 58', '', 1988, 5, 1997, 12, 1, 0, null, null, 38016, 1, NOW(), 1989, 1997, 0),
(59, 'test car 59', '', 1976, 8, 1978, 3, 1, 0, null, null, 97259, 1, NOW(), 1976, 1978, 0),
(60, 'test car 60', '', 2007, 3, 2016, 11, 0, 0, null, null, 77916, 1, NOW(), 2007, 2016, 0),
(61, 'test car 61', '', 1901, 12, 1907, 8, 0, 0, null, null, 48024, 1, NOW(), 1901, 1907, 0),
(62, 'test car 62', '', 1921, 10, 1922, 10, 1, 0, null, null, 76140, 1, NOW(), 1921, 1922, 0),
(63, 'test car 63', '', 1930, 11, 1937, 3, 1, 0, null, null, 17742, 1, NOW(), 1930, 1937, 0),
(64, 'test car 64', '', 1934, 10, 1936, 11, 0, 0, null, null, 96362, 0, NOW(), 1935, 1936, 0),
(65, 'test car 65', '', 1905, 12, 1906, 1, 0, 0, null, null, 64221, 1, NOW(), 1905, 1906, 0),
(66, 'test car 66', '', 2011, 3, 2020, 6, 0, 0, null, null, 76409, 1, NOW(), 2012, 2020, 0),
(67, 'test car 67', '', 1910, 4, 1910, 5, 1, 0, null, null, 34090, 0, NOW(), 1911, 1910, 0),
(68, 'test car 68', '', 1926, 11, 1930, 9, 1, 0, null, null, 2520, 0, NOW(), 1927, 1930, 0),
(69, 'test car 69', '', 1953, 2, 1963, 2, 0, 0, null, null, 27948, 1, NOW(), 1954, 1963, 0),
(70, 'test car 70', '', 1979, 12, 1983, 10, 0, 0, null, null, 81677, 0, NOW(), 1980, 1983, 0),
(71, 'test car 71', '', 1981, 2, 1981, 1, 0, 0, null, null, 78568, 0, NOW(), 1981, 1981, 0),
(72, 'test car 72', '', 1991, 8, 1993, 3, 0, 0, null, null, 67998, 0, NOW(), 1991, 1993, 0),
(73, 'test car 73', '', 1930, 10, 1934, 3, 1, 0, null, null, 16943, 0, NOW(), 1930, 1934, 0),
(74, 'test car 74', '', 1994, 10, 2003, 6, 0, 0, null, null, 27821, 0, NOW(), 1994, 2003, 0),
(75, 'test car 75', '', 1923, 9, 1923, 11, 0, 0, null, null, 5835, 1, NOW(), 1923, 1923, 0),
(76, 'test car 76', '', 1988, 10, 1991, 5, 1, 0, null, null, 49514, 1, NOW(), 1989, 1991, 0),
(77, 'test car 77', '', 1988, 7, 1991, 8, 0, 0, null, null, 65243, 1, NOW(), 1989, 1991, 0),
(78, 'test car 78', '', 1943, 1, 1952, 7, 0, 0, null, null, 36858, 1, NOW(), 1944, 1952, 0),
(79, 'test car 79', '', 1966, 7, 1967, 5, 0, 0, null, null, 27707, 1, NOW(), 1967, 1967, 0),
(80, 'test car 80', '', 1984, 10, 1984, 9, 0, 0, null, null, 90039, 0, NOW(), 1984, 1984, 0),
(81, 'test car 81', '', 1946, 4, 1948, 6, 1, 0, null, null, 32911, 1, NOW(), 1946, 1948, 0),
(82, 'test car 82', '', 1942, 9, 1951, 11, 1, 0, null, null, 83884, 0, NOW(), 1942, 1951, 0),
(83, 'test car 83', '', 1968, 1, 1974, 7, 0, 0, null, null, 51898, 0, NOW(), 1969, 1974, 0),
(84, 'test car 84', '', 1996, 10, 2006, 5, 1, 0, null, null, 56033, 1, NOW(), 1996, 2006, 0),
(85, 'test car 85', '', 1970, 1, 1980, 4, 0, 0, null, null, 78847, 0, NOW(), 1971, 1980, 0),
(86, 'test car 86', '', 1928, 6, 1932, 6, 1, 0, null, null, 88307, 1, NOW(), 1928, 1932, 0),
(87, 'test car 87', '', 1904, 3, 1911, 7, 0, 0, null, null, 96877, 0, NOW(), 1905, 1911, 0),
(88, 'test car 88', '', 1995, 4, 2001, 6, 0, 0, null, null, 41250, 0, NOW(), 1995, 2001, 0),
(89, 'test car 89', '', 1960, 9, 1967, 8, 1, 0, null, null, 35858, 0, NOW(), 1960, 1967, 0),
(90, 'test car 90', '', 1954, 12, 1958, 7, 1, 0, null, null, 9110, 1, NOW(), 1954, 1958, 0),
(91, 'test car 91', '', 1990, 3, 2000, 2, 1, 0, null, null, 66048, 1, NOW(), 1991, 2000, 0),
(92, 'test car 92', '', 1907, 4, 1909, 6, 1, 0, null, null, 77032, 1, NOW(), 1908, 1909, 0),
(93, 'test car 93', '', 1956, 4, 1958, 8, 0, 0, null, null, 96789, 1, NOW(), 1957, 1958, 0),
(94, 'test car 94', '', 1976, 1, 1984, 12, 1, 0, null, null, 83543, 1, NOW(), 1977, 1984, 0),
(95, 'test car 95', '', 1927, 3, 1936, 9, 0, 0, null, null, 20953, 0, NOW(), 1927, 1936, 0),
(96, 'test car 96', '', 1994, 4, 2001, 4, 0, 0, null, null, 43642, 0, NOW(), 1994, 2001, 0),
(97, 'test car 97', '', 1993, 8, 1994, 7, 1, 0, null, null, 29203, 0, NOW(), 1993, 1994, 0),
(98, 'test car 98', '', 1959, 10, 1964, 7, 1, 0, null, null, 85957, 1, NOW(), 1959, 1964, 0),
(99, 'test car 99', '', 1911, 11, 1918, 2, 1, 0, null, null, 92830, 1, NOW(), 1912, 1918, 0),
(100, 'test car 100', '', 1929, 6, 1937, 11, 1, 0, null, null, 32396, 0, NOW(), 1929, 1937, 0),
(101, 'test car 101', '', 1906, 8, 1915, 2, 1, 0, null, null, 28241, 1, NOW(), 1906, 1915, 0),
(102, 'test car 102', '', 1961, 5, 1965, 11, 0, 0, null, null, 32358, 1, NOW(), 1961, 1965, 0),
(103, 'test car 103', '', 1992, 10, 1998, 11, 1, 0, null, null, 47866, 0, NOW(), 1992, 1998, 0),
(104, 'test car 104', '', 2002, 3, 2007, 3, 1, 0, null, null, 98313, 0, NOW(), 2003, 2007, 0),
(105, 'test car 105', '', 2007, 1, 2012, 12, 0, 0, null, null, 31807, 0, NOW(), 2008, 2012, 0),
(106, 'test car 106', '', 1924, 9, 1925, 7, 0, 0, null, null, 39700, 1, NOW(), 1925, 1925, 0),
(107, 'test car 107', '', 1973, 5, 1976, 11, 0, 0, null, null, 29300, 1, NOW(), 1974, 1976, 0),
(108, 'test car 108', '', 1963, 12, 1965, 6, 1, 0, null, null, 33230, 1, NOW(), 1963, 1965, 0),
(109, 'test car 109', '', 2012, 6, 2017, 6, 1, 0, null, null, 84894, 1, NOW(), 2013, 2017, 0),
(110, 'test car 110', '', 1994, 12, 1999, 9, 0, 0, null, null, 4799, 1, NOW(), 1994, 1999, 0),
(111, 'test car 111', '', 2011, 1, 2017, 4, 0, 0, null, null, 72861, 1, NOW(), 2011, 2017, 0),
(112, 'test car 112', '', 1933, 6, 1940, 8, 1, 0, null, null, 29156, 0, NOW(), 1933, 1940, 0),
(113, 'test car 113', '', 1994, 2, 1995, 12, 0, 0, null, null, 18190, 1, NOW(), 1995, 1995, 0),
(114, 'test car 114', '', 1917, 8, 1918, 5, 1, 0, null, null, 12005, 0, NOW(), 1917, 1918, 0),
(115, 'test car 115', '', 1948, 4, 1952, 1, 0, 0, null, null, 23053, 0, NOW(), 1949, 1952, 0),
(116, 'test car 116', '', 1982, 12, 1982, 10, 1, 0, null, null, 7882, 1, NOW(), 1983, 1982, 1),
(117, 'test car 117', '', 2011, 8, 2013, 3, 1, 0, null, null, 40957, 0, NOW(), 2012, 2013, 0),
(118, 'test car 118', '', 1904, 4, 1913, 2, 0, 0, null, null, 56069, 0, NOW(), 1905, 1913, 0),
(119, 'test car 119', '', 1996, 5, 1999, 3, 1, 0, null, null, 36003, 0, NOW(), 1996, 1999, 0),
(120, 'test car 120', '', 1982, 2, 1985, 6, 0, 0, null, null, 6099, 1, NOW(), 1983, 1985, 0),
(121, 'test car 121', '', 1942, 2, 1943, 5, 0, 0, null, null, 57558, 1, NOW(), 1942, 1943, 0),
(122, 'test car 122', '', 1943, 3, 1947, 2, 1, 0, null, null, 92587, 1, NOW(), 1944, 1947, 0),
(123, 'test car 123', '', 1926, 9, 1932, 11, 1, 0, null, null, 79172, 1, NOW(), 1927, 1932, 0),
(124, 'test car 124', '', 1983, 12, 1984, 6, 1, 0, null, null, 812, 0, NOW(), 1983, 1984, 0),
(125, 'test car 125', '', 1970, 3, 1974, 1, 0, 0, null, null, 11369, 1, NOW(), 1971, 1974, 0),
(126, 'test car 126', '', 1997, 1, 2000, 5, 1, 0, null, null, 92630, 0, NOW(), 1998, 2000, 0),
(127, 'test car 127', '', 2004, 8, 2011, 11, 1, 0, null, null, 23922, 1, NOW(), 2005, 2011, 0),
(128, 'test car 128', '', 1968, 6, 1977, 3, 1, 0, null, null, 76249, 0, NOW(), 1968, 1977, 0),
(129, 'test car 129', '', 1979, 10, 1985, 4, 1, 0, null, null, 77277, 0, NOW(), 1979, 1985, 0),
(130, 'test car 130', '', 2003, 12, 2010, 11, 1, 0, null, null, 46842, 0, NOW(), 2003, 2010, 0),
(131, 'test car 131', '', 1949, 8, 1949, 3, 0, 0, null, null, 34967, 1, NOW(), 1950, 1949, 0),
(132, 'test car 132', '', 1982, 10, 1982, 3, 1, 0, null, null, 50011, 1, NOW(), 1982, 1982, 0),
(133, 'test car 133', '', 2004, 4, 2008, 9, 0, 0, null, null, 78284, 0, NOW(), 2005, 2008, 0),
(134, 'test car 134', '', 1965, 5, 1967, 2, 0, 0, null, null, 74228, 0, NOW(), 1965, 1967, 0),
(135, 'test car 135', '', 1902, 10, 1906, 5, 0, 0, null, null, 47874, 1, NOW(), 1903, 1906, 0),
(136, 'test car 136', '', 1907, 11, 1911, 12, 1, 0, null, null, 37818, 1, NOW(), 1908, 1911, 0),
(137, 'test car 137', '', 1917, 11, 1927, 10, 1, 0, null, null, 651, 1, NOW(), 1917, 1927, 0),
(138, 'test car 138', '', 1904, 11, 1904, 5, 1, 1, null, null, 95000, 1, NOW(), 1905, 1904, 0),
(139, 'test car 139', '', 1994, 6, 1994, 12, 1, 0, null, null, 35767, 0, NOW(), 1994, 1994, 0),
(140, 'test car 140', '', 1960, 9, 1965, 6, 1, 0, null, null, 30649, 0, NOW(), 1961, 1965, 0),
(141, 'test car 141', '', 1932, 1, 1935, 2, 0, 0, null, null, 99928, 0, NOW(), 1932, 1935, 0),
(142, 'test car 142', '', 1914, 11, 1922, 10, 1, 0, null, null, 60864, 0, NOW(), 1914, 1922, 0),
(143, 'test car 143', '', 1924, 7, 1925, 3, 0, 0, null, null, 31250, 1, NOW(), 1925, 1925, 0),
(144, 'test car 144', '', 1956, 10, 1962, 12, 0, 0, null, null, 5217, 1, NOW(), 1957, 1962, 0),
(145, 'test car 145', '', 1916, 9, 1917, 2, 0, 0, null, null, 56843, 1, NOW(), 1916, 1917, 0),
(146, 'test car 146', '', 1953, 7, 1961, 9, 1, 0, null, null, 57739, 0, NOW(), 1953, 1961, 0),
(147, 'test car 147', '', 1907, 6, 1907, 9, 0, 0, null, null, 52175, 1, NOW(), 1908, 1907, 1),
(148, 'test car 148', '', 1901, 7, 1908, 7, 0, 0, null, null, 83318, 0, NOW(), 1902, 1908, 0),
(149, 'test car 149', '', 1993, 2, 1996, 3, 0, 0, null, null, 68020, 1, NOW(), 1994, 1996, 0),
(150, 'test car 150', '', 1913, 11, 1921, 3, 0, 0, null, null, 87949, 1, NOW(), 1914, 1921, 0),
(151, 'test car 151', '', 1929, 4, 1938, 9, 0, 0, null, null, 38619, 1, NOW(), 1929, 1938, 0),
(152, 'test car 152', '', 1942, 4, 1944, 5, 0, 0, null, null, 38831, 0, NOW(), 1942, 1944, 0),
(153, 'test car 153', '', 1998, 7, 2003, 7, 0, 0, null, null, 99622, 0, NOW(), 1999, 2003, 0),
(154, 'test car 154', '', 1915, 1, 1917, 11, 0, 0, null, null, 30791, 0, NOW(), 1916, 1917, 0),
(155, 'test car 155', '', 1989, 4, 1996, 12, 0, 0, null, null, 37656, 0, NOW(), 1990, 1996, 0),
(156, 'test car 156', '', 1946, 1, 1948, 10, 0, 0, null, null, 44604, 1, NOW(), 1946, 1948, 0),
(157, 'test car 157', '', 2005, 6, 2011, 4, 1, 0, null, null, 69810, 1, NOW(), 2005, 2011, 0),
(158, 'test car 158', '', 1920, 3, 1925, 11, 0, 0, null, null, 49364, 0, NOW(), 1921, 1925, 0),
(159, 'test car 159', '', 1940, 6, 1949, 3, 1, 0, null, null, 89837, 1, NOW(), 1940, 1949, 0),
(160, 'test car 160', '', 1978, 4, 1986, 3, 0, 0, null, null, 27525, 1, NOW(), 1979, 1986, 0),
(161, 'test car 161', '', 2005, 6, 2010, 5, 1, 0, null, null, 51032, 1, NOW(), 2005, 2010, 0),
(162, 'test car 162', '', 1928, 11, 1937, 3, 0, 0, null, null, 77256, 0, NOW(), 1929, 1937, 1),
(163, 'test car 163', '', 1997, 12, 2001, 5, 0, 0, null, null, 24535, 1, NOW(), 1997, 2001, 0),
(164, 'test car 164', '', 1925, 3, 1926, 1, 1, 0, null, null, 13995, 1, NOW(), 1926, 1926, 0),
(165, 'test car 165', '', 1960, 6, 1964, 2, 0, 0, null, null, 64585, 1, NOW(), 1960, 1964, 0),
(166, 'test car 166', '', 1948, 6, 1953, 3, 1, 0, null, null, 83250, 0, NOW(), 1948, 1953, 0),
(167, 'test car 167', '', 1968, 12, 1968, 11, 1, 0, null, null, 63721, 0, NOW(), 1969, 1968, 0),
(168, 'test car 168', '', 1930, 7, 1931, 2, 1, 0, null, null, 88340, 1, NOW(), 1931, 1931, 0),
(169, 'test car 169', '', 1951, 10, 1954, 4, 1, 0, null, null, 73137, 0, NOW(), 1952, 1954, 0),
(170, 'test car 170', '', 1934, 10, 1937, 11, 1, 0, null, null, 50811, 1, NOW(), 1935, 1937, 0),
(171, 'test car 171', '', 2005, 2, 2013, 11, 1, 0, null, null, 64062, 1, NOW(), 2005, 2013, 0),
(172, 'test car 172', '', 1940, 8, 1940, 5, 0, 0, null, null, 47267, 0, NOW(), 1941, 1940, 0),
(173, 'test car 173', '', 1932, 6, 1940, 4, 1, 0, null, null, 37812, 1, NOW(), 1932, 1940, 0),
(174, 'test car 174', '', 1911, 1, 1914, 11, 0, 0, null, null, 58402, 1, NOW(), 1912, 1914, 0),
(175, 'test car 175', '', 1925, 6, 1935, 11, 1, 0, null, null, 63615, 0, NOW(), 1925, 1935, 0),
(176, 'test car 176', '', 1911, 11, 1921, 1, 1, 0, null, null, 43197, 0, NOW(), 1912, 1921, 0),
(177, 'test car 177', '', 1949, 4, 1954, 12, 0, 0, null, null, 78699, 1, NOW(), 1950, 1954, 0),
(178, 'test car 178', '', 1981, 1, 1981, 10, 0, 0, null, null, 391, 0, NOW(), 1981, 1981, 0),
(179, 'test car 179', '', 1967, 9, 1968, 8, 1, 0, null, null, 35593, 0, NOW(), 1968, 1968, 0),
(180, 'test car 180', '', 1915, 11, 1923, 2, 1, 0, null, null, 5430, 1, NOW(), 1916, 1923, 0),
(181, 'test car 181', '', 1936, 3, 1944, 11, 1, 0, null, null, 42998, 1, NOW(), 1937, 1944, 0),
(182, 'test car 182', '', 1928, 8, 1928, 4, 0, 0, null, null, 34945, 1, NOW(), 1929, 1928, 0),
(183, 'test car 183', '', 1943, 3, 1948, 10, 0, 0, null, null, 93051, 1, NOW(), 1944, 1948, 0),
(184, 'test car 184', '', 1951, 9, 1953, 8, 1, 0, null, null, 50497, 0, NOW(), 1951, 1953, 0),
(185, 'test car 185', '', 1991, 11, 1999, 5, 1, 0, null, null, 18952, 1, NOW(), 1992, 1999, 0),
(186, 'test car 186', '', 1926, 7, 1932, 11, 0, 0, null, null, 5821, 1, NOW(), 1927, 1932, 0),
(187, 'test car 187', '', 1920, 1, 1925, 6, 1, 1, null, null, 99237, 1, NOW(), 1921, 1925, 0),
(188, 'test car 188', '', 1971, 1, 1979, 7, 1, 0, null, null, 63271, 1, NOW(), 1972, 1979, 0),
(189, 'test car 189', '', 1979, 10, 1988, 6, 0, 0, null, null, 11408, 1, NOW(), 1980, 1988, 0),
(190, 'test car 190', '', 1913, 12, 1916, 3, 1, 0, null, null, 49593, 0, NOW(), 1913, 1916, 0),
(191, 'test car 191', '', 1959, 7, 1960, 4, 0, 0, null, null, 45372, 1, NOW(), 1959, 1960, 0),
(192, 'test car 192', '', 1908, 4, 1909, 3, 1, 0, null, null, 62856, 0, NOW(), 1908, 1909, 0),
(193, 'test car 193', '', 1908, 8, 1916, 1, 0, 0, null, null, 19414, 1, NOW(), 1908, 1916, 0),
(194, 'test car 194', '', 1931, 6, 1938, 10, 0, 0, null, null, 83551, 0, NOW(), 1931, 1938, 0),
(195, 'test car 195', '', 1909, 5, 1919, 2, 0, 0, null, null, 94561, 1, NOW(), 1910, 1919, 0),
(196, 'test car 196', '', 1986, 5, 1986, 12, 0, 0, null, null, 44962, 0, NOW(), 1986, 1986, 0),
(197, 'test car 197', '', 1988, 10, 1995, 12, 1, 0, null, null, 99262, 1, NOW(), 1988, 1995, 0),
(198, 'test car 198', '', 2008, 12, 2008, 9, 0, 0, null, null, 41663, 1, NOW(), 2008, 2008, 0),
(199, 'test car 199', '', 1990, 10, 1991, 8, 0, 0, null, null, 727, 0, NOW(), 1990, 1991, 0),
(200, 'test car 200', '', 1973, 10, 1981, 4, 0, 0, null, null, 50120, 0, NOW(), 1974, 1981, 0),
(201, 'test car 201', '', 2003, 4, 2007, 7, 0, 0, null, null, 32557, 1, NOW(), 2004, 2007, 0),
(202, 'test car 202', '', 1909, 8, 1910, 7, 0, 0, null, null, 5563, 1, NOW(), 1910, 1910, 0),
(203, 'test car 203', '', 1911, 7, 1918, 9, 1, 0, null, null, 67707, 0, NOW(), 1912, 1918, 0);

-- --------------------------------------------------------

--
-- Table structure for table `cars_pictures`
--

CREATE TABLE IF NOT EXISTS `cars_pictures` (
  `car_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `picture_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`car_id`,`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE IF NOT EXISTS `category` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  `catname` varchar(35) NOT NULL,
  `split_by_brand` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1054 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB';

-- --------------------------------------------------------

--
-- Table structure for table `category_car`
--

CREATE TABLE IF NOT EXISTS `category_car` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`category_id`,`car_id`),
  KEY `car_id` (`car_id`),
  KEY `category_id` (`category_id`,`add_datetime`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `category_language`
--

CREATE TABLE IF NOT EXISTS `category_language` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  PRIMARY KEY (`category_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `category_parent`
--

CREATE TABLE IF NOT EXISTS `category_parent` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `level` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`category_id`,`parent_id`),
  KEY `FK_category_parent_category_id2` (`parent_id`)
) ENGINE=InnoDB AVG_ROW_LENGTH=45 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `comment_topic`
--

CREATE TABLE IF NOT EXISTS `comment_topic` (
  `type_id` tinyint(3) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `messages` int(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`type_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `comment_topic_view`
--

CREATE TABLE IF NOT EXISTS `comment_topic_view` (
  `type_id` tinyint(3) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`,`item_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `comment_vote`
--

CREATE TABLE IF NOT EXISTS `comment_vote` (
  `comment_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `comments_messages`
--

CREATE TABLE IF NOT EXISTS `comments_messages` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `type_id` tinyint(11) UNSIGNED NOT NULL,
  `item_id` int(11) UNSIGNED NOT NULL,
  `author_id` int(11) UNSIGNED DEFAULT NULL,
  `datetime` timestamp NOT NULL,
  `message` mediumtext NOT NULL,
  `moderator_attention` tinyint(3) UNSIGNED NOT NULL,
  `vote` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `deleted_by` int(10) UNSIGNED DEFAULT NULL,
  `replies_count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `type_id` (`type_id`,`item_id`),
  KEY `datetime_sort` (`datetime`),
  KEY `deleted_by` (`deleted_by`),
  KEY `parent_id` (`parent_id`),
  KEY `moderator_attention` (`moderator_attention`)
) ENGINE=InnoDB AUTO_INCREMENT=932834 AVG_ROW_LENGTH=266 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`author_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `comments_types`
--

CREATE TABLE IF NOT EXISTS `comments_types` (
  `id` tinyint(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE IF NOT EXISTS `contact` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `contact_user_id` int(10) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL,
  PRIMARY KEY (`user_id`,`contact_user_id`),
  KEY `contact_user_id` (`contact_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE IF NOT EXISTS `countries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  `group_id` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `countries_groups`
--

CREATE TABLE IF NOT EXISTS `countries_groups` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `day_stat`
--

CREATE TABLE IF NOT EXISTS `day_stat` (
  `day_date` date NOT NULL,
  `hits` mediumint(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`day_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `engine_parent_cache`
--

CREATE TABLE IF NOT EXISTS `engine_parent_cache` (
  `engine_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`engine_id`,`parent_id`),
  KEY `parent_fk` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `engines`
--

CREATE TABLE IF NOT EXISTS `engines` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `caption` varchar(100) NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `last_editor_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `last_editor_id` (`last_editor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1748 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`brand_id`)';

REPLACE INTO engines (id, parent_id, caption, owner_id, last_editor_id)
VALUES (1, null, "Test engine", 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `factory`
--

CREATE TABLE IF NOT EXISTS `factory` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `year_from` smallint(5) UNSIGNED DEFAULT NULL,
  `year_to` smallint(5) UNSIGNED DEFAULT NULL,
  `point` point DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `text_id` (`text_id`),
  KEY `point` (`point`)
) ;

REPLACE INTO factory (id, name, year_from, year_to, point, text_id)
VALUES (1, "Test factory", 1999, 2005, null, null);

-- --------------------------------------------------------

--
-- Table structure for table `factory_car`
--

CREATE TABLE IF NOT EXISTS `factory_car` (
  `factory_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`factory_id`,`car_id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO factory_car (factory_id, car_id)
VALUES (1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `formated_image`
--

CREATE TABLE IF NOT EXISTS `formated_image` (
  `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `format` varchar(255) NOT NULL,
  `formated_image_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`image_id`,`format`),
  KEY `formated_image_id` (`formated_image_id`,`image_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3617324 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `forums_theme_parent`
--

CREATE TABLE IF NOT EXISTS `forums_theme_parent` (
  `forum_theme_id` int(11) UNSIGNED NOT NULL,
  `parent_id` int(11) UNSIGNED NOT NULL,
  `level` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`forum_theme_id`,`parent_id`),
  KEY `FK_forum_theme_parent_forums_themes_id2` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `forums_themes`
--

CREATE TABLE IF NOT EXISTS `forums_themes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `folder` varchar(30) NOT NULL DEFAULT '',
  `caption` varchar(50) NOT NULL DEFAULT '',
  `position` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `description` tinytext NOT NULL,
  `topics` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `messages` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `is_moderator` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `disable_topics` tinyint(4) UNSIGNED DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder` (`folder`),
  UNIQUE KEY `caption` (`caption`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 AVG_ROW_LENGTH=1170 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB';

replace into forums_themes (id, parent_id, folder, caption, position, description, topics, messages, is_moderator, disable_topics)
values (1, null, "test", "Test", 1, "That is test theme", 1, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `forums_topics`
--

CREATE TABLE IF NOT EXISTS `forums_topics` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `theme_id` int(11) UNSIGNED DEFAULT '0',
  `caption` varchar(100) NOT NULL DEFAULT '',
  `author_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `add_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `_messages` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `views` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('normal','closed','deleted') NOT NULL DEFAULT 'normal',
  `author_ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `theme_id` (`theme_id`),
  KEY `author_id` (`author_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3077 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`theme_id`)';

replace into forums_topics (id, theme_id, caption, author_id, status, author_ip)
values (1, 1, "Test topic", 1, "normal", 0);

-- --------------------------------------------------------

--
-- Table structure for table `forums_topics_subscribers`
--

CREATE TABLE IF NOT EXISTS `forums_topics_subscribers` (
  `topic_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`topic_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `htmls`
--

CREATE TABLE IF NOT EXISTS `htmls` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `html` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB';

-- --------------------------------------------------------

--
-- Table structure for table `image`
--

CREATE TABLE IF NOT EXISTS `image` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `filepath` varchar(255) NOT NULL,
  `filesize` int(10) UNSIGNED NOT NULL,
  `width` int(10) UNSIGNED NOT NULL,
  `height` int(10) UNSIGNED NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dir` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filepath`,`dir`),
  KEY `image_dir_id` (`dir`)
) ENGINE=InnoDB AUTO_INCREMENT=3617343 DEFAULT CHARSET=utf8;

REPLACE into image (id, filepath, filesize, width, height, date_add, dir)
values (1, "1.jpg", 242405, 1200, 800, NOW(), "picture"),
(33, "2.jpg", 242405, 1200, 800, NOW(), "picture"),
(35, "3.jpg", 242405, 1200, 800, NOW(), "picture"),
(37, "4.jpg", 242405, 1200, 800, NOW(), "picture"),
(38, "5.jpg", 242405, 1200, 800, NOW(), "picture");

-- --------------------------------------------------------

--
-- Table structure for table `image_dir`
--

CREATE TABLE IF NOT EXISTS `image_dir` (
  `dir` varchar(255) NOT NULL,
  `count` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ip_monitoring4`
--

CREATE TABLE IF NOT EXISTS `ip_monitoring4` (
  `day_date` date NOT NULL,
  `hour` tinyint(3) UNSIGNED NOT NULL,
  `tenminute` tinyint(3) UNSIGNED NOT NULL,
  `minute` tinyint(3) UNSIGNED NOT NULL,
  `count` int(10) UNSIGNED NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`,`day_date`,`hour`,`tenminute`,`minute`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ip_whitelist`
--

CREATE TABLE IF NOT EXISTS `ip_whitelist` (
  `description` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `lang_pages`
--

CREATE TABLE IF NOT EXISTS `lang_pages` (
  `page_id` int(10) UNSIGNED NOT NULL,
  `language_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`page_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE IF NOT EXISTS `languages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `locale` varchar(10) NOT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `locale` (`locale`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `links`
--

CREATE TABLE IF NOT EXISTS `links` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` enum('default','official','helper','club') NOT NULL DEFAULT 'default' COMMENT 'Г’ГЁГЇ',
  `brandId` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `url` varchar(100) NOT NULL COMMENT 'Г Г¤Г°ГҐГ±',
  `caption` varchar(250) NOT NULL COMMENT 'ГЌГ Г§ГўГ Г­ГЁГҐ',
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brandId`)
) ENGINE=InnoDB AUTO_INCREMENT=1040 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events`
--

CREATE TABLE IF NOT EXISTS `log_events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `add_datetime` timestamp NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `add_datetime` (`add_datetime`)
) ENGINE=InnoDB AUTO_INCREMENT=2775694 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_articles`
--

CREATE TABLE IF NOT EXISTS `log_events_articles` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`article_id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_brands`
--

CREATE TABLE IF NOT EXISTS `log_events_brands` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `brand_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`brand_id`),
  KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_cars`
--

CREATE TABLE IF NOT EXISTS `log_events_cars` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`car_id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_engines`
--

CREATE TABLE IF NOT EXISTS `log_events_engines` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `engine_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`engine_id`),
  KEY `engine_id` (`engine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_factory`
--

CREATE TABLE IF NOT EXISTS `log_events_factory` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `factory_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`factory_id`),
  KEY `factory_id` (`factory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_pictures`
--

CREATE TABLE IF NOT EXISTS `log_events_pictures` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `picture_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`picture_id`),
  KEY `picture_id` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_twins_groups`
--

CREATE TABLE IF NOT EXISTS `log_events_twins_groups` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `twins_group_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`twins_group_id`),
  KEY `twins_group_id` (`twins_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log_events_user`
--

CREATE TABLE IF NOT EXISTS `log_events_user` (
  `log_event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`log_event_id`,`user_id`),
  KEY `FK_log_events_user_users_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `login_state`
--

CREATE TABLE IF NOT EXISTS `login_state` (
  `state` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `language` varchar(2) NOT NULL,
  `time` timestamp NOT NULL,
  `service` varchar(50) NOT NULL,
  PRIMARY KEY (`state`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE IF NOT EXISTS `message` (
  `id` int(11) UNSIGNED NOT NULL,
  `account_id` int(11) UNSIGNED NOT NULL,
  `with_account_id` int(11) UNSIGNED NOT NULL,
  `by_account_id` int(11) UNSIGNED NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `modification`
--

CREATE TABLE IF NOT EXISTS `modification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `modification_group`
--

CREATE TABLE IF NOT EXISTS `modification_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `modification_picture`
--

CREATE TABLE IF NOT EXISTS `modification_picture` (
  `modification_id` int(11) NOT NULL,
  `picture_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`modification_id`,`picture_id`),
  KEY `picture_id` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `modification_value`
--

CREATE TABLE IF NOT EXISTS `modification_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modification_id` int(11) NOT NULL,
  `value` varchar(100) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value` (`value`,`modification_id`),
  KEY `modification_id` (`modification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `museum`
--

CREATE TABLE IF NOT EXISTS `museum` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `_lat` double DEFAULT NULL,
  `_lng` double DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `description` text CHARACTER SET ucs2 NOT NULL,
  `address` text NOT NULL,
  `img` int(10) UNSIGNED DEFAULT NULL,
  `point` point DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `point` (`point`)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `of_day`
--

CREATE TABLE IF NOT EXISTS `of_day` (
  `day_date` date NOT NULL,
  `picture_id` int(10) UNSIGNED DEFAULT NULL,
  `car_id` int(10) UNSIGNED DEFAULT NULL,
  `twitter_sent` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`day_date`),
  KEY `of_day_fk` (`picture_id`),
  KEY `FK_of_day_cars_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`picture_id`)';

INSERT INTO of_day (day_date, car_id, twitter_sent)
VALUES (CURDATE(), 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `page_language`
--

CREATE TABLE IF NOT EXISTS `page_language` (
  `page_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `breadcrumbs` varchar(100) NOT NULL,
  PRIMARY KEY (`page_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `breadcrumbs` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_group_node` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `registered_only` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `class` varchar(30) DEFAULT NULL,
  `guest_only` tinyint(3) UNSIGNED NOT NULL,
  `position` smallint(6) NOT NULL,
  `inherit_blocks` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_position` (`parent_id`,`position`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `pages` (`id`, `parent_id`, `name`, `title`, `breadcrumbs`, `url`, `is_group_node`, `registered_only`, `class`, `guest_only`, `position`, `inherit_blocks`) VALUES
(1, NULL, 'Index page', 'Encyclopedia of cars in the pictures. AutoWP.ru', '', '/', 0, 0, '', 0, 1, 0),
(2, 1, 'Главное меню', '', '', '', 1, 0, '', 0, 1, 0),
(10, 1, 'Brand', '%BRAND_NAME%', '%BRAND_NAME%', '/%BRAND_CATNAME%/', 0, 0, '', 0, 1045, 0),
(14, 10, '%BRAND_NAME% cars in chronological order', '%BRAND_NAME% cars in chronological order', 'Cars in chronological order', '/%BRAND_CATNAME%/cars/', 0, 0, '', 0, 913, 1),
(15, 10, 'Last pictures of %BRAND_NAME%', 'Last pictures of %BRAND_NAME%', 'Last pictures', '/%BRAND_CATNAME%/recent/', 0, 0, '', 0, 918, 1),
(18, 1, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/picture/%PICTURE_ID%', 0, 0, '', 0, 1021, 0),
(19, 1, 'Brands', 'Brands', 'Brands', '', 0, 0, '', 0, 1035, 0),
(20, 19, 'Тип производителей', '', NULL, NULL, 0, 0, NULL, 0, 1, 1),
(21, 2, 'Mostly', 'Mostly', '', '/mosts', 0, 0, '', 0, 24, 0),
(22, 2, 'Categories', 'Categories', '', '/category', 0, 0, '', 0, 25, 1),
(23, 22, '%CATEGORY_NAME%', '%CATEGORY_NAME%', '%CATEGORY_SHORT_NAME%', '/category/%CATEGORY_CATNAME%', 0, 0, '/category/%CATEGORY_CATNAME%', 0, 1, 1),
(24, 1, 'Лимитированные и специальные серии автомобилей', '', '', '/limitededitions/', 0, 0, '', 0, 777, 0),
(25, 2, 'Twins', '', '', '/twins', 0, 0, '', 0, 23, 0),
(26, 25, '%TWINS_GROUP_NAME%', '%TWINS_GROUP_NAME%', '%TWINS_GROUP_NAME%', '/twins/group%TWINS_GROUP_ID%', 0, 0, '', 0, 6, 1),
(27, 26, 'Specifications of %TWINS_GROUP_NAME%', 'Specifications of %TWINS_GROUP_NAME%', 'Specifications', '/twins/group%TWINS_GROUP_ID%/specifications', 0, 0, '', 0, 9, 1),
(28, 26, 'All pictures of %TWINS_GROUP_NAME%', 'All pictures of %TWINS_GROUP_NAME%', 'All pictures', '/twins/group%TWINS_GROUP_ID%/pictures', 0, 0, '', 0, 7, 1),
(29, 87, 'Add picture', 'Add picture', '', '/upload/', 0, 1, '', 0, 18, 0),
(30, 29, 'Select brand', 'Select brand', 'Select brand', '', 0, 0, '', 0, 5, 1),
(31, 1, 'Articles', 'Articles', 'Articles', '/articles/', 0, 0, '', 0, 1046, 1),
(32, 31, '%ARTICLE_NAME%', '%ARTICLE_NAME%', '%ARTICLE_NAME%', '', 0, 0, '/articles/%ARTICLE_CATNAME%/', 0, 1, 1),
(33, 10, '%CAR_NAME%', '%CAR_NAME%', '%SHORT_CAR_NAME%', '/%BRAND_CATNAME%/%CAR_CATNAME%/', 0, 0, '', 0, 909, 1),
(34, 33, 'All pictures of %CAR_NAME%', 'All pictures of %CAR_NAME%', 'All pictures', '/%BRAND_CATNAME%/%CAR_CATNAME%/pictures/', 0, 0, '', 0, 13, 1),
(36, 33, 'Specifications of %CAR_NAME%', 'Specifications of %CAR_NAME%', 'Specifications', '/%BRAND_CATNAME%/%CAR_CATNAME%/specifications/', 0, 0, '', 0, 14, 1),
(37, 10, 'Concepts & prototypes', 'Concepts & prototypes', 'Concepts & prototypes', '/%BRAND_CATNAME%/concepts/', 0, 0, '', 0, 915, 1),
(38, 10, '%BRAND_NAME% engines', '%BRAND_NAME% engines', 'Engines', '/%BRAND_CATNAME%/engines/', 0, 0, '', 0, 914, 1),
(39, 10, '%BRAND_NAME% logotypes', '%BRAND_NAME% logotypes', 'Logotypes', '/%BRAND_CATNAME%/logotypes/', 0, 0, '', 0, 916, 1),
(40, 10, '%BRAND_NAME% miscellaneous', '%BRAND_NAME% miscellaneous', 'Miscellaneous', '/%BRAND_CATNAME%/mixed/', 0, 0, '', 0, 917, 1),
(41, 10, 'Unsorted', 'Unsorted', 'Unsorted', '/%BRAND_CATNAME%/other/', 0, 0, '', 0, 920, 1),
(42, 2, 'Forums', 'Forums', '', '/forums', 0, 0, '', 0, 27, 0),
(43, 42, '%THEME_NAME%', '%THEME_NAME%', '%THEME_NAME%', '/forums/index/%THEME_ID%', 0, 0, '', 0, 1, 1),
(44, 43, '%TOPIC_NAME%', '%TOPIC_NAME%', '%TOPIC_NAME%', '/forums/topic/topic/topic_id/%TOPIC_ID%', 0, 0, '', 0, 5, 1),
(45, 43, 'New topic', 'New topic', 'New topic', '/forums/topic/new/theme_id/%THEME_ID%', 0, 0, '', 0, 4, 1),
(48, 87, 'Cabinet', 'Cabinet', '', '/account', 0, 1, '', 0, 27, 0),
(49, 48, 'Personal messages', 'Personal messages', '', '/account/pm', 0, 0, '', 0, 23, 1),
(51, 1, 'New pictures', '', '', '/new', 0, 0, '', 0, 1036, 0),
(52, 1, 'Registration', 'Registration', '', '/registration', 0, 0, '', 0, 1056, 0),
(53, 52, 'ok', 'Успешная регистрация', '', '', 0, 0, '', 0, 1, 1),
(54, 48, 'Confirm the email address', 'Confirm the email address', '', '', 0, 0, '', 0, 21, 1),
(55, 48, 'My e-mail', 'My e-mail', '', '/account/email', 0, 1, '', 0, 26, 1),
(56, 55, 'Changed', 'Changing e-mail', '', '', 0, 1, '', 0, 1, 1),
(57, 48, 'Forums subscriptions', 'Forums subscriptions', '', '/account/forums', 0, 1, '', 0, 31, 1),
(58, 10, '%BRAND_NAME% %DPBRAND_NAME%', '%BRAND_NAME% %DPBRAND_NAME%', '%DPBRAND_NAME%', '/%BRAND_CATNAME%/%DPBRAND_CATNAME%/', 0, 0, '', 0, 901, 1),
(59, 10, '%BRAND_NAME% %DESIGN_PROJECT_NAME%', '%BRAND_NAME% %DESIGN_PROJECT_NAME%', '%DESIGN_PROJECT_NAME%', '/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/', 0, 0, '', 0, 902, 1),
(60, 1, 'Password recovery', 'Password recovery', '', '', 0, 0, '', 0, 1038, 0),
(61, 1, 'All brands', '', '', '/brands/', 0, 0, '', 0, 1039, 0),
(62, 1, '%USER_NAME%', '%USER_NAME%', '%USER_NAME%', '/users/%USER_IDENTITY%', 0, 0, '', 0, 1020, 1),
(63, 62, 'User\'s pictures', 'User\'s pictures', 'Pictures', '/users/%USER_IDENTITY%', 0, 0, '', 0, 1, 1),
(66, 59, 'All pictures of %BRAND_NAME% %DESIGN_PROJECT_NAME%', 'All pictures of %BRAND_NAME% %DESIGN_PROJECT_NAME%', 'All pictures', '/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/pictures/', 0, 0, '', 0, 1, 1),
(67, 1, 'Moderator page', '', '', '/moder', 0, 0, '', 0, 1040, 0),
(68, 67, 'Страницы сайта', '', '', '/moder/pages', 0, 0, NULL, 0, 1, 1),
(69, 68, 'Добавить', '', '', '', 0, 0, NULL, 0, 1, 1),
(70, 68, 'Изменить', '', '', '', 0, 0, NULL, 0, 2, 1),
(71, 67, 'Права', '', '', '/moder/rights', 0, 0, NULL, 0, 2, 1),
(72, 73, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/moder/pictures/picture/picture_id/%PICTURE_ID%', 0, 1, '', 0, 3, 1),
(73, 67, 'Картинки', '', '', '/moder/pictures', 0, 1, '', 0, 20, 1),
(74, 67, 'Автомобили по алфавиту', '', '', '/moder/alpha-cars', 0, 0, NULL, 0, 5, 1),
(75, 67, 'Журнал событий', '', '', '/log/', 0, 0, NULL, 0, 6, 1),
(76, 1, 'Немодерированное', 'Немодерированное', '', '', 0, 0, '', 0, 1053, 0),
(77, 67, 'Трафик', '', '', '/moder/trafic', 0, 0, NULL, 0, 7, 1),
(78, 131, '%CAR_NAME%', '%CAR_NAME%', '%CAR_NAME%', '/moder/cars/car/car_id/%CAR_ID%', 0, 1, '', 0, 26, 1),
(79, 1, 'Sign in', 'Sign in', '', '/login', 0, 0, '', 1, 1058, 0),
(80, 49, 'Sent', 'Sent', '', '/account/pm/sent', 0, 0, '', 0, 15, 1),
(81, 49, 'System messages', 'System messages', '', '/account/pm/system', 0, 0, '', 0, 18, 1),
(82, 67, 'Engines', 'Engines', 'Engines', '/moder/engines', 0, 1, '', 0, 27, 1),
(83, 44, 'Move', 'Move', 'Move', '/forums/topic/move/topic_id/%TOPIC_ID%', 0, 0, '', 0, 1, 1),
(85, 67, '%BRAND_NAME%', '%BRAND_NAME%', '%BRAND_NAME%', '/moder/brands/brand/brand_id/%BRAND_ID%', 0, 1, '', 0, 24, 1),
(86, 29, 'Image successfully uploaded to the site', 'Image successfully uploaded to the site', 'Success', '/upload/success', 0, 0, '', 0, 6, 1),
(87, 1, 'More', 'More', '', '', 1, 0, '', 0, 1043, 1),
(89, 87, 'Feedback', '', '', '/feedback', 0, 0, '', 0, 19, 0),
(90, 87, 'Sign out', '', '', '/login/logout', 0, 1, '', 0, 28, 1),
(91, 87, 'Registration', '', '', '/registration', 0, 0, '', 1, 4, 1),
(93, 89, 'Message sent', '', '', '', 0, 0, '', 0, 0, 1),
(94, 48, 'Unmoderated', 'Unmoderated', '', '/account/not-taken-pictures', 0, 1, '', 0, 25, 1),
(96, 67, 'Автомобили-близнецы', '', '', '', 0, 1, '', 0, 11, 1),
(97, 67, 'Ракурсы', '', '', '', 0, 1, '', 0, 12, 1),
(100, 67, 'Аттрибуты', '', '', '/moder/attrs', 0, 1, '', 0, 14, 1),
(101, 100, '%ATTR_NAME%', '%ATTR_NAME%', '%ATTR_NAME%', '', 0, 1, '', 0, 1, 1),
(102, 1, 'Specs editors %CAR_NAME%', 'Specs editors %CAR_NAME%', 'Specs editors', '', 0, 0, '', 0, 1047, 1),
(103, 102, 'История изменения', 'История изменения', '', '/moder/index/attrs-change-log', 0, 1, '', 0, 18, 1),
(104, 1, 'Пользовательская статистика', '', '', '', 0, 0, '', 0, 1000, 0),
(105, 1, 'Add a comment', 'Add a comment', '', '', 0, 0, '', 0, 1041, 0),
(106, 1, 'Rules', 'Rules', '', '/rules', 0, 0, '', 0, 1042, 0),
(107, 67, 'Заявки на удаление', 'Заявки на удаление', '', '', 0, 0, '', 0, 15, 1),
(109, 1, 'Cutaway', '', 'Cutaway', '/cutaway', 0, 0, '', 0, 1003, 0),
(110, 67, 'Комментарии', 'Комментарии', '', '/moder/comments', 0, 1, '', 0, 16, 1),
(111, 1, 'Engine spec editor %ENGINE_NAME%', 'Engine spec editor %ENGINE_NAME%', 'Engine spec editor', '', 0, 0, '', 0, 1048, 1),
(114, 67, 'Журнал ТТХ', 'Журнал ТТХ', '', '/moder/spec', 0, 1, '', 0, 17, 1),
(115, 67, 'Музеи', 'Музеи', 'Музеи', '/moder/museum', 0, 1, '', 0, 18, 1),
(116, 115, 'Музей', 'Музей', '%MUSEUM_NAME%', '/moder/museum/edit/museum_id/%MUSEUM_ID%/', 0, 1, '', 0, 1, 1),
(117, 2, 'Map', 'Map', '', '/map', 0, 0, '', 0, 26, 0),
(118, 115, 'Новый', 'Новый', 'Новый', '/moder/museum/new', 0, 1, '', 0, 2, 1),
(119, 67, 'Статистика', '', '', '/moder/index/stat', 0, 1, '', 0, 19, 1),
(120, 68, 'Блоки', '', '', '', 0, 1, '', 0, 3, 1),
(122, 1, 'Specifications', 'Specifications', 'Specifications', '/spec/', 0, 0, '', 0, 1057, 1),
(123, 48, 'My accounts', 'My accounts', 'My accounts', '/profile/accounts/', 0, 1, '', 0, 22, 0),
(124, 87, 'Who is online?', '', '', '/users/online', 0, 0, 'online', 0, 24, 1),
(125, 67, 'Categories', 'Categories', '', '/moder/category', 0, 1, '', 0, 22, 1),
(126, 125, 'Add', 'Add', '', '/moder/category/new/', 0, 1, '', 0, 1, 1),
(127, 125, 'Edit', 'Edit', '', '', 0, 1, '', 0, 3, 1),
(128, 49, 'Inbox', 'Inbox', '', '/account/pm', 0, 1, '', 0, 17, 0),
(129, 48, 'Profile', 'Profile', '', '/account/profile', 0, 1, '', 0, 12, 0),
(130, 48, 'My pictures', 'My pictures', '', '', 0, 1, '', 0, 30, 0),
(131, 67, 'Vehicles', 'Vehicles', 'Vehicles', '/moder/cars', 0, 1, '', 0, 26, 1),
(133, 48, 'Access', 'Access Control', '', '/account/access', 0, 1, '', 0, 27, 1),
(134, 60, 'New password', 'New password', '', '', 0, 0, '', 0, 4, 0),
(135, 60, 'New password saved', '', '', '', 0, 1, '', 0, 5, 0),
(136, 87, 'About us', 'About us', '', '/about', 0, 0, '', 0, 29, 1),
(137, 48, 'Account delete', '', '', '/account/delete', 0, 1, '', 0, 28, 1),
(138, 14, '%BRAND_NAME% %CAR_TYPE_NAME% in chronological order', '%BRAND_NAME% %CAR_TYPE_NAME% in chronological order', '%CAR_TYPE_NAME%', '/%BRAND_CATNAME%/cars/%CAR_TYPE_CATNAME%/', 0, 0, '', 0, 1, 0),
(140, 61, '%BRAND_TYPE_NAME%', '%BRAND_TYPE_NAME%', '%BRAND_TYPE_NAME%', '/brands/%BRAND_TYPE_NAME%', 0, 0, '', 0, 1, 1),
(141, 63, '%BRAND_NAME% pictures', '%BRAND_NAME% pictures', '%BRAND_NAME% pictures', '/users/%USER_IDENTITY%/pictures/%BRAND_CATNAME%', 0, 0, '', 0, 1, 1),
(142, 100, '%ATTR_ITEMTYPE_NAME% %ZONE_NAME%', '%ATTR_ITEMTYPE_NAME% %ZONE_NAME%', '%ATTR_ITEMTYPE_NAME% %ZONE_NAME%', '/moder/attrs/zone/zone_id/%ZONE_ID%', 0, 1, '', 0, 4, 1),
(143, 96, '%TWINS_GROUP_NAME%', '%TWINS_GROUP_NAME%', '%TWINS_GROUP_NAME%', '/moder/twins/twins-group/twins_group_id/%TWINS_GROUP_ID%', 0, 1, '', 0, 1, 1),
(144, 78, 'Brand selection', 'Brand selection', 'Brand selection', '', 0, 1, '', 0, 1, 1),
(146, 78, 'Twins group selection', 'Twins group selection', 'Twins group selection', '', 0, 1, '', 0, 5, 1),
(147, 78, 'Design project selection', 'Design project selection', 'Design project selection', '', 0, 1, '', 0, 7, 1),
(148, 72, 'Cropper', 'Cropper', 'Cropper', '', 0, 1, '', 0, 1, 1),
(149, 72, 'Move picture', 'Move picture', 'Move picture', '', 0, 1, '', 0, 12, 1),
(153, 25, '%BRAND_NAME% Twins', '%BRAND_NAME% Twins', '%BRAND_NAME%', '/twins/%BRAND_CATNAME%', 0, 0, '', 0, 7, 1),
(154, 21, '%MOST_NAME%', '%MOST_NAME%', '%MOST_NAME%', '/mosts/%MOST_CATNAME%', 0, 0, '', 0, 1, 1),
(155, 154, 'Most %MOST_NAME% %CAR_TYPE_NAME%', 'Most %MOST_NAME% %CAR_TYPE_NAME%', '%CAR_TYPE_NAME%', '/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%', 0, 0, '', 0, 1, 1),
(156, 155, 'Most %MOST_NAME% %CAR_TYPE_NAME% %YEAR_NAME%', 'Most %MOST_NAME% %CAR_TYPE_NAME% %YEAR_NAME%', '%YEAR_NAME%', '/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%/%YEAR_CATNAME%', 0, 0, '', 0, 1, 1),
(157, 1, '%VOTING_NAME%', '%VOTING_NAME%', '%VOTING_NAME%', '/voting/voting/id/%VOTING_ID%', 0, 0, '', 0, 1022, 0),
(159, 117, 'Museum', '%MUSEUM_NAME%', '%MUSEUM_NAME%', '/museums/museum/id/%MUSEUM_ID%', 0, 0, '', 0, 1, 0),
(161, 1, 'Pulse', 'Pulse', 'Pulse', '/pulse/', 0, 0, '', 0, 1049, 0),
(162, 23, 'Pictures', 'Pictures', '', '/category/%CATEGORY_CATNAME%/pictures', 0, 0, '', 0, 4, 0),
(163, 131, 'New vehicle', 'New vehicle', 'New vehicle', '', 0, 0, '', 0, 28, 0),
(164, 10, 'Mosts', 'Mosts', 'Mosts', '/%BRAND_CATNAME%/mosts/', 0, 0, '', 0, 919, 0),
(165, 164, 'Most %MOST_NAME% %BRAND_NAME%', 'Most %MOST_NAME% %BRAND_NAME%', '%MOST_NAME%', '/%BRAND_CATNAME%/mosts/%MOST_CATNAME%', 0, 0, '', 0, 1, 0),
(166, 165, 'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME%', 'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME%', '%CAR_TYPE_NAME%', '/%BRAND_CATNAME%/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%', 0, 0, '', 0, 1, 0),
(167, 166, 'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME% %YEAR_NAME%', 'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME% %YEAR_NAME%', '%YEAR_NAME%', '/%BRAND_CATNAME%/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%/%YEAR_CATNAME%', 0, 0, '', 0, 1, 0),
(168, 38, '%ENGINE_NAME% engine', '%ENGINE_NAME% engine', '%ENGINE_NAME% engine', '/%BRAND_CATNAME%/engines/%ENGINE_ID%/', 0, 0, '', 0, 913, 0),
(169, 82, 'Engine %ENGINE_NAME%', 'Engine %ENGINE_NAME%', 'Engine %ENGINE_NAME%', '/moder/engines/engine_id/%ENGINE_ID%/', 0, 0, '', 0, 1, 0),
(170, 82, 'Add', 'Add', 'Add', '/moder/engines/add', 0, 0, '', 0, 3, 0),
(171, 169, 'Select parent', 'Select parent', 'Select parent', '', 0, 0, '', 0, 1, 0),
(172, 168, 'Vehicles with engine %ENGINE_NAME%', 'Vehicles with engine %ENGINE_NAME%', 'Vehicles', '', 0, 0, '', 0, 4, 0),
(173, 1, 'Statistics', 'Statistics', 'Statistics', '/users/rating', 0, 0, '', 0, 1050, 0),
(174, 1, 'Specs', 'Specs', 'Specs', '/info/spec', 0, 0, '', 0, 1051, 0),
(175, 67, 'Factories', 'Factories', 'Factories', '/moder/factory', 0, 0, '', 0, 29, 0),
(176, 175, 'Add', 'Add', 'Add', '/moder/factory/add', 0, 0, '', 0, 1, 0),
(177, 175, '%FACTORY_NAME%', '%FACTORY_NAME%', '%FACTORY_NAME%', '', 0, 0, '', 0, 3, 0),
(178, 78, 'Factory selection', 'Factory selection', 'Factory selection', '', 0, 0, '', 0, 9, 0),
(180, 1, 'Factories', 'Factories', 'Factories', '/factory', 0, 0, '', 0, 1052, 0),
(181, 117, '%FACTORY_NAME%', '%FACTORY_NAME%', '%FACTORY_NAME%', '/factory/factory/id/%FACTORY_ID%', 0, 0, '', 0, 2, 0),
(182, 181, 'Vehicles', 'Vehicles', 'Vehicles', '/factory/factory-cars/id/%FACTORY_ID%', 0, 0, '', 0, 1, 0),
(183, 28, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/twins/group%TWINS_GROUP_ID%/pictures/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(184, 162, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/category/%CATEGORY_CATNAME%/pictures/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(185, 23, '%CAR_NAME%', '%CAR_NAME%', '%CAR_NAME%', '/category/%CATEGORY_CATNAME%/%CAR_ID%', 0, 0, '', 0, 3, 0),
(186, 185, 'Pictures', 'Pictures', 'Pictures', '/category/%CATEGORY_CATNAME%/%CAR_ID%/pictures', 0, 0, '', 0, 1, 0),
(187, 186, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/category/%CATEGORY_CATNAME%/%CAR_ID%/pictures/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(188, 48, 'Conflicts', 'Conflicts', 'Conflicts', '/account/specs-conflics', 0, 1, '', 0, 29, 0),
(189, 102, 'Low weight', 'Low weight', 'Low weight', '', 0, 0, '', 0, 17, 0),
(190, 40, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/%BRAND_CATNAME%/mixed/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(191, 41, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/%BRAND_CATNAME%/other/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(192, 39, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/%BRAND_CATNAME%/logotypes/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(193, 66, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(194, 34, '%PICTURE_NAME%', '%PICTURE_NAME%', '%PICTURE_NAME%', '/%BRAND_CATNAME%/%CAR_CATNAME%/pictures/%PICTURE_ID%', 0, 0, '', 0, 1, 0),
(195, 168, '%ENGINE_NAME% pictures', '%ENGINE_NAME% pictures', '%ENGINE_NAME% pictures', '', 0, 0, '', 0, 3, 0),
(196, 1, 'Donate', 'Donate', 'Donate', '/donate', 0, 0, '', 0, 1061, 0),
(197, 1, 'Text history', 'Text history', 'Text history', '/info/text', 0, 0, '', 0, 1064, 0),
(198, 48, 'Contacts', 'Contacts', 'Contacts', '/account/contacts', 0, 1, '', 0, 33, 0),
(201, 1, 'Mascots', 'Mascots', 'Mascots', '/mascots', 0, 0, '', 0, 1065, 1),
(202, 67, 'Perspectives', 'Perspectives', 'Perspectives', '/moder/perspectives', 0, 0, '', 0, 30, 1),
(203, 67, 'Users', 'Users', 'Users', '/moder/users', 0, 1, '', 0, 31, 1),
(204, 1, 'Telegram', 'Telegram', 'Telegram', '/telegram', 0, 0, '', 0, 1066, 1),
(205, 62, 'User\'s comments', 'User\'s comments', 'Comments', '/users/%USER_IDENTITY%/comments', 0, 0, '', 0, 2, 1);


-- --------------------------------------------------------

--
-- Table structure for table `personal_messages`
--

CREATE TABLE IF NOT EXISTS `personal_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` int(10) UNSIGNED DEFAULT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `contents` mediumtext NOT NULL,
  `add_datetime` timestamp NOT NULL,
  `readen` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `deleted_by_from` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `deleted_by_to` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`,`readen`),
  KEY `IX_personal_messages` (`from_user_id`,`to_user_id`,`readen`,`deleted_by_to`),
  KEY `IX_personal_messages2` (`to_user_id`,`from_user_id`,`deleted_by_to`)
) ENGINE=InnoDB AUTO_INCREMENT=1031214 AVG_ROW_LENGTH=281 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB';

-- --------------------------------------------------------

--
-- Table structure for table `perspective_language`
--

CREATE TABLE IF NOT EXISTS `perspective_language` (
  `perspective_id` int(11) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`perspective_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `perspectives`
--

CREATE TABLE IF NOT EXISTS `perspectives` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_2` (`position`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `perspectives_groups`
--

CREATE TABLE IF NOT EXISTS `perspectives_groups` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `position` tinyint(11) UNSIGNED NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_id` (`page_id`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `perspectives_groups_perspectives`
--

CREATE TABLE IF NOT EXISTS `perspectives_groups_perspectives` (
  `group_id` int(11) UNSIGNED NOT NULL,
  `perspective_id` int(11) UNSIGNED NOT NULL,
  `position` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`perspective_id`,`group_id`),
  UNIQUE KEY `position` (`position`,`group_id`),
  KEY `FK_perspectives_groups_perspectives_perspectives_groups_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `perspectives_pages`
--

CREATE TABLE IF NOT EXISTS `perspectives_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `picture_view`
--

CREATE TABLE IF NOT EXISTS `picture_view` (
  `picture_id` int(10) UNSIGNED NOT NULL,
  `views` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `picture_votes_ips`
--

CREATE TABLE IF NOT EXISTS `picture_votes_ips` (
  `picture_id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(15) NOT NULL,
  `vote_datetime` timestamp NOT NULL,
  `vote` int(11) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`picture_id`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `pictures`
--

CREATE TABLE IF NOT EXISTS `pictures` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `car_id` int(10) UNSIGNED DEFAULT NULL,
  `width` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `height` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `filesize` int(8) UNSIGNED NOT NULL DEFAULT '0',
  `owner_id` int(10) UNSIGNED DEFAULT '0',
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(255) NOT NULL DEFAULT '',
  `crc` int(11) DEFAULT NULL,
  `status` enum('new','accepted','removing','removed','inbox') NOT NULL,
  `type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `removing_date` date DEFAULT NULL,
  `brand_id` int(10) UNSIGNED DEFAULT NULL,
  `engine_id` int(10) UNSIGNED DEFAULT NULL,
  `perspective_id` int(10) UNSIGNED DEFAULT NULL,
  `change_status_user_id` int(10) UNSIGNED DEFAULT NULL,
  `change_perspective_user_id` int(10) UNSIGNED DEFAULT NULL,
  `crop_left` smallint(6) UNSIGNED DEFAULT NULL,
  `crop_top` smallint(11) UNSIGNED DEFAULT NULL,
  `crop_width` smallint(6) UNSIGNED DEFAULT NULL,
  `crop_height` smallint(11) UNSIGNED DEFAULT NULL,
  `accept_datetime` timestamp NULL DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `source_id` int(10) UNSIGNED DEFAULT NULL,
  `copyrights` text,
  `identity` varchar(10) DEFAULT NULL,
  `replace_picture_id` int(10) UNSIGNED DEFAULT NULL,
  `image_id` int(10) UNSIGNED DEFAULT NULL,
  `factory_id` int(10) UNSIGNED DEFAULT NULL,
  `ip` varbinary(16) NOT NULL,
  `copyrights_text_id` int(11) DEFAULT NULL,
  `point` point DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity` (`identity`),
  UNIQUE KEY `image_id` (`image_id`),
  KEY `crc` (`crc`),
  KEY `engineIndex` (`engine_id`,`type`),
  KEY `dateAndIdOrdering` (`status`,`add_date`,`id`),
  KEY `comments` (`status`),
  KEY `perspective_id` (`perspective_id`,`status`),
  KEY `car_id` (`car_id`,`type`,`status`),
  KEY `brandIndex` (`brand_id`,`type`,`status`),
  KEY `owner_id` (`owner_id`,`status`),
  KEY `accept_datetime` (`status`,`accept_datetime`),
  KEY `pictures_fk5` (`type`),
  KEY `pictures_fk6` (`replace_picture_id`),
  KEY `factory_id` (`factory_id`),
  KEY `width` (`width`,`height`,`add_date`,`id`),
  KEY `copyrights_text_id` (`copyrights_text_id`)
) ENGINE=InnoDB AUTO_INCREMENT=917309 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`owner_id`)';

REPLACE into pictures (id, car_id, brand_id, width, height, status, ip, owner_id, type, image_id, accept_datetime) 
values (1, 1, null, 1600, 1200, 'accepted', inet6_aton('127.0.0.1'), 1, 1, 1, now()), 
(2, null, 1, 1600, 1200, 'accepted', inet6_aton('127.0.0.1'), 1, 0, 33, now()),
(3, null, 1, 1600, 1200, 'accepted', inet6_aton('127.0.0.1'), 1, 3, 35, now()),
(4, null, 1, 1600, 1200, 'accepted', inet6_aton('127.0.0.1'), 1, 2, 37, now()),
(5, null, 1, 1600, 1200, 'inbox', inet6_aton('127.0.0.1'), 1, 2, 38, now());

-- --------------------------------------------------------

--
-- Table structure for table `pictures_moder_votes`
--

CREATE TABLE IF NOT EXISTS `pictures_moder_votes` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `picture_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `day_date` datetime NOT NULL,
  `reason` varchar(50) NOT NULL DEFAULT '',
  `vote` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`picture_id`),
  KEY `picture_id` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`picture_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `pictures_types`
--

CREATE TABLE IF NOT EXISTS `pictures_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



INSERT INTO `pictures_types` (`id`, `name`) VALUES
(1, 'Автомобиль'),
(4, 'Двигатель'),
(7, 'Завод'),
(6, 'Интерьер'),
(2, 'Логотип бренда'),
(5, 'Модель'),
(0, 'Несортировано'),
(3, 'Разное');

-- --------------------------------------------------------

--
-- Table structure for table `pma__bookmark`
--

CREATE TABLE IF NOT EXISTS `pma__bookmark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dbase` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `user` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `query` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Table structure for table `pma__central_columns`
--

CREATE TABLE IF NOT EXISTS `pma__central_columns` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `col_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `col_type` varchar(64) COLLATE utf8_bin NOT NULL,
  `col_length` text COLLATE utf8_bin,
  `col_collation` varchar(64) COLLATE utf8_bin NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) COLLATE utf8_bin DEFAULT '',
  `col_default` text COLLATE utf8_bin,
  PRIMARY KEY (`db_name`,`col_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Table structure for table `pma__column_info`
--

CREATE TABLE IF NOT EXISTS `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `column_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `transformation` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `transformation_options` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `input_transformation` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__designer_settings`
--

CREATE TABLE IF NOT EXISTS `pma__designer_settings` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `settings_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Table structure for table `pma__export_templates`
--

CREATE TABLE IF NOT EXISTS `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `export_type` varchar(10) COLLATE utf8_bin NOT NULL,
  `template_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `template_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Table structure for table `pma__favorite`
--

CREATE TABLE IF NOT EXISTS `pma__favorite` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `tables` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Table structure for table `pma__history`
--

CREATE TABLE IF NOT EXISTS `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sqlquery` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`,`db`,`table`,`timevalue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__navigationhiding`
--

CREATE TABLE IF NOT EXISTS `pma__navigationhiding` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `item_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `item_type` varchar(64) COLLATE utf8_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Table structure for table `pma__pdf_pages`
--

CREATE TABLE IF NOT EXISTS `pma__pdf_pages` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_descr` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`page_nr`),
  KEY `db_name` (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__recent`
--

CREATE TABLE IF NOT EXISTS `pma__recent` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `tables` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

-- --------------------------------------------------------

--
-- Table structure for table `pma__relation`
--

CREATE TABLE IF NOT EXISTS `pma__relation` (
  `master_db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `master_table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `master_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  KEY `foreign_field` (`foreign_db`,`foreign_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Table structure for table `pma__savedsearches`
--

CREATE TABLE IF NOT EXISTS `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `search_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `search_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_coords`
--

CREATE TABLE IF NOT EXISTS `pma__table_coords` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT '0',
  `x` float UNSIGNED NOT NULL DEFAULT '0',
  `y` float UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_info`
--

CREATE TABLE IF NOT EXISTS `pma__table_info` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `display_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_uiprefs`
--

CREATE TABLE IF NOT EXISTS `pma__table_uiprefs` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `prefs` text COLLATE utf8_bin NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`,`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Table structure for table `pma__tracking`
--

CREATE TABLE IF NOT EXISTS `pma__tracking` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text COLLATE utf8_bin NOT NULL,
  `schema_sql` text COLLATE utf8_bin,
  `data_sql` longtext COLLATE utf8_bin,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') COLLATE utf8_bin DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`db_name`,`table_name`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__userconfig`
--

CREATE TABLE IF NOT EXISTS `pma__userconfig` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `config_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__usergroups`
--

CREATE TABLE IF NOT EXISTS `pma__usergroups` (
  `usergroup` varchar(64) COLLATE utf8_bin NOT NULL,
  `tab` varchar(64) COLLATE utf8_bin NOT NULL,
  `allowed` enum('Y','N') COLLATE utf8_bin NOT NULL DEFAULT 'N',
  PRIMARY KEY (`usergroup`,`tab`,`allowed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Table structure for table `pma__users`
--

CREATE TABLE IF NOT EXISTS `pma__users` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `usergroup` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`,`usergroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

-- --------------------------------------------------------

--
-- Table structure for table `referer`
--

CREATE TABLE IF NOT EXISTS `referer` (
  `host` varchar(255) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `count` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `last_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `accept` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`url`),
  KEY `UK_referer_host` (`host`,`last_date`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `referer_blacklist`
--

CREATE TABLE IF NOT EXISTS `referer_blacklist` (
  `host` varchar(255) NOT NULL,
  `hard` tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `referer_whitelist`
--

CREATE TABLE IF NOT EXISTS `referer_whitelist` (
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `referrers`
--

CREATE TABLE IF NOT EXISTS `referrers` (
  `url` varchar(255) NOT NULL DEFAULT '',
  `day_date` date NOT NULL,
  `count` int(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`url`,`day_date`),
  KEY `day_date` (`day_date`),
  KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `see_also`
--

CREATE TABLE IF NOT EXISTS `see_also` (
  `from_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `from_type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `to_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `to_type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`from_id`,`from_type`,`to_id`,`to_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `id` char(32) NOT NULL DEFAULT '',
  `modified` int(11) DEFAULT NULL,
  `lifetime` int(11) DEFAULT NULL,
  `data` text,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_session_modified` (`modified`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AVG_ROW_LENGTH=84 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `sources`
--

CREATE TABLE IF NOT EXISTS `sources` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `spec`
--

CREATE TABLE IF NOT EXISTS `spec` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  `parent_id` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `short_name` (`short_name`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `telegram_brand`
--

CREATE TABLE IF NOT EXISTS `telegram_brand` (
  `brand_id` int(10) UNSIGNED NOT NULL,
  `chat_id` int(11) NOT NULL,
  `inbox` tinyint(1) NOT NULL DEFAULT '0',
  `new` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`brand_id`,`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `telegram_chat`
--

CREATE TABLE IF NOT EXISTS `telegram_chat` (
  `chat_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `token` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`chat_id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `textstorage_revision`
--

CREATE TABLE IF NOT EXISTS `textstorage_revision` (
  `text_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `text` text NOT NULL,
  `timestamp` timestamp NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`text_id`,`revision`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `textstorage_text`
--

CREATE TABLE IF NOT EXISTS `textstorage_text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text NOT NULL,
  `last_updated` timestamp NOT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31128 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `twins_groups`
--

CREATE TABLE IF NOT EXISTS `twins_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `text_id` (`text_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2020 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB';

REPLACE into twins_groups (id, name, add_datetime, text_id)
values (1, "test twins", NOW(), null);

-- --------------------------------------------------------

--
-- Table structure for table `twins_groups_cars`
--

CREATE TABLE IF NOT EXISTS `twins_groups_cars` (
  `twins_group_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`twins_group_id`,`car_id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`twins_group_id`)';

REPLACE into twins_groups_cars (twins_group_id, car_id)
values (1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_account`
--

CREATE TABLE IF NOT EXISTS `user_account` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `external_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `used_for_reg` tinyint(3) UNSIGNED NOT NULL,
  `service_id` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_id` (`service_id`,`external_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2311 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_authority`
--

CREATE TABLE IF NOT EXISTS `user_authority` (
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `authority` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`from_user_id`,`to_user_id`),
  KEY `to_user_id` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_car_subscribe`
--

CREATE TABLE IF NOT EXISTS `user_car_subscribe` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `car_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`car_id`),
  KEY `car_id_index` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_password_remind`
--

CREATE TABLE IF NOT EXISTS `user_password_remind` (
  `hash` varchar(255) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`),
  KEY `FK_user_password_remind_users_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_remember`
--
drop table if exists user_remember;
CREATE TABLE IF NOT EXISTS `user_remember` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE into user_remember (user_id, token, date)
values (3, "admin-token", NOW());

-- --------------------------------------------------------

--
-- Table structure for table `user_renames`
--

CREATE TABLE IF NOT EXISTS `user_renames` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `old_name` varchar(255) NOT NULL,
  `new_name` varchar(255) NOT NULL,
  `date` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=3212 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(20) DEFAULT NULL,
  `password` varchar(50) NOT NULL DEFAULT '',
  `e_mail` varchar(50) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `reg_date` timestamp NULL DEFAULT NULL,
  `last_online` timestamp NULL DEFAULT NULL,
  `icq` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `url` varchar(50) NOT NULL DEFAULT '',
  `own_car` varchar(100) NOT NULL DEFAULT '',
  `dream_car` varchar(100) NOT NULL DEFAULT '',
  `forums_topics` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `forums_messages` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `pictures_added` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `e_mail_checked` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `hide_e_mail` int(11) DEFAULT NULL,
  `authority` float DEFAULT '0',
  `pictures_ratio` double UNSIGNED DEFAULT NULL,
  `email_to_check` varchar(50) DEFAULT NULL,
  `email_check_code` varchar(32) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `messaging_interval` int(10) UNSIGNED NOT NULL DEFAULT '10',
  `last_message_time` timestamp NULL DEFAULT NULL,
  `deleted` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `identity` varchar(50) DEFAULT NULL,
  `img` int(10) UNSIGNED DEFAULT NULL,
  `votes_per_day` int(10) UNSIGNED NOT NULL DEFAULT '1',
  `votes_left` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `specs_volume` int(11) NOT NULL DEFAULT '0',
  `specs_volume_valid` tinyint(4) NOT NULL DEFAULT '0',
  `specs_positives` int(11) DEFAULT NULL,
  `specs_negatives` int(11) DEFAULT NULL,
  `specs_weight` double NOT NULL DEFAULT '0',
  `last_ip` varbinary(16) NOT NULL,
  `language` varchar(2) NOT NULL DEFAULT 'ru',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `e_mail` (`e_mail`),
  UNIQUE KEY `identity` (`identity`),
  KEY `password` (`password`),
  KEY `email_check_code` (`email_check_code`),
  KEY `role` (`role`),
  KEY `specs_volume` (`specs_volume`),
  KEY `last_ip` (`last_ip`)
) ENGINE=InnoDB AUTO_INCREMENT=25161 AVG_ROW_LENGTH=227 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`group_id`)';

REPLACE INTO users (id, login, password, e_mail, name, last_online, role, last_ip, timezone, language)
values (1, 'test', '26cc2d23a03a8f07ed1e3d000a244636', 'test@example.com', 'tester', now(), 'user', inet6_aton('127.0.0.1'), 'Europe/Moscow', 'ru');

REPLACE INTO users (id, name, last_online, role, last_ip, identity)
values (2, 'tester2', now(), 'user', inet6_aton('127.0.0.1'), 'identity');

REPLACE INTO users (id, name, last_online, role, last_ip, identity)
values (3, 'admin', now(), 'admin', inet6_aton('127.0.0.1'), 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE IF NOT EXISTS `votes` (
  `picture_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `day_date` date NOT NULL,
  `count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `summary` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`picture_id`,`day_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB';

-- --------------------------------------------------------

--
-- Table structure for table `voting`
--

CREATE TABLE IF NOT EXISTS `voting` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `multivariant` tinyint(1) NOT NULL DEFAULT '0',
  `begin_date` date NOT NULL,
  `end_date` date NOT NULL,
  `votes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `text` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB';

-- --------------------------------------------------------

--
-- Table structure for table `voting_variant`
--

CREATE TABLE IF NOT EXISTS `voting_variant` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `voting_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `votes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `position` tinyint(3) UNSIGNED NOT NULL,
  `text` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voting_id` (`voting_id`,`name`),
  UNIQUE KEY `unique_position` (`voting_id`,`position`),
  KEY `voting_id_2` (`voting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 AVG_ROW_LENGTH=197 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`voting_id`)';

-- --------------------------------------------------------

--
-- Table structure for table `voting_variant_vote`
--

CREATE TABLE IF NOT EXISTS `voting_variant_vote` (
  `voting_variant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`voting_variant_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `voting_variant_id` (`voting_variant_id`)
) ENGINE=InnoDB AVG_ROW_LENGTH=30 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`user_id`)';


CREATE TABLE `vehicle_vehicle_type` (
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `vehicle_type_id` int(10) UNSIGNED NOT NULL,
  `inherited` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `vehicle_vehicle_type`
  ADD PRIMARY KEY (`vehicle_id`,`vehicle_type_id`),
  ADD KEY `vehicle_type_id` (`vehicle_type_id`);


--
-- Constraints for dumped tables
--

ALTER TABLE `vehicle_vehicle_type`
  ADD CONSTRAINT `vehicle_vehicle_type_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `vehicle_vehicle_type_ibfk_2` FOREIGN KEY (`vehicle_type_id`) REFERENCES `car_types` (`id`);

--
-- Constraints for table `acl_resources_privileges`
--
ALTER TABLE `acl_resources_privileges`
  ADD CONSTRAINT `acl_resources_privileges_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `acl_resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `acl_roles_parents`
--
ALTER TABLE `acl_roles_parents`
  ADD CONSTRAINT `acl_roles_parents_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acl_roles_parents_ibfk_2` FOREIGN KEY (`parent_role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `acl_roles_privileges_allowed`
--
ALTER TABLE `acl_roles_privileges_allowed`
  ADD CONSTRAINT `acl_roles_privileges_allowed_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acl_roles_privileges_allowed_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `acl_roles_privileges_denied`
--
ALTER TABLE `acl_roles_privileges_denied`
  ADD CONSTRAINT `acl_roles_privileges_denied_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acl_roles_privileges_denied_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_fk` FOREIGN KEY (`last_editor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `articles_fk1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `articles_fk2` FOREIGN KEY (`html_id`) REFERENCES `htmls` (`id`);

--
-- Constraints for table `articles_brands`
--
ALTER TABLE `articles_brands`
  ADD CONSTRAINT `articles_brands_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_brands_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `articles_brands_cache`
--
ALTER TABLE `articles_brands_cache`
  ADD CONSTRAINT `articles_brands_cache_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `articles_brands_cache_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `articles_cars`
--
ALTER TABLE `articles_cars`
  ADD CONSTRAINT `articles_cars_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`);

--
-- Constraints for table `articles_criterias_votes`
--
ALTER TABLE `articles_criterias_votes`
  ADD CONSTRAINT `articles_criterias_votes_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_criterias_votes_fk1` FOREIGN KEY (`criteria_id`) REFERENCES `articles_votings_criterias` (`id`);

--
-- Constraints for table `articles_criterias_votes_ips`
--
ALTER TABLE `articles_criterias_votes_ips`
  ADD CONSTRAINT `articles_criterias_votes_ips_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_criterias_votes_ips_fk1` FOREIGN KEY (`criteria_id`) REFERENCES `articles_votings_criterias` (`id`);

--
-- Constraints for table `articles_engines`
--
ALTER TABLE `articles_engines`
  ADD CONSTRAINT `articles_engines_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_engines_fk1` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`);

--
-- Constraints for table `articles_sources`
--
ALTER TABLE `articles_sources`
  ADD CONSTRAINT `articles_sources_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

--
-- Constraints for table `articles_twins_groups`
--
ALTER TABLE `articles_twins_groups`
  ADD CONSTRAINT `article_twins_groups_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `articles_twins_groups_fk` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`);

--
-- Constraints for table `attrs_attributes`
--
ALTER TABLE `attrs_attributes`
  ADD CONSTRAINT `attrs_attributes_fk` FOREIGN KEY (`type_id`) REFERENCES `attrs_types` (`id`),
  ADD CONSTRAINT `attrs_attributes_fk1` FOREIGN KEY (`parent_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_attributes_fk2` FOREIGN KEY (`unit_id`) REFERENCES `attrs_units` (`id`);

--
-- Constraints for table `attrs_list_options`
--
ALTER TABLE `attrs_list_options`
  ADD CONSTRAINT `attrs_list_options_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_list_options_fk1` FOREIGN KEY (`parent_id`) REFERENCES `attrs_list_options` (`id`);

--
-- Constraints for table `attrs_user_values`
--
ALTER TABLE `attrs_user_values`
  ADD CONSTRAINT `attrs_user_values_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_user_values_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `attrs_user_values_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_user_values_float`
--
ALTER TABLE `attrs_user_values_float`
  ADD CONSTRAINT `attrs_user_values_float_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_user_values_float_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `attrs_user_values_float_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_user_values_int`
--
ALTER TABLE `attrs_user_values_int`
  ADD CONSTRAINT `attrs_user_values_int_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_user_values_int_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `attrs_user_values_int_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_user_values_list`
--
ALTER TABLE `attrs_user_values_list`
  ADD CONSTRAINT `FK_attrs_user_values_list_attrs_attributes_id` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `FK_attrs_user_values_list_attrs_item_types_id` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `FK_attrs_user_values_list_attrs_list_options_id` FOREIGN KEY (`value`) REFERENCES `attrs_list_options` (`id`),
  ADD CONSTRAINT `FK_attrs_user_values_list_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_user_values_string`
--
ALTER TABLE `attrs_user_values_string`
  ADD CONSTRAINT `attrs_user_values_string_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_user_values_string_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `attrs_user_values_string_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_user_values_text`
--
ALTER TABLE `attrs_user_values_text`
  ADD CONSTRAINT `attrs_user_values_text_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_user_values_text_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `attrs_user_values_text_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attrs_values`
--
ALTER TABLE `attrs_values`
  ADD CONSTRAINT `attrs_values_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_values_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `attrs_values_float`
--
ALTER TABLE `attrs_values_float`
  ADD CONSTRAINT `attrs_values_float_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_values_float_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `attrs_values_int`
--
ALTER TABLE `attrs_values_int`
  ADD CONSTRAINT `attrs_values_int_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_values_int_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `attrs_values_list`
--
ALTER TABLE `attrs_values_list`
  ADD CONSTRAINT `FK_attrs_values_list_attrs_attributes_id` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `FK_attrs_values_list_attrs_item_types_id` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  ADD CONSTRAINT `FK_attrs_values_list_attrs_list_options_id` FOREIGN KEY (`value`) REFERENCES `attrs_list_options` (`id`);

--
-- Constraints for table `attrs_values_string`
--
ALTER TABLE `attrs_values_string`
  ADD CONSTRAINT `attrs_values_string_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_values_string_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `attrs_values_text`
--
ALTER TABLE `attrs_values_text`
  ADD CONSTRAINT `attrs_values_text_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  ADD CONSTRAINT `attrs_values_text_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `attrs_zone_attributes`
--
ALTER TABLE `attrs_zone_attributes`
  ADD CONSTRAINT `attrs_zone_attributes_fk` FOREIGN KEY (`zone_id`) REFERENCES `attrs_zones` (`id`),
  ADD CONSTRAINT `attrs_zone_attributes_fk1` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`);

--
-- Constraints for table `attrs_zones`
--
ALTER TABLE `attrs_zones`
  ADD CONSTRAINT `attrs_zones_fk` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`);

--
-- Constraints for table `banned_ip`
--
ALTER TABLE `banned_ip`
  ADD CONSTRAINT `banned_ip_ibfk_1` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `brand_alias`
--
ALTER TABLE `brand_alias`
  ADD CONSTRAINT `FK_brand_alias_brands_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `brand_engine`
--
ALTER TABLE `brand_engine`
  ADD CONSTRAINT `brand_fk` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `engine_fk2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`);

--
-- Constraints for table `brand_language`
--
ALTER TABLE `brand_language`
  ADD CONSTRAINT `FK_brand_language_brands_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `brand_type_language`
--
ALTER TABLE `brand_type_language`
  ADD CONSTRAINT `FK_brand_type_language_brand_types_id` FOREIGN KEY (`brand_type_id`) REFERENCES `brand_types` (`id`);

--
-- Constraints for table `brands`
--
ALTER TABLE `brands`
  ADD CONSTRAINT `brands_fk` FOREIGN KEY (`parent_brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `brands_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`);

--
-- Constraints for table `brands_cars`
--
ALTER TABLE `brands_cars`
  ADD CONSTRAINT `brands_cars_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `brands_cars_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `brands_pictures_cache`
--
ALTER TABLE `brands_pictures_cache`
  ADD CONSTRAINT `brands_pictures_cache_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `brands_pictures_cache_ibfk_2` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `car_language`
--
ALTER TABLE `car_language`
  ADD CONSTRAINT `car_language_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `car_parent`
--
ALTER TABLE `car_parent`
  ADD CONSTRAINT `car_parent_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `car_parent_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `cars` (`id`);

--
-- Constraints for table `car_parent_cache`
--
ALTER TABLE `car_parent_cache`
  ADD CONSTRAINT `car_parent_cache_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_parent_cache_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `car_type_language`
--
ALTER TABLE `car_type_language`
  ADD CONSTRAINT `FK_car_type_language_car_types_id` FOREIGN KEY (`car_type_id`) REFERENCES `car_types` (`id`);

--
-- Constraints for table `car_types_parents`
--
ALTER TABLE `car_types_parents`
  ADD CONSTRAINT `car_types_parents_ibfk_1` FOREIGN KEY (`id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_types_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cars`
--
ALTER TABLE `cars`
  ADD CONSTRAINT `cars_ibfk_2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`),
  ADD CONSTRAINT `cars_ibfk_3` FOREIGN KEY (`spec_id`) REFERENCES `spec` (`id`),
  ADD CONSTRAINT `cars_ibfk_4` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  ADD CONSTRAINT `cars_ibfk_5` FOREIGN KEY (`full_text_id`) REFERENCES `textstorage_text` (`id`);

--
-- Constraints for table `category_car`
--
ALTER TABLE `category_car`
  ADD CONSTRAINT `category_car_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_car_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_car_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_language`
--
ALTER TABLE `category_language`
  ADD CONSTRAINT `category_language_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_parent`
--
ALTER TABLE `category_parent`
  ADD CONSTRAINT `FK_category_parent_category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_category_parent_category_id2` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comment_topic_view`
--
ALTER TABLE `comment_topic_view`
  ADD CONSTRAINT `comment_topic_view_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comment_vote`
--
ALTER TABLE `comment_vote`
  ADD CONSTRAINT `comment_vote_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_vote_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments_messages`
--
ALTER TABLE `comments_messages`
  ADD CONSTRAINT `comments_messages_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `comments_messages_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `contact`
--
ALTER TABLE `contact`
  ADD CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `engine_parent_cache`
--
ALTER TABLE `engine_parent_cache`
  ADD CONSTRAINT `engine_fk` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `engines`
--
ALTER TABLE `engines`
  ADD CONSTRAINT `engines_ibfk_1` FOREIGN KEY (`last_editor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `engines` (`id`);

--
-- Constraints for table `factory_car`
--
ALTER TABLE `factory_car`
  ADD CONSTRAINT `factory_car_ibfk_1` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`),
  ADD CONSTRAINT `factory_car_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`);

--
-- Constraints for table `formated_image`
--
ALTER TABLE `formated_image`
  ADD CONSTRAINT `formated_image_ibfk_1` FOREIGN KEY (`formated_image_id`) REFERENCES `image` (`id`);

--
-- Constraints for table `forums_theme_parent`
--
ALTER TABLE `forums_theme_parent`
  ADD CONSTRAINT `FK_forum_theme_parent_forums_themes_id` FOREIGN KEY (`forum_theme_id`) REFERENCES `forums_themes` (`id`),
  ADD CONSTRAINT `FK_forum_theme_parent_forums_themes_id2` FOREIGN KEY (`parent_id`) REFERENCES `forums_themes` (`id`);

--
-- Constraints for table `forums_themes`
--
ALTER TABLE `forums_themes`
  ADD CONSTRAINT `FK_forums_themes_forums_themes_id` FOREIGN KEY (`parent_id`) REFERENCES `forums_themes` (`id`);

--
-- Constraints for table `forums_topics`
--
ALTER TABLE `forums_topics`
  ADD CONSTRAINT `forums_topics_fk` FOREIGN KEY (`theme_id`) REFERENCES `forums_themes` (`id`),
  ADD CONSTRAINT `forums_topics_fk1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `forums_topics_subscribers`
--
ALTER TABLE `forums_topics_subscribers`
  ADD CONSTRAINT `topics_subscribers_fk` FOREIGN KEY (`topic_id`) REFERENCES `forums_topics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `topics_subscribers_fk1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events`
--
ALTER TABLE `log_events`
  ADD CONSTRAINT `log_events_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `log_events_articles`
--
ALTER TABLE `log_events_articles`
  ADD CONSTRAINT `log_events_articles_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_articles_fk1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_brands`
--
ALTER TABLE `log_events_brands`
  ADD CONSTRAINT `log_events_brands_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_brands_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_cars`
--
ALTER TABLE `log_events_cars`
  ADD CONSTRAINT `log_events_cars_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_engines`
--
ALTER TABLE `log_events_engines`
  ADD CONSTRAINT `log_events_engines_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_engines_fk1` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_factory`
--
ALTER TABLE `log_events_factory`
  ADD CONSTRAINT `log_events_factory_ibfk_1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_factory_ibfk_2` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_pictures`
--
ALTER TABLE `log_events_pictures`
  ADD CONSTRAINT `log_events_pictures_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_pictures_fk1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_twins_groups`
--
ALTER TABLE `log_events_twins_groups`
  ADD CONSTRAINT `log_events_twins_groups_ibfk_1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_events_twins_groups_ibfk_2` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_events_user`
--
ALTER TABLE `log_events_user`
  ADD CONSTRAINT `FK_log_events_user_log_events_id` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`),
  ADD CONSTRAINT `FK_log_events_user_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `modification`
--
ALTER TABLE `modification`
  ADD CONSTRAINT `modification_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `modification_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `modification_group` (`id`);

--
-- Constraints for table `modification_picture`
--
ALTER TABLE `modification_picture`
  ADD CONSTRAINT `modification_picture_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modification_picture_ibfk_2` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `modification_value`
--
ALTER TABLE `modification_value`
  ADD CONSTRAINT `modification_value_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`);

--
-- Constraints for table `of_day`
--
ALTER TABLE `of_day`
  ADD CONSTRAINT `FK_of_day_cars_id` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `of_day_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `page_language`
--
ALTER TABLE `page_language`
  ADD CONSTRAINT `page_language_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_fk` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`);

--
-- Constraints for table `personal_messages`
--
ALTER TABLE `personal_messages`
  ADD CONSTRAINT `personal_messages_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `personal_messages_fk1` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `perspective_language`
--
ALTER TABLE `perspective_language`
  ADD CONSTRAINT `perspective_language_ibfk_1` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `perspectives_groups`
--
ALTER TABLE `perspectives_groups`
  ADD CONSTRAINT `perspectives_groups_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `perspectives_pages` (`id`);

--
-- Constraints for table `perspectives_groups_perspectives`
--
ALTER TABLE `perspectives_groups_perspectives`
  ADD CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_groups_id` FOREIGN KEY (`group_id`) REFERENCES `perspectives_groups` (`id`),
  ADD CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_id` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`);

--
-- Constraints for table `picture_view`
--
ALTER TABLE `picture_view`
  ADD CONSTRAINT `picture_view_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `picture_votes_ips`
--
ALTER TABLE `picture_votes_ips`
  ADD CONSTRAINT `picture_votes_ips_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pictures`
--
ALTER TABLE `pictures`
  ADD CONSTRAINT `pictures_fk` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pictures_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `pictures_fk2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`),
  ADD CONSTRAINT `pictures_fk4` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `pictures_fk5` FOREIGN KEY (`type`) REFERENCES `pictures_types` (`id`),
  ADD CONSTRAINT `pictures_fk6` FOREIGN KEY (`replace_picture_id`) REFERENCES `pictures` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pictures_fk7` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  ADD CONSTRAINT `pictures_ibfk_1` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`),
  ADD CONSTRAINT `pictures_ibfk_2` FOREIGN KEY (`copyrights_text_id`) REFERENCES `textstorage_text` (`id`);

--
-- Constraints for table `pictures_moder_votes`
--
ALTER TABLE `pictures_moder_votes`
  ADD CONSTRAINT `picture_id_ref` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pictures_moder_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `session`
--
ALTER TABLE `session`
  ADD CONSTRAINT `session_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spec`
--
ALTER TABLE `spec`
  ADD CONSTRAINT `spec_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `spec` (`id`);

--
-- Constraints for table `telegram_brand`
--
ALTER TABLE `telegram_brand`
  ADD CONSTRAINT `telegram_brand_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `telegram_chat`
--
ALTER TABLE `telegram_chat`
  ADD CONSTRAINT `telegram_chat_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `textstorage_revision`
--
ALTER TABLE `textstorage_revision`
  ADD CONSTRAINT `textstorage_revision_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  ADD CONSTRAINT `textstorage_revision_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `twins_groups`
--
ALTER TABLE `twins_groups`
  ADD CONSTRAINT `twins_groups_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`);

--
-- Constraints for table `twins_groups_cars`
--
ALTER TABLE `twins_groups_cars`
  ADD CONSTRAINT `twins_groups_cars_fk` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`),
  ADD CONSTRAINT `twins_groups_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`);

--
-- Constraints for table `user_account`
--
ALTER TABLE `user_account`
  ADD CONSTRAINT `user_account_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_authority`
--
ALTER TABLE `user_authority`
  ADD CONSTRAINT `user_authority_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_authority_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_car_subscribe`
--
ALTER TABLE `user_car_subscribe`
  ADD CONSTRAINT `user_car_subscribe_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_car_subscribe_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_password_remind`
--
ALTER TABLE `user_password_remind`
  ADD CONSTRAINT `FK_user_password_remind_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_remember`
--
ALTER TABLE `user_remember`
  ADD CONSTRAINT `user_remember_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_renames`
--
ALTER TABLE `user_renames`
  ADD CONSTRAINT `user_renames_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `voting_variant`
--
ALTER TABLE `voting_variant`
  ADD CONSTRAINT `voting_variants_ibfk_1` FOREIGN KEY (`voting_id`) REFERENCES `voting` (`id`);

--
-- Constraints for table `voting_variant_vote`
--
ALTER TABLE `voting_variant_vote`
  ADD CONSTRAINT `voting_variant_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `voting_variant_votes_ibfk_2` FOREIGN KEY (`voting_variant_id`) REFERENCES `voting_variant` (`id`);
  
-- phpMyAdmin SQL Dump
-- version 4.7.0-dev
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 31, 2016 at 01:13 AM
-- Server version: 5.7.15
-- PHP Version: 7.0.12-1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `autowp`
--

-- --------------------------------------------------------

--
-- Table structure for table `brand_vehicle_language`
--

CREATE TABLE `brand_vehicle_language` (
  `brand_id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(2) NOT NULL,
  `name` varchar(70) NOT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `brand_vehicle_language`
  ADD PRIMARY KEY (`brand_id`,`vehicle_id`,`language`);

INSERT INTO `brand_vehicle_language` (`brand_id`, `vehicle_id`, `language`, `name`, `is_auto`) VALUES
(1, 1, 'en', 'BMW 335i', 1);

SET FOREIGN_KEY_CHECKS = 1;  