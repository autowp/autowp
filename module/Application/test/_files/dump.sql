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

REPLACE into attrs_attributes (id, name, type_id, parent_id, unit_id, description, `precision`, position, multiple)
values (1, "list", 6, null, null, "", 1, 1, 0);

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

REPLACE into attrs_list_options (id, attribute_id, name, position, parent_id)
values (1, 1, "test option", 1, null);

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
values (1, 1, 1, 1, NOW(), NOW(), 0, 1);

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
values (1, 1, 1, 1, 1, 1);

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
values (1, 1, 1, NOW(), 0);

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
values (1, 1, 1, 1, 1);

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
values (1, 1, 0, "first-car"), (1, 2, 0, "second-car");

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
values (1, 1, 0, 0, 0, 0), (2, 2, 0, 0, 0, 0);

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

REPLACE into cars (id, caption, body, produced_exactly, begin_year, is_concept, engine_id)
values (1, 'test car', '', 0, 1999, 0, 1),
(2, 'test concept car', '', 0, 1999, 1, NULL);

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