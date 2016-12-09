use autowp_test;

-- MySQL dump 10.13  Distrib 5.7.16, for Linux (x86_64)
--
-- Host: localhost    Database: autowp_test
-- ------------------------------------------------------
-- Server version   5.7.16-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acl_resources`
--

DROP TABLE IF EXISTS `acl_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_resources`
--

/*!40000 ALTER TABLE `acl_resources` DISABLE KEYS */;
INSERT INTO `acl_resources` VALUES 
(12,'attrs'),(1,'brand'),(4,'car'),(14,'category'),(6,'comment'),(8,'engine'),
(21,'factory'),(10,'forums'),(15,'hotlinks'),(2,'model'),(19,'museums'),
(7,'page'),(5,'picture'),(9,'rights'),(17,'specifications'),(18,'status'),
(11,'twins'),(13,'user'),(20,'website');
/*!40000 ALTER TABLE `acl_resources` ENABLE KEYS */;

--
-- Table structure for table `acl_resources_privileges`
--

DROP TABLE IF EXISTS `acl_resources_privileges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_resources_privileges` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`resource_id`,`name`),
  CONSTRAINT `acl_resources_privileges_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `acl_resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_resources_privileges`
--

/*!40000 ALTER TABLE `acl_resources_privileges` DISABLE KEYS */;
REPLACE INTO `acl_resources_privileges` VALUES 
(4,4,'add'),(1,4,'edit_meta'),(5,4,'move'),
(2,11,'edit'),(3,13,'ban'),(7,17,'edit'),(6,21,'edit'),
(8,5,'move');
/*!40000 ALTER TABLE `acl_resources_privileges` ENABLE KEYS */;

--
-- Table structure for table `acl_roles`
--

DROP TABLE IF EXISTS `acl_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_roles`
--

/*!40000 ALTER TABLE `acl_roles` DISABLE KEYS */;
INSERT INTO `acl_roles` VALUES 
(1,'abstract-user'),
(5,'admin'),
(12,'articles-moder'),
(11,'brands-moder'),
(10,'cars-moder'),
(8,'comments-writer'),
(15,'engines-moder'),(23,'expert'),
(58,'factory-moder'),(16,'forums-moder'),(49,'green-user'),
(7,'guest'),(17,'models-moder'),(14,'moder'),(50,'museum-moder'),
(13,'pages-moder'),
(9,'pictures-moder'),
(47,'specifications-editor'),
(6,'user');
/*!40000 ALTER TABLE `acl_roles` ENABLE KEYS */;

--
-- Table structure for table `acl_roles_parents`
--

DROP TABLE IF EXISTS `acl_roles_parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_roles_parents` (
  `role_id` int(10) unsigned NOT NULL,
  `parent_role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`parent_role_id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `acl_roles_parents_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_parents_ibfk_2` FOREIGN KEY (`parent_role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_roles_parents`
--

/*!40000 ALTER TABLE `acl_roles_parents` DISABLE KEYS */;
REPLACE INTO `acl_roles_parents` VALUES (14,6),(5,10),(5,14),(10,14),(5,58),(5,9);
/*!40000 ALTER TABLE `acl_roles_parents` ENABLE KEYS */;

--
-- Table structure for table `acl_roles_privileges_allowed`
--

DROP TABLE IF EXISTS `acl_roles_privileges_allowed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_roles_privileges_allowed` (
  `role_id` int(10) unsigned NOT NULL,
  `privilege_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `acl_roles_privileges_allowed_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_privileges_allowed_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_roles_privileges_allowed`
--

/*!40000 ALTER TABLE `acl_roles_privileges_allowed` DISABLE KEYS */;
REPLACE INTO `acl_roles_privileges_allowed` VALUES 
(10,1),(10,2),(10,3),(10,4),(10,5),(58,6),(6,7),(9,8);
/*!40000 ALTER TABLE `acl_roles_privileges_allowed` ENABLE KEYS */;

--
-- Table structure for table `acl_roles_privileges_denied`
--

DROP TABLE IF EXISTS `acl_roles_privileges_denied`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl_roles_privileges_denied` (
  `role_id` int(10) unsigned NOT NULL,
  `privilege_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `acl_roles_privileges_denied_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acl_roles_privileges_denied_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `acl_resources_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl_roles_privileges_denied`
--

/*!40000 ALTER TABLE `acl_roles_privileges_denied` DISABLE KEYS */;
/*!40000 ALTER TABLE `acl_roles_privileges_denied` ENABLE KEYS */;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `html_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `catname` varchar(100) NOT NULL,
  `last_editor_id` int(10) unsigned DEFAULT NULL,
  `last_edit_date` timestamp NULL DEFAULT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author_id` int(10) unsigned DEFAULT NULL,
  `enabled` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `first_enabled_datetime` timestamp NULL DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `preview_width` tinyint(3) unsigned DEFAULT NULL,
  `preview_height` tinyint(3) unsigned DEFAULT NULL,
  `preview_filename` varchar(50) DEFAULT NULL,
  `ratio` float unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  KEY `html_id` (`html_id`),
  KEY `last_editor_id` (`last_editor_id`),
  KEY `author_id` (`author_id`),
  KEY `first_enabled_datetime` (`first_enabled_datetime`),
  CONSTRAINT `articles_fk` FOREIGN KEY (`last_editor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `articles_fk1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `articles_fk2` FOREIGN KEY (`html_id`) REFERENCES `htmls` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`last_editor_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;

--
-- Table structure for table `articles_brands`
--

DROP TABLE IF EXISTS `articles_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_brands` (
  `article_id` int(10) unsigned NOT NULL,
  `brand_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`brand_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `articles_brands_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_brands_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_brands`
--

/*!40000 ALTER TABLE `articles_brands` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_brands` ENABLE KEYS */;

--
-- Table structure for table `articles_brands_cache`
--

DROP TABLE IF EXISTS `articles_brands_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_brands_cache` (
  `article_id` int(10) unsigned NOT NULL,
  `brand_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`brand_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `articles_brands_cache_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `articles_brands_cache_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_brands_cache`
--

/*!40000 ALTER TABLE `articles_brands_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_brands_cache` ENABLE KEYS */;

--
-- Table structure for table `articles_cars`
--

DROP TABLE IF EXISTS `articles_cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_cars` (
  `article_id` int(10) unsigned NOT NULL,
  `car_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`car_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `articles_cars_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`article_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_cars`
--

/*!40000 ALTER TABLE `articles_cars` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_cars` ENABLE KEYS */;

--
-- Table structure for table `articles_criterias_votes`
--

DROP TABLE IF EXISTS `articles_criterias_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_criterias_votes` (
  `article_id` int(10) unsigned NOT NULL,
  `criteria_id` tinyint(3) unsigned NOT NULL,
  `votes_count` int(10) unsigned NOT NULL,
  `summary_vote` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`criteria_id`),
  KEY `criteria_id` (`criteria_id`),
  CONSTRAINT `articles_criterias_votes_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_criterias_votes_fk1` FOREIGN KEY (`criteria_id`) REFERENCES `articles_votings_criterias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_criterias_votes`
--

/*!40000 ALTER TABLE `articles_criterias_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_criterias_votes` ENABLE KEYS */;

--
-- Table structure for table `articles_criterias_votes_ips`
--

DROP TABLE IF EXISTS `articles_criterias_votes_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_criterias_votes_ips` (
  `article_id` int(10) unsigned NOT NULL,
  `criteria_id` tinyint(3) unsigned NOT NULL,
  `ip` varchar(15) NOT NULL,
  `vote_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`,`criteria_id`,`ip`),
  KEY `criteria_id` (`criteria_id`),
  CONSTRAINT `articles_criterias_votes_ips_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_criterias_votes_ips_fk1` FOREIGN KEY (`criteria_id`) REFERENCES `articles_votings_criterias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_criterias_votes_ips`
--

/*!40000 ALTER TABLE `articles_criterias_votes_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_criterias_votes_ips` ENABLE KEYS */;

--
-- Table structure for table `articles_engines`
--

DROP TABLE IF EXISTS `articles_engines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_engines` (
  `article_id` int(10) unsigned NOT NULL,
  `engine_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`engine_id`),
  KEY `engine_id` (`engine_id`),
  CONSTRAINT `articles_engines_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_engines_fk1` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`article_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_engines`
--

/*!40000 ALTER TABLE `articles_engines` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_engines` ENABLE KEYS */;

--
-- Table structure for table `articles_sources`
--

DROP TABLE IF EXISTS `articles_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `url` varchar(100) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `articles_sources_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`article_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_sources`
--

/*!40000 ALTER TABLE `articles_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_sources` ENABLE KEYS */;

--
-- Table structure for table `articles_twins_groups`
--

DROP TABLE IF EXISTS `articles_twins_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_twins_groups` (
  `article_id` int(10) unsigned NOT NULL,
  `twins_group_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`twins_group_id`),
  KEY `twins_group_id` (`twins_group_id`),
  CONSTRAINT `article_twins_groups_fk` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `articles_twins_groups_fk` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`twins_group_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_twins_groups`
--

/*!40000 ALTER TABLE `articles_twins_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_twins_groups` ENABLE KEYS */;

--
-- Table structure for table `articles_votings_criterias`
--

DROP TABLE IF EXISTS `articles_votings_criterias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles_votings_criterias` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles_votings_criterias`
--

/*!40000 ALTER TABLE `articles_votings_criterias` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles_votings_criterias` ENABLE KEYS */;

--
-- Table structure for table `attrs_attributes`
--

DROP TABLE IF EXISTS `attrs_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_attributes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type_id` smallint(5) unsigned DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `unit_id` int(10) unsigned zerofill DEFAULT NULL,
  `description` text,
  `precision` smallint(5) unsigned DEFAULT NULL,
  `position` int(10) unsigned NOT NULL,
  `multiple` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`parent_id`),
  UNIQUE KEY `position` (`position`,`parent_id`),
  KEY `type` (`type_id`),
  KEY `unit_id` (`unit_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `attrs_attributes_fk` FOREIGN KEY (`type_id`) REFERENCES `attrs_types` (`id`),
  CONSTRAINT `attrs_attributes_fk1` FOREIGN KEY (`parent_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_attributes_fk2` FOREIGN KEY (`unit_id`) REFERENCES `attrs_units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_attributes`
--

/*!40000 ALTER TABLE `attrs_attributes` DISABLE KEYS */;
INSERT INTO `attrs_attributes` VALUES (1,'specs/attrs/14/17/1',2,17,0000000001,NULL,NULL,1,0),(2,'specs/attrs/14/17/2',2,17,0000000001,NULL,NULL,2,0),(3,'specs/attrs/14/17/3',2,17,0000000001,NULL,NULL,3,0),(4,'specs/attrs/14/4',2,14,0000000001,NULL,NULL,4,0),(5,'specs/attrs/14/18/5',2,18,0000000001,NULL,NULL,5,0),(6,'specs/attrs/14/18/6',2,18,0000000001,NULL,NULL,6,0),(7,'specs/attrs/14/167/7',2,167,0000000001,'',NULL,8,0),(8,'specs/attrs/15/8',1,15,NULL,'',NULL,9,0),(9,'specs/attrs/15/9',1,15,NULL,'',NULL,10,0),(10,'specs/attrs/15/10',1,15,NULL,NULL,NULL,11,0),(11,'specs/attrs/54/195/11',3,195,0000000003,'specs/attrs/54/195/11/description',1,11,0),(12,'specs/attrs/16/12',NULL,16,NULL,NULL,NULL,12,0),(13,'specs/attrs/16/13',2,16,NULL,NULL,NULL,13,0),(14,'specs/attrs/14',NULL,NULL,NULL,NULL,NULL,16,0),(15,'specs/attrs/15',NULL,NULL,NULL,NULL,NULL,46,0),(16,'specs/attrs/16',NULL,NULL,NULL,NULL,NULL,15,0),(17,'specs/attrs/14/17',NULL,14,NULL,'specs/attrs/14/17/description',NULL,17,0),(18,'specs/attrs/14/18',NULL,14,NULL,NULL,NULL,18,0),(19,'specs/attrs/22/19',NULL,22,NULL,NULL,NULL,19,0),(20,'specs/attrs/22/19/20',6,19,NULL,NULL,NULL,20,0),(21,'specs/attrs/22/19/21',6,19,NULL,NULL,NULL,21,0),(22,'specs/attrs/22',NULL,NULL,NULL,NULL,NULL,40,0),(23,'specs/attrs/22/23',7,22,NULL,NULL,NULL,23,0),(24,'specs/attrs/22/24',NULL,22,NULL,NULL,NULL,24,0),(25,'specs/attrs/22/24/25',2,24,NULL,NULL,NULL,25,0),(26,'specs/attrs/22/24/26',6,24,NULL,NULL,NULL,26,0),(27,'specs/attrs/22/24/27',2,24,NULL,NULL,NULL,27,0),(28,'specs/attrs/22/24/28',3,24,0000000001,NULL,NULL,28,0),(29,'specs/attrs/22/24/29',3,24,0000000001,NULL,NULL,29,0),(30,'specs/attrs/22/30',3,22,NULL,'',2,30,0),(31,'specs/attrs/22/31',2,22,0000000004,NULL,NULL,31,0),(32,'specs/attrs/22/32',NULL,22,NULL,NULL,NULL,32,0),(33,'specs/attrs/22/32/33',2,32,0000000005,'specs/attrs/22/32/33/description',NULL,33,0),(34,'specs/attrs/22/32/34',2,32,0000000006,NULL,NULL,34,0),(35,'specs/attrs/22/32/35',2,32,0000000006,NULL,NULL,35,0),(36,'specs/attrs/22/36',NULL,22,NULL,NULL,NULL,36,0),(37,'specs/attrs/22/36/37',2,36,0000000007,NULL,NULL,37,0),(38,'specs/attrs/22/36/38',2,36,0000000006,NULL,NULL,38,0),(39,'specs/attrs/22/36/39',2,36,0000000006,NULL,NULL,39,0),(40,'specs/attrs/40',NULL,NULL,NULL,NULL,NULL,45,0),(41,'specs/attrs/40/41',7,40,NULL,'',NULL,41,0),(42,'specs/attrs/40/42',NULL,40,NULL,NULL,NULL,42,0),(43,'specs/attrs/40/42/43',7,42,NULL,'',NULL,43,0),(44,'specs/attrs/40/42/44',2,42,NULL,NULL,NULL,45,0),(45,'specs/attrs/45',1,NULL,NULL,NULL,NULL,0,0),(46,'specs/attrs/46',NULL,NULL,NULL,NULL,NULL,74,0),(47,'specs/attrs/46/47',3,46,0000000008,NULL,1,47,0),(48,'specs/attrs/46/48',3,46,0000000009,'',1,49,0),(49,'specs/attrs/46/49',3,46,0000000009,'',1,51,0),(50,'specs/attrs/46/50',3,46,0000000009,'',1,52,0),(51,'specs/attrs/46/51',3,46,0000000009,NULL,1,53,0),(52,'specs/attrs/46/52',3,46,0000000009,NULL,1,54,0),(53,'specs/attrs/46/53',5,46,NULL,NULL,NULL,55,0),(54,'specs/attrs/54',NULL,NULL,NULL,NULL,NULL,82,0),(55,'specs/attrs/54/55',2,54,0000000011,NULL,NULL,55,0),(56,'specs/attrs/54/56',2,54,0000000011,NULL,NULL,56,0),(57,'specs/attrs/54/57',NULL,54,NULL,NULL,NULL,57,0),(58,'specs/attrs/54/57/58',2,57,0000000012,NULL,NULL,58,0),(59,'specs/attrs/54/57/59',2,57,0000000012,NULL,NULL,59,0),(60,'specs/attrs/54/60',NULL,54,0000000012,'',NULL,60,0),(61,'specs/attrs/54/60/61',2,60,0000000012,NULL,NULL,61,0),(62,'specs/attrs/54/60/62',2,60,0000000012,NULL,NULL,62,0),(63,'specs/attrs/14/63',NULL,14,NULL,NULL,NULL,63,0),(64,'specs/attrs/14/63/64',3,63,NULL,NULL,3,64,0),(65,'specs/attrs/14/63/65',3,63,NULL,NULL,3,65,0),(66,'specs/attrs/16/66',6,16,NULL,'',NULL,66,0),(67,'specs/attrs/16/12/67',2,12,NULL,'specs/attrs/16/12/67/description',NULL,67,0),(68,'specs/attrs/16/12/68',2,12,NULL,NULL,NULL,68,0),(69,'specs/attrs/16/12/69',2,12,NULL,NULL,NULL,69,0),(70,'specs/attrs/70',NULL,NULL,NULL,NULL,NULL,22,0),(71,'specs/attrs/70/71',2,70,0000000002,'',NULL,71,0),(72,'specs/attrs/70/72',2,70,0000000002,'',NULL,72,0),(73,'specs/attrs/70/73',2,70,0000000002,'',NULL,73,0),(74,'specs/attrs/74',NULL,NULL,NULL,NULL,NULL,54,0),(75,'specs/attrs/74/142/75',1,142,NULL,'',NULL,75,0),(76,'specs/attrs/74/143/76',1,143,NULL,'',NULL,76,0),(77,'specs/attrs/74/77',5,74,NULL,'',NULL,77,0),(78,'specs/attrs/54/78',NULL,54,NULL,'',NULL,78,0),(79,'specs/attrs/54/78/183/79',3,183,0000000013,'',1,79,0),(80,'specs/attrs/54/78/183/80',3,183,0000000013,'',1,80,0),(81,'specs/attrs/54/78/183/81',3,183,0000000013,'',1,81,0),(82,'specs/attrs/82',2,NULL,0000000014,'',NULL,95,0),(83,'specs/attrs/40/83',1,40,NULL,'',NULL,83,0),(84,'specs/attrs/84',NULL,NULL,NULL,NULL,NULL,84,0),(85,'specs/attrs/84/85',NULL,84,NULL,'',NULL,85,0),(86,'specs/attrs/84/86',NULL,84,NULL,'',NULL,86,0),(87,'specs/attrs/84/85/87',2,85,0000000001,'',NULL,87,0),(88,'specs/attrs/84/85/88',3,85,0000000015,'',NULL,89,0),(89,'specs/attrs/84/85/89',3,85,0000000015,'',1,90,0),(90,'specs/attrs/84/85/90',2,85,0000000010,'',NULL,88,0),(91,'specs/attrs/84/86/91',2,86,0000000001,'',NULL,91,0),(92,'specs/attrs/84/86/92',3,86,0000000015,'',NULL,93,0),(93,'specs/attrs/84/86/93',3,86,0000000015,'',1,94,0),(94,'specs/attrs/84/86/94',2,86,0000000010,'',NULL,92,0),(95,'specs/attrs/95',NULL,NULL,NULL,NULL,NULL,14,0),(96,'specs/attrs/95/96',2,95,0000000016,'',NULL,96,0),(97,'specs/attrs/95/97',2,95,0000000016,'',NULL,97,0),(98,'specs/attrs/22/98',7,22,NULL,NULL,NULL,18,1),(99,'specs/attrs/22/99',7,22,NULL,NULL,NULL,558,0),(100,'specs/attrs/22/100',1,22,NULL,NULL,NULL,17,0),(103,'specs/attrs/16/12/103',2,12,NULL,'specs/attrs/16/12/103/description',NULL,70,0),(104,'specs/attrs/95/104',NULL,95,NULL,'',NULL,99,0),(106,'specs/attrs/95/106',NULL,95,NULL,'',NULL,98,0),(107,'specs/attrs/95/107',NULL,95,NULL,'',NULL,100,0),(108,'specs/attrs/95/108',NULL,95,NULL,'',NULL,101,0),(109,'specs/attrs/95/106/109',NULL,106,NULL,'',NULL,1,0),(111,'specs/attrs/95/106/111',2,106,0000000016,'',NULL,3,0),(113,'specs/attrs/95/104/113',2,104,0000000016,'',NULL,1,0),(114,'specs/attrs/95/104/114',2,104,0000000016,'',NULL,2,0),(118,'specs/attrs/95/107/118',2,107,0000000016,'',NULL,1,0),(119,'specs/attrs/95/107/119',2,107,NULL,'',NULL,2,0),(120,'specs/attrs/95/107/120',2,107,NULL,'',NULL,3,0),(121,'specs/attrs/95/108/121',NULL,108,NULL,'',NULL,1,0),(122,'specs/attrs/95/108/122',NULL,108,NULL,'',NULL,2,0),(123,'specs/attrs/95/108/121/123',2,121,0000000016,'',NULL,1,0),(124,'specs/attrs/95/108/121/124',2,121,NULL,'',NULL,2,0),(125,'specs/attrs/95/108/121/125',2,121,NULL,'',NULL,3,0),(126,'specs/attrs/95/108/122/126',2,122,0000000016,'',NULL,1,0),(127,'specs/attrs/95/108/122/127',2,122,NULL,'',NULL,2,0),(128,'specs/attrs/95/108/122/128',2,122,NULL,'',NULL,3,0),(129,'specs/attrs/95/106/109/129',2,109,0000000016,'',NULL,1,0),(130,'specs/attrs/95/106/109/130',2,109,NULL,'',NULL,2,0),(131,'specs/attrs/95/106/109/131',2,109,NULL,'',NULL,3,0),(132,'specs/attrs/95/106/111/132',2,111,0000000016,'',NULL,1,0),(133,'specs/attrs/95/106/111/133',2,111,NULL,'',NULL,2,0),(134,'specs/attrs/95/106/111/134',2,111,NULL,'',NULL,3,0),(135,'specs/attrs/95/135',NULL,95,NULL,'',NULL,102,0),(136,'specs/attrs/95/135/136',2,135,0000000016,'',NULL,1,0),(137,'specs/attrs/95/135/137',2,135,0000000016,'',NULL,2,0),(138,'specs/attrs/54/138',5,54,NULL,'',NULL,79,0),(139,'specs/attrs/40/42/139',1,42,NULL,'',NULL,44,0),(140,'specs/attrs/14/17/140',2,17,0000000001,'',NULL,4,0),(141,'specs/attrs/14/17/141',2,17,0000000001,'',NULL,5,0),(142,'specs/attrs/74/142',NULL,74,NULL,'',NULL,78,0),(143,'specs/attrs/74/143',NULL,74,NULL,'',NULL,79,0),(144,'specs/attrs/74/142/144',6,142,NULL,'',NULL,76,0),(145,'specs/attrs/74/143/145',6,143,NULL,'',NULL,77,0),(146,'specs/attrs/74/142/146',3,142,0000000001,'',NULL,77,0),(147,'specs/attrs/74/143/147',3,143,0000000001,'',NULL,78,0),(148,'specs/attrs/74/142/148',3,142,0000000001,'',NULL,78,0),(149,'specs/attrs/74/143/149',3,143,0000000001,'',NULL,79,0),(150,'specs/attrs/74/142/150',6,142,NULL,'',NULL,79,0),(151,'specs/attrs/74/143/151',6,143,NULL,'',NULL,80,0),(152,'specs/attrs/74/142/152',5,142,NULL,'',NULL,80,0),(153,'specs/attrs/74/142/153',5,142,NULL,'',NULL,81,0),(154,'specs/attrs/74/143/154',5,143,NULL,'',NULL,81,0),(155,'specs/attrs/74/143/155',5,143,NULL,'',NULL,82,0),(156,'specs/attrs/22/156',6,22,NULL,'',NULL,559,0),(157,'specs/attrs/157',6,NULL,NULL,NULL,NULL,103,0),(158,'specs/attrs/54/158',3,54,0000000002,'',NULL,80,0),(159,'specs/attrs/22/24/159',2,24,0000000011,'',NULL,30,0),(160,'specs/attrs/46/160',3,46,0000000009,'',NULL,56,0),(161,'specs/attrs/46/161',3,46,0000000003,'',NULL,57,0),(162,'specs/attrs/84/85/162',3,85,0000000001,'',NULL,91,0),(163,'specs/attrs/84/86/163',3,86,0000000001,'',NULL,95,0),(164,'specs/attrs/84/164',1,84,NULL,'',NULL,87,0),(165,'specs/attrs/84/165',6,84,NULL,'',NULL,88,0),(167,'specs/attrs/14/167',NULL,14,NULL,'specs/attrs/14/167/description',NULL,64,0),(168,'specs/attrs/14/167/168',2,167,0000000001,'',NULL,9,0),(170,'specs/attrs/170',1,NULL,NULL,'',NULL,104,255),(171,'specs/attrs/22/32/171',3,32,0000000017,'specs/attrs/22/32/171/description',NULL,36,0),(172,'specs/attrs/22/32/172',3,32,0000000005,'specs/attrs/22/32/172/description',NULL,37,0),(173,'specs/attrs/22/32/173',3,32,0000000005,'specs/attrs/22/32/173/description',NULL,38,0),(174,'specs/attrs/22/32/174',3,32,0000000005,'specs/attrs/22/32/174/description',NULL,39,0),(175,'specs/attrs/46/175',3,46,0000000009,'',1,50,0),(176,'specs/attrs/14/167/176',2,167,0000000001,'',NULL,7,0),(177,'specs/attrs/22/32/177',3,32,0000000005,'',1,40,0),(178,'specs/attrs/22/32/178',3,32,0000000005,'specs/attrs/22/32/178/description',1,41,0),(179,'specs/attrs/22/179',6,22,NULL,'',NULL,560,0),(180,'specs/attrs/46/180',3,46,0000000009,'',NULL,48,0),(181,'specs/attrs/181',NULL,NULL,NULL,'',NULL,70,0),(182,'specs/attrs/181/182',3,181,0000000019,'',NULL,1,0),(183,'specs/attrs/54/78/183',NULL,78,NULL,'',NULL,82,0),(184,'specs/attrs/54/78/184',NULL,78,NULL,'',NULL,83,0),(185,'specs/attrs/54/78/184/185',3,184,0000000013,'',1,1,0),(186,'specs/attrs/54/78/184/186',3,184,0000000013,'',1,2,0),(187,'specs/attrs/54/78/184/187',3,184,0000000013,'',1,3,0),(188,'specs/attrs/54/78/184/188',3,184,0000000013,'',1,4,0),(189,'specs/attrs/54/78/189',NULL,78,NULL,'',NULL,84,0),(190,'specs/attrs/54/78/189/190',3,189,0000000013,'',1,1,0),(191,'specs/attrs/54/78/189/191',3,189,0000000013,'',1,2,0),(192,'specs/attrs/54/78/192',NULL,78,NULL,'',NULL,85,0),(193,'specs/attrs/54/78/192/193',3,192,0000000013,'',1,1,0),(194,'specs/attrs/54/78/192/194',3,192,0000000013,'',1,2,0),(195,'specs/attrs/54/195',NULL,54,NULL,'',NULL,83,0),(196,'specs/attrs/54/195/196',3,195,0000000003,'specs/attrs/54/195/196/description',1,12,0),(197,'specs/attrs/54/195/197',3,195,0000000003,'specs/attrs/54/195/197/description',1,13,0),(198,'specs/attrs/54/198',3,54,NULL,'',1,84,0),(199,'specs/attrs/54/78/199',NULL,78,NULL,'',NULL,86,0),(200,'specs/attrs/54/78/199/200',3,199,0000000013,'',1,1,0),(201,'specs/attrs/54/78/199/201',3,199,0000000013,'',1,2,0),(202,'specs/attrs/54/78/199/202',3,199,0000000013,'specs/attrs/54/78/199/202/description',1,3,0),(203,'specs/attrs/14/17/203',2,17,0000000001,'',NULL,6,0),(204,'specs/attrs/16/204',6,16,NULL,'',NULL,67,0),(205,'specs/attrs/54/205',3,54,0000000002,'',NULL,81,0),(206,'specs/attrs/22/206',7,22,NULL,'',NULL,561,0),(207,'specs/attrs/22/207',7,22,NULL,'',NULL,562,0),(208,'specs/attrs/15/208',NULL,15,NULL,'',NULL,8,0),(209,'specs/attrs/15/208/209',7,208,NULL,'',NULL,1,0),(210,'specs/attrs/15/208/210',7,208,NULL,'',NULL,2,0),(211,'specs/attrs/15/208/211',NULL,208,NULL,'',NULL,3,0),(212,'specs/attrs/15/208/212',5,208,NULL,'',NULL,4,0),(213,'specs/attrs/15/208/211/213',5,211,NULL,'',NULL,1,0),(214,'specs/attrs/15/208/211/214',6,211,NULL,'',NULL,2,0),(215,'specs/attrs/15/208/211/215',7,211,NULL,'',NULL,3,0),(216,'specs/attrs/15/208/211/216',5,211,NULL,'',NULL,4,0),(217,'specs/attrs/15/217',NULL,15,NULL,'',NULL,12,0),(218,'specs/attrs/15/217/218',7,217,NULL,'',NULL,1,0),(219,'specs/attrs/15/217/219',7,217,NULL,'',NULL,2,0),(220,'specs/attrs/15/217/220',NULL,217,NULL,'',NULL,3,0),(221,'specs/attrs/15/217/221',5,217,NULL,'',NULL,4,0),(222,'specs/attrs/15/217/220/222',5,220,NULL,'',NULL,1,0),(223,'specs/attrs/15/217/220/223',6,220,NULL,'',NULL,2,0),(224,'specs/attrs/15/217/220/224',7,220,NULL,'',NULL,3,0),(225,'specs/attrs/15/217/220/225',5,220,NULL,'',NULL,4,0),(226,'specs/attrs/54/226',3,54,0000000020,'',NULL,82,0);
/*!40000 ALTER TABLE `attrs_attributes` ENABLE KEYS */;

--
-- Table structure for table `attrs_item_types`
--

DROP TABLE IF EXISTS `attrs_item_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_item_types` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_item_types`
--

/*!40000 ALTER TABLE `attrs_item_types` DISABLE KEYS */;
INSERT INTO `attrs_item_types` VALUES (1,'Автомобиль'),(3,'Двигатель'),(2,'Модификация автомобиля');
/*!40000 ALTER TABLE `attrs_item_types` ENABLE KEYS */;

--
-- Table structure for table `attrs_list_options`
--

DROP TABLE IF EXISTS `attrs_list_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_list_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `position` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position` (`position`,`attribute_id`,`parent_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `attrs_list_options_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_list_options_fk1` FOREIGN KEY (`parent_id`) REFERENCES `attrs_list_options` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=216 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_list_options`
--

/*!40000 ALTER TABLE `attrs_list_options` DISABLE KEYS */;
INSERT INTO `attrs_list_options` VALUES (1,20,'specs/attrs/22/19/20/options/1',1,NULL),(2,20,'specs/attrs/22/19/20/options/2',2,NULL),(3,20,'specs/attrs/22/19/20/options/3',3,NULL),(4,21,'specs/attrs/22/19/21/options/4',1,NULL),(5,21,'specs/attrs/22/19/21/options/5',2,NULL),(6,23,'specs/attrs/22/23/options/6',1,NULL),(7,26,'specs/attrs/22/24/26/options/7',1,NULL),(8,26,'specs/attrs/22/24/26/options/8',2,NULL),(9,26,'specs/attrs/22/24/26/options/9',3,NULL),(10,26,'specs/attrs/22/24/26/options/10',4,NULL),(11,66,'specs/attrs/16/66/options/11',1,NULL),(12,66,'specs/attrs/16/66/options/12',2,NULL),(13,66,'specs/attrs/16/66/options/13',3,NULL),(14,41,'specs/attrs/40/41/options/14',1,NULL),(15,41,'specs/attrs/40/41/options/15',2,NULL),(16,41,'specs/attrs/40/41/options/16',3,NULL),(17,41,'specs/attrs/40/41/options/17',4,16),(18,41,'specs/attrs/40/41/options/18',5,16),(19,41,'specs/attrs/40/41/options/19',6,16),(20,43,'specs/attrs/40/42/43/options/20',1,NULL),(21,43,'specs/attrs/40/42/43/options/21',2,NULL),(22,43,'specs/attrs/40/42/43/options/22',3,NULL),(23,43,'specs/attrs/40/42/43/options/23',4,NULL),(24,23,'specs/attrs/22/23/options/24',2,NULL),(25,23,'specs/attrs/22/23/options/25',1,24),(26,23,'specs/attrs/22/23/options/26',4,24),(27,23,'specs/attrs/22/23/options/27',5,24),(28,98,'specs/attrs/22/98/options/28',1,NULL),(29,98,'specs/attrs/22/98/options/29',2,NULL),(30,98,'specs/attrs/22/98/options/30',3,NULL),(31,98,'specs/attrs/22/98/options/31',4,NULL),(32,98,'specs/attrs/22/98/options/32',5,NULL),(33,98,'specs/attrs/22/98/options/33',6,NULL),(34,98,'specs/attrs/22/98/options/34',1,32),(35,98,'specs/attrs/22/98/options/35',2,32),(36,98,'specs/attrs/22/98/options/36',1,28),(37,98,'specs/attrs/22/98/options/37',2,28),(38,98,'specs/attrs/22/98/options/38',3,28),(39,98,'specs/attrs/22/98/options/39',4,28),(40,98,'specs/attrs/22/98/options/40',5,28),(41,98,'specs/attrs/22/98/options/41',6,28),(42,98,'specs/attrs/22/98/options/42',7,28),(43,98,'specs/attrs/22/98/options/43',8,28),(44,98,'specs/attrs/22/98/options/44',9,28),(45,98,'specs/attrs/22/98/options/45',10,28),(46,99,'specs/attrs/22/99/options/46',1,NULL),(47,99,'specs/attrs/22/99/options/47',2,NULL),(48,99,'specs/attrs/22/99/options/48',1,47),(49,99,'specs/attrs/22/99/options/49',3,47),(50,43,'specs/attrs/40/42/43/options/50',5,NULL),(51,43,'specs/attrs/40/42/43/options/51',1,50),(52,43,'specs/attrs/40/42/43/options/52',2,50),(54,99,'specs/attrs/22/99/options/54',2,47),(55,23,'specs/attrs/22/23/options/55',3,24),(56,41,'specs/attrs/40/41/options/56',7,NULL),(57,41,'specs/attrs/40/41/options/57',8,NULL),(58,144,'specs/attrs/74/142/144/options/58',1,NULL),(59,144,'specs/attrs/74/142/144/options/59',2,NULL),(60,145,'specs/attrs/74/143/145/options/60',1,NULL),(61,145,'specs/attrs/74/143/145/options/61',2,NULL),(62,150,'specs/attrs/74/142/150/options/62',1,NULL),(63,150,'specs/attrs/74/142/150/options/63',2,NULL),(64,150,'specs/attrs/74/142/150/options/64',3,NULL),(65,151,'specs/attrs/74/143/151/options/65',1,NULL),(66,151,'specs/attrs/74/143/151/options/66',2,NULL),(67,151,'specs/attrs/74/143/151/options/67',3,NULL),(68,156,'specs/attrs/22/156/options/68',1,NULL),(69,156,'specs/attrs/22/156/options/69',2,NULL),(70,156,'specs/attrs/22/156/options/70',3,NULL),(71,157,'specs/attrs/157/options/71',1,NULL),(72,157,'specs/attrs/157/options/72',2,NULL),(73,157,'specs/attrs/157/options/73',3,NULL),(74,157,'specs/attrs/157/options/74',4,NULL),(75,157,'specs/attrs/157/options/75',5,NULL),(76,157,'specs/attrs/157/options/76',6,NULL),(77,157,'specs/attrs/157/options/77',7,NULL),(78,165,'specs/attrs/84/165/options/78',1,NULL),(79,165,'specs/attrs/84/165/options/79',2,NULL),(80,165,'specs/attrs/84/165/options/80',3,NULL),(81,179,'specs/attrs/22/179/options/81',1,NULL),(82,179,'specs/attrs/22/179/options/82',2,NULL),(83,156,'specs/attrs/22/156/options/83',4,NULL),(84,98,'specs/attrs/22/98/options/84',11,NULL),(85,204,'specs/attrs/16/204/options/85',1,NULL),(86,204,'specs/attrs/16/204/options/86',2,NULL),(87,43,'specs/attrs/40/42/43/options/87',6,NULL),(88,206,'specs/attrs/22/206/options/88',1,NULL),(89,206,'specs/attrs/22/206/options/89',2,NULL),(90,206,'specs/attrs/22/206/options/90',3,NULL),(91,206,'specs/attrs/22/206/options/91',4,NULL),(92,206,'specs/attrs/22/206/options/92',5,NULL),(93,206,'specs/attrs/22/206/options/93',6,88),(94,206,'specs/attrs/22/206/options/94',7,88),(95,206,'specs/attrs/22/206/options/95',8,88),(96,206,'specs/attrs/22/206/options/96',9,89),(97,206,'specs/attrs/22/206/options/97',10,89),(98,206,'specs/attrs/22/206/options/98',11,89),(99,206,'specs/attrs/22/206/options/99',12,89),(100,206,'specs/attrs/22/206/options/100',13,88),(101,26,'specs/attrs/22/24/26/options/101',5,NULL),(102,207,'specs/attrs/22/207/options/102',1,NULL),(103,207,'specs/attrs/22/207/options/103',2,NULL),(104,207,'specs/attrs/22/207/options/104',3,NULL),(105,207,'specs/attrs/22/207/options/105',4,103),(106,207,'specs/attrs/22/207/options/106',5,103),(107,207,'specs/attrs/22/207/options/107',6,103),(108,209,'specs/attrs/15/208/209/options/108',1,NULL),(109,209,'specs/attrs/15/208/209/options/109',2,NULL),(110,209,'specs/attrs/15/208/209/options/110',3,NULL),(111,209,'specs/attrs/15/208/209/options/111',4,NULL),(112,209,'specs/attrs/15/208/209/options/112',5,NULL),(113,209,'specs/attrs/15/208/209/options/113',6,NULL),(114,209,'specs/attrs/15/208/209/options/114',7,108),(115,209,'specs/attrs/15/208/209/options/115',8,108),(116,209,'specs/attrs/15/208/209/options/116',9,109),(117,209,'specs/attrs/15/208/209/options/117',10,109),(118,209,'specs/attrs/15/208/209/options/118',11,117),(119,209,'specs/attrs/15/208/209/options/119',12,117),(120,209,'specs/attrs/15/208/209/options/120',13,117),(121,209,'specs/attrs/15/208/209/options/121',14,117),(122,209,'specs/attrs/15/208/209/options/122',15,117),(123,209,'specs/attrs/15/208/209/options/123',16,117),(124,209,'specs/attrs/15/208/209/options/124',17,112),(125,209,'specs/attrs/15/208/209/options/125',18,112),(126,210,'specs/attrs/15/208/210/options/126',1,NULL),(127,210,'specs/attrs/15/208/210/options/127',2,NULL),(128,210,'specs/attrs/15/208/210/options/128',3,NULL),(129,210,'specs/attrs/15/208/210/options/129',4,126),(130,210,'specs/attrs/15/208/210/options/130',5,126),(131,210,'specs/attrs/15/208/210/options/131',6,126),(132,210,'specs/attrs/15/208/210/options/132',7,127),(133,210,'specs/attrs/15/208/210/options/133',8,127),(134,210,'specs/attrs/15/208/210/options/134',9,127),(135,210,'specs/attrs/15/208/210/options/135',10,127),(136,210,'specs/attrs/15/208/210/options/136',11,127),(137,210,'specs/attrs/15/208/210/options/137',12,127),(138,210,'specs/attrs/15/208/210/options/138',13,127),(139,210,'specs/attrs/15/208/210/options/139',14,128),(140,210,'specs/attrs/15/208/210/options/140',15,128),(141,210,'specs/attrs/15/208/210/options/141',16,130),(142,210,'specs/attrs/15/208/210/options/142',17,130),(143,210,'specs/attrs/15/208/210/options/143',18,130),(144,210,'specs/attrs/15/208/210/options/144',19,131),(145,210,'specs/attrs/15/208/210/options/145',20,131),(146,210,'specs/attrs/15/208/210/options/146',21,131),(147,210,'specs/attrs/15/208/210/options/147',22,138),(148,210,'specs/attrs/15/208/210/options/148',23,147),(149,210,'specs/attrs/15/208/210/options/149',24,140),(150,210,'specs/attrs/15/208/210/options/150',25,140),(151,210,'specs/attrs/15/208/210/options/151',26,140),(152,214,'specs/attrs/15/208/211/214/options/152',1,NULL),(153,214,'specs/attrs/15/208/211/214/options/153',2,NULL),(154,215,'specs/attrs/15/208/211/215/options/154',1,NULL),(155,215,'specs/attrs/15/208/211/215/options/155',2,NULL),(156,215,'specs/attrs/15/208/211/215/options/156',3,155),(157,215,'specs/attrs/15/208/211/215/options/157',4,155),(158,215,'specs/attrs/15/208/211/215/options/158',5,155),(159,215,'specs/attrs/15/208/211/215/options/159',6,NULL),(160,218,'specs/attrs/15/217/218/options/160',1,NULL),(161,218,'specs/attrs/15/217/218/options/161',2,160),(162,218,'specs/attrs/15/217/218/options/162',3,160),(163,218,'specs/attrs/15/217/218/options/163',4,NULL),(164,218,'specs/attrs/15/217/218/options/164',5,163),(165,218,'specs/attrs/15/217/218/options/165',6,163),(166,218,'specs/attrs/15/217/218/options/166',7,165),(167,218,'specs/attrs/15/217/218/options/167',8,165),(168,218,'specs/attrs/15/217/218/options/168',9,165),(169,218,'specs/attrs/15/217/218/options/169',10,165),(170,218,'specs/attrs/15/217/218/options/170',11,165),(171,218,'specs/attrs/15/217/218/options/171',12,165),(172,218,'specs/attrs/15/217/218/options/172',13,NULL),(173,218,'specs/attrs/15/217/218/options/173',14,NULL),(174,218,'specs/attrs/15/217/218/options/174',15,NULL),(175,218,'specs/attrs/15/217/218/options/175',16,174),(176,218,'specs/attrs/15/217/218/options/176',17,174),(177,218,'specs/attrs/15/217/218/options/177',18,NULL),(178,209,'specs/attrs/15/208/209/options/178',19,NULL),(179,218,'specs/attrs/15/217/218/options/179',19,NULL),(180,219,'specs/attrs/15/217/219/options/180',1,NULL),(181,219,'specs/attrs/15/217/219/options/181',2,180),(182,219,'specs/attrs/15/217/219/options/182',3,180),(183,219,'specs/attrs/15/217/219/options/183',4,182),(184,219,'specs/attrs/15/217/219/options/184',5,182),(185,219,'specs/attrs/15/217/219/options/185',6,182),(186,219,'specs/attrs/15/217/219/options/186',7,180),(187,219,'specs/attrs/15/217/219/options/187',8,186),(188,219,'specs/attrs/15/217/219/options/188',9,186),(189,219,'specs/attrs/15/217/219/options/189',10,186),(190,219,'specs/attrs/15/217/219/options/190',11,NULL),(191,219,'specs/attrs/15/217/219/options/191',12,190),(192,219,'specs/attrs/15/217/219/options/192',13,190),(193,219,'specs/attrs/15/217/219/options/193',14,190),(194,219,'specs/attrs/15/217/219/options/194',15,190),(195,219,'specs/attrs/15/217/219/options/195',16,190),(196,219,'specs/attrs/15/217/219/options/196',17,190),(197,219,'specs/attrs/15/217/219/options/197',18,190),(198,219,'specs/attrs/15/217/219/options/198',19,197),(199,219,'specs/attrs/15/217/219/options/199',20,198),(200,219,'specs/attrs/15/217/219/options/200',21,NULL),(201,219,'specs/attrs/15/217/219/options/201',22,200),(202,219,'specs/attrs/15/217/219/options/202',23,200),(203,219,'specs/attrs/15/217/219/options/203',24,202),(204,219,'specs/attrs/15/217/219/options/204',25,202),(205,219,'specs/attrs/15/217/219/options/205',26,202),(206,223,'specs/attrs/15/217/220/223/options/206',1,NULL),(207,223,'specs/attrs/15/217/220/223/options/207',2,NULL),(208,224,'specs/attrs/15/217/220/224/options/208',1,NULL),(209,224,'specs/attrs/15/217/220/224/options/209',2,NULL),(210,224,'specs/attrs/15/217/220/224/options/210',3,209),(211,224,'specs/attrs/15/217/220/224/options/211',4,209),(212,224,'specs/attrs/15/217/220/224/options/212',5,209),(213,224,'specs/attrs/15/217/220/224/options/213',6,NULL),(214,179,'specs/attrs/22/179/options/liquid-air',3,NULL),(215,99,'specs/attrs/engine/turbo/options/x6',4,47);
/*!40000 ALTER TABLE `attrs_list_options` ENABLE KEYS */;

--
-- Table structure for table `attrs_types`
--

DROP TABLE IF EXISTS `attrs_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_types` (
  `id` smallint(6) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `element` varchar(30) NOT NULL,
  `maxlength` int(10) unsigned DEFAULT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_types`
--

/*!40000 ALTER TABLE `attrs_types` DISABLE KEYS */;
INSERT INTO `attrs_types` VALUES (000001,'Строка','text',255,60),(000002,'Целое число','text',15,5),(000003,'Число с плавающей точкой','text',20,5),(000004,'Текст','textarea',0,NULL),(000005,'Флаг','select',0,NULL),(000006,'Список значений','select',0,NULL),(000007,'Дерево значений','select',NULL,NULL);
/*!40000 ALTER TABLE `attrs_types` ENABLE KEYS */;

--
-- Table structure for table `attrs_units`
--

DROP TABLE IF EXISTS `attrs_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `abbr` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `abbr` (`abbr`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_units`
--

/*!40000 ALTER TABLE `attrs_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_units` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values`
--

DROP TABLE IF EXISTS `attrs_user_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `conflict` tinyint(4) NOT NULL DEFAULT '0',
  `weight` double DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  KEY `update_date` (`update_date`),
  CONSTRAINT `attrs_user_values_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_user_values_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `attrs_user_values_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values`
--

/*!40000 ALTER TABLE `attrs_user_values` DISABLE KEYS */;
INSERT INTO `attrs_user_values` VALUES (20,1,1,1,'2016-11-25 18:31:46','2016-11-25 18:31:46',0,1);
/*!40000 ALTER TABLE `attrs_user_values` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values_float`
--

DROP TABLE IF EXISTS `attrs_user_values_float`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values_float` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` double DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attrs_user_values_float_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_user_values_float_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `attrs_user_values_float_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values_float`
--

/*!40000 ALTER TABLE `attrs_user_values_float` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_user_values_float` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values_int`
--

DROP TABLE IF EXISTS `attrs_user_values_int`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values_int` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` int(11) DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `attrs_user_values_int_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_user_values_int_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `attrs_user_values_int_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values_int`
--

/*!40000 ALTER TABLE `attrs_user_values_int` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_user_values_int` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values_list`
--

DROP TABLE IF EXISTS `attrs_user_values_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values_list` (
  `attribute_id` int(11) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(4) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `value` int(11) unsigned DEFAULT NULL,
  `ordering` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`,`ordering`),
  KEY `FK_attrs_user_values_list_attrs_item_types_id` (`item_type_id`),
  KEY `FK_attrs_user_values_list_users_id` (`user_id`),
  KEY `FK_attrs_user_values_list_attrs_list_options_id` (`value`),
  CONSTRAINT `FK_attrs_user_values_list_attrs_attributes_id` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `FK_attrs_user_values_list_attrs_item_types_id` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `FK_attrs_user_values_list_attrs_list_options_id` FOREIGN KEY (`value`) REFERENCES `attrs_list_options` (`id`),
  CONSTRAINT `FK_attrs_user_values_list_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values_list`
--

/*!40000 ALTER TABLE `attrs_user_values_list` DISABLE KEYS */;
INSERT INTO `attrs_user_values_list` VALUES (20,1,1,1,1,1);
/*!40000 ALTER TABLE `attrs_user_values_list` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values_string`
--

DROP TABLE IF EXISTS `attrs_user_values_string`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values_string` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attrs_user_values_string_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_user_values_string_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `attrs_user_values_string_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values_string`
--

/*!40000 ALTER TABLE `attrs_user_values_string` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_user_values_string` ENABLE KEYS */;

--
-- Table structure for table `attrs_user_values_text`
--

DROP TABLE IF EXISTS `attrs_user_values_text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_user_values_text` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` text,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`user_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attrs_user_values_text_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_user_values_text_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `attrs_user_values_text_fk2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 19456 kB; (`attribute_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_user_values_text`
--

/*!40000 ALTER TABLE `attrs_user_values_text` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_user_values_text` ENABLE KEYS */;

--
-- Table structure for table `attrs_values`
--

DROP TABLE IF EXISTS `attrs_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `conflict` tinyint(4) NOT NULL DEFAULT '0',
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`,`item_id`) USING BTREE,
  CONSTRAINT `attrs_values_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_values_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values`
--

/*!40000 ALTER TABLE `attrs_values` DISABLE KEYS */;
INSERT INTO `attrs_values` VALUES (20,1,1,0,'2016-11-25 18:31:46');
/*!40000 ALTER TABLE `attrs_values` ENABLE KEYS */;

--
-- Table structure for table `attrs_values_float`
--

DROP TABLE IF EXISTS `attrs_values_float`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values_float` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` double DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `IX_attrs_values_float_value` (`item_type_id`,`attribute_id`,`value`,`item_id`),
  CONSTRAINT `attrs_values_float_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_values_float_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=79;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values_float`
--

/*!40000 ALTER TABLE `attrs_values_float` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_values_float` ENABLE KEYS */;

--
-- Table structure for table `attrs_values_int`
--

DROP TABLE IF EXISTS `attrs_values_int`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values_int` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` int(11) DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  CONSTRAINT `attrs_values_int_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_values_int_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values_int`
--

/*!40000 ALTER TABLE `attrs_values_int` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_values_int` ENABLE KEYS */;

--
-- Table structure for table `attrs_values_list`
--

DROP TABLE IF EXISTS `attrs_values_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values_list` (
  `attribute_id` int(11) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(4) unsigned NOT NULL,
  `value` int(11) unsigned DEFAULT NULL,
  `ordering` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`,`ordering`),
  KEY `FK_attrs_values_list_attrs_item_types_id` (`item_type_id`),
  KEY `FK_attrs_values_list_attrs_list_options_id` (`value`),
  CONSTRAINT `FK_attrs_values_list_attrs_attributes_id` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `FK_attrs_values_list_attrs_item_types_id` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`),
  CONSTRAINT `FK_attrs_values_list_attrs_list_options_id` FOREIGN KEY (`value`) REFERENCES `attrs_list_options` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values_list`
--

/*!40000 ALTER TABLE `attrs_values_list` DISABLE KEYS */;
INSERT INTO `attrs_values_list` VALUES (20,1,1,1,1);
/*!40000 ALTER TABLE `attrs_values_list` ENABLE KEYS */;

--
-- Table structure for table `attrs_values_string`
--

DROP TABLE IF EXISTS `attrs_values_string`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values_string` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  CONSTRAINT `attrs_values_string_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_values_string_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values_string`
--

/*!40000 ALTER TABLE `attrs_values_string` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_values_string` ENABLE KEYS */;

--
-- Table structure for table `attrs_values_text`
--

DROP TABLE IF EXISTS `attrs_values_text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_values_text` (
  `attribute_id` int(10) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  `value` text,
  PRIMARY KEY (`attribute_id`,`item_id`,`item_type_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `item_type_id` (`item_type_id`),
  CONSTRAINT `attrs_values_text_fk` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`),
  CONSTRAINT `attrs_values_text_fk1` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_values_text`
--

/*!40000 ALTER TABLE `attrs_values_text` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_values_text` ENABLE KEYS */;

--
-- Table structure for table `attrs_zone_attributes`
--

DROP TABLE IF EXISTS `attrs_zone_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_zone_attributes` (
  `zone_id` int(10) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  `position` int(10) unsigned NOT NULL,
  PRIMARY KEY (`attribute_id`,`zone_id`),
  UNIQUE KEY `zone_id` (`zone_id`,`position`),
  CONSTRAINT `attrs_zone_attributes_fk` FOREIGN KEY (`zone_id`) REFERENCES `attrs_zones` (`id`),
  CONSTRAINT `attrs_zone_attributes_fk1` FOREIGN KEY (`attribute_id`) REFERENCES `attrs_attributes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_zone_attributes`
--

/*!40000 ALTER TABLE `attrs_zone_attributes` DISABLE KEYS */;
/*!40000 ALTER TABLE `attrs_zone_attributes` ENABLE KEYS */;

--
-- Table structure for table `attrs_zones`
--

DROP TABLE IF EXISTS `attrs_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attrs_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `item_type_id` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`item_type_id`),
  KEY `item_type_id` (`item_type_id`),
  CONSTRAINT `attrs_zones_fk` FOREIGN KEY (`item_type_id`) REFERENCES `attrs_item_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attrs_zones`
--

/*!40000 ALTER TABLE `attrs_zones` DISABLE KEYS */;
INSERT INTO `attrs_zones` VALUES (3,'Автобусы',1),(2,'Грузовые автомобили',1),(5,'Двигатели',3),(1,'Легковые автомобили',1),(4,'Модификации',2);
/*!40000 ALTER TABLE `attrs_zones` ENABLE KEYS */;

--
-- Table structure for table `banned_ip`
--

DROP TABLE IF EXISTS `banned_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banned_ip` (
  `up_to` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `by_user_id` int(10) unsigned DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`),
  KEY `up_to` (`up_to`),
  KEY `by_user_id` (`by_user_id`),
  CONSTRAINT `banned_ip_ibfk_1` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banned_ip`
--

/*!40000 ALTER TABLE `banned_ip` DISABLE KEYS */;
/*!40000 ALTER TABLE `banned_ip` ENABLE KEYS */;

--
-- Table structure for table `brand_alias`
--

DROP TABLE IF EXISTS `brand_alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_alias` (
  `name` varchar(255) NOT NULL,
  `brand_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`name`),
  KEY `FK_brand_alias_brands_id` (`brand_id`),
  CONSTRAINT `FK_brand_alias_brands_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_alias`
--

/*!40000 ALTER TABLE `brand_alias` DISABLE KEYS */;
/*!40000 ALTER TABLE `brand_alias` ENABLE KEYS */;

--
-- Table structure for table `brand_engine`
--

DROP TABLE IF EXISTS `brand_engine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_engine` (
  `brand_id` int(10) unsigned NOT NULL,
  `engine_id` int(10) unsigned NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`,`engine_id`),
  KEY `engine_id` (`engine_id`),
  CONSTRAINT `brand_fk` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `engine_fk2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_engine`
--

/*!40000 ALTER TABLE `brand_engine` DISABLE KEYS */;
/*!40000 ALTER TABLE `brand_engine` ENABLE KEYS */;

--
-- Table structure for table `brand_language`
--

DROP TABLE IF EXISTS `brand_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_language` (
  `brand_id` int(11) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`brand_id`,`language`),
  CONSTRAINT `FK_brand_language_brands_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_language`
--

/*!40000 ALTER TABLE `brand_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `brand_language` ENABLE KEYS */;

--
-- Table structure for table `brand_type_language`
--

DROP TABLE IF EXISTS `brand_type_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_type_language` (
  `brand_type_id` int(11) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `index_description` varchar(255) NOT NULL,
  PRIMARY KEY (`brand_type_id`,`language`),
  CONSTRAINT `FK_brand_type_language_brand_types_id` FOREIGN KEY (`brand_type_id`) REFERENCES `brand_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_type_language`
--

/*!40000 ALTER TABLE `brand_type_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `brand_type_language` ENABLE KEYS */;

--
-- Table structure for table `brand_types`
--

DROP TABLE IF EXISTS `brand_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_types` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  `index_items` smallint(5) unsigned NOT NULL DEFAULT '10',
  `index_description` varchar(255) NOT NULL,
  `ordering` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_types`
--

/*!40000 ALTER TABLE `brand_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `brand_types` ENABLE KEYS */;

--
-- Table structure for table `brand_vehicle_language`
--

DROP TABLE IF EXISTS `brand_vehicle_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_vehicle_language` (
  `brand_id` int(10) unsigned NOT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `language` varchar(2) NOT NULL,
  `name` varchar(70) NOT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`brand_id`,`vehicle_id`,`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_vehicle_language`
--

/*!40000 ALTER TABLE `brand_vehicle_language` DISABLE KEYS */;
INSERT INTO `brand_vehicle_language` VALUES (1,1,'en','BMW 335i',1);
/*!40000 ALTER TABLE `brand_vehicle_language` ENABLE KEYS */;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folder` varchar(50) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `position` smallint(5) unsigned NOT NULL DEFAULT '0',
  `group_id` int(10) unsigned DEFAULT NULL,
  `type_id` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `activepictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `_description` mediumtext,
  `full_name` varchar(50) DEFAULT NULL,
  `engines_count` int(10) unsigned NOT NULL DEFAULT '0',
  `carpictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `enginepictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `logopictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `unsortedpictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `mixedpictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `cars_count` int(10) unsigned NOT NULL DEFAULT '0',
  `new_style` tinyint(1) NOT NULL DEFAULT '0',
  `manual_sort` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `new_models_style` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `conceptcars_count` int(10) unsigned NOT NULL DEFAULT '0',
  `parent_brand_id` int(10) unsigned DEFAULT NULL,
  `twins_groups_count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `new_twins_groups_count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `from_year` smallint(5) unsigned NOT NULL DEFAULT '0',
  `to_year` smallint(5) unsigned NOT NULL DEFAULT '0',
  `img` int(10) unsigned DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder` (`folder`),
  KEY `group_id` (`group_id`),
  KEY `position` (`position`,`name`),
  KEY `type_id` (`type_id`,`position`,`name`),
  KEY `parent_brand_id` (`parent_brand_id`),
  KEY `text_id` (`text_id`),
  CONSTRAINT `brands_fk` FOREIGN KEY (`parent_brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `brands_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1893 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`parent_brand_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'bmw','BMW',0,NULL,1,0,NULL,NULL,0,0,0,0,0,0,0,0,0,0,0,NULL,0,0,0,0,NULL,NULL),(2,'test-brand','Test brand',0,NULL,1,0,NULL,NULL,0,0,0,0,0,0,0,0,0,0,0,NULL,0,0,0,0,NULL,NULL);
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;

--
-- Table structure for table `brand_item`
--

DROP TABLE IF EXISTS `brand_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_item` (
  `brand_id` int(10) unsigned NOT NULL DEFAULT '0',
  `car_id` int(10) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `catname` varchar(70) DEFAULT NULL,
  PRIMARY KEY (`brand_id`,`car_id`),
  UNIQUE KEY `brand_id` (`brand_id`,`catname`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `brand_item_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `brand_item_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB; (`car_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_item`
--

/*!40000 ALTER TABLE `brand_item` DISABLE KEYS */;
INSERT INTO `brand_item` VALUES (1,1,0,'first-car'),(1,2,0,'second-car'),(1,3,0,'test-car-3'),(1,4,0,'test-car-4'),(1,5,0,'test-car-5'),(1,6,0,'test-car-6'),(1,7,0,'test-car-7'),(1,8,0,'test-car-8'),(1,9,0,'test-car-9'),(1,10,0,'test-car-10'),(1,11,0,'test-car-11'),(1,12,0,'test-car-12'),(1,13,0,'test-car-13'),(1,14,0,'test-car-14'),(1,15,0,'test-car-15'),(1,16,0,'test-car-16'),(1,17,0,'test-car-17'),(1,18,0,'test-car-18'),(1,19,0,'test-car-19'),(1,20,0,'test-car-20'),(1,21,0,'test-car-21'),(1,22,0,'test-car-22'),(1,23,0,'test-car-23'),(1,24,0,'test-car-24'),(1,25,0,'test-car-25'),(1,26,0,'test-car-26'),(1,27,0,'test-car-27'),(1,28,0,'test-car-28'),(1,29,0,'test-car-29'),(1,30,0,'test-car-30'),(1,31,0,'test-car-31'),(1,32,0,'test-car-32'),(1,33,0,'test-car-33'),(1,34,0,'test-car-34'),(1,35,0,'test-car-35'),(1,36,0,'test-car-36'),(1,37,0,'test-car-37'),(1,38,0,'test-car-38'),(1,39,0,'test-car-39'),(1,40,0,'test-car-40'),(1,41,0,'test-car-41'),(1,42,0,'test-car-42'),(1,43,0,'test-car-43'),(1,44,0,'test-car-44'),(1,45,0,'test-car-45'),(1,46,0,'test-car-46'),(1,47,0,'test-car-47'),(1,48,0,'test-car-48'),(1,49,0,'test-car-49'),(1,50,0,'test-car-50'),(1,51,0,'test-car-51'),(1,52,0,'test-car-52'),(1,53,0,'test-car-53'),(1,54,0,'test-car-54'),(1,55,0,'test-car-55'),(1,56,0,'test-car-56'),(1,57,0,'test-car-57'),(1,58,0,'test-car-58'),(1,59,0,'test-car-59'),(1,60,0,'test-car-60'),(1,61,0,'test-car-61'),(1,62,0,'test-car-62'),(1,63,0,'test-car-63'),(1,64,0,'test-car-64'),(1,65,0,'test-car-65'),(1,66,0,'test-car-66'),(1,67,0,'test-car-67'),(1,68,0,'test-car-68'),(1,69,0,'test-car-69'),(1,70,0,'test-car-70'),(1,71,0,'test-car-71'),(1,72,0,'test-car-72'),(1,73,0,'test-car-73'),(1,74,0,'test-car-74'),(1,75,0,'test-car-75'),(1,76,0,'test-car-76'),(1,77,0,'test-car-77'),(1,78,0,'test-car-78'),(1,79,0,'test-car-79'),(1,80,0,'test-car-80'),(1,81,0,'test-car-81'),(1,82,0,'test-car-82'),(1,83,0,'test-car-83'),(1,84,0,'test-car-84'),(1,85,0,'test-car-85'),(1,86,0,'test-car-86'),(1,87,0,'test-car-87'),(1,88,0,'test-car-88'),(1,89,0,'test-car-89'),(1,90,0,'test-car-90'),(1,91,0,'test-car-91'),(1,92,0,'test-car-92'),(1,93,0,'test-car-93'),(1,94,0,'test-car-94'),(1,95,0,'test-car-95'),(1,96,0,'test-car-96'),(1,97,0,'test-car-97'),(1,98,0,'test-car-98'),(1,99,0,'test-car-99'),(1,100,0,'test-car-100'),(1,101,0,'test-car-101'),(1,102,0,'test-car-102'),(1,103,0,'test-car-103'),(1,104,0,'test-car-104'),(1,105,0,'test-car-105'),(1,106,0,'test-car-106'),(1,107,0,'test-car-107'),(1,108,0,'test-car-108'),(1,109,0,'test-car-109'),(1,110,0,'test-car-110'),(1,111,0,'test-car-111'),(1,112,0,'test-car-112'),(1,113,0,'test-car-113'),(1,114,0,'test-car-114'),(1,115,0,'test-car-115'),(1,116,0,'test-car-116'),(1,117,0,'test-car-117'),(1,118,0,'test-car-118'),(1,119,0,'test-car-119'),(1,120,0,'test-car-120'),(1,121,0,'test-car-121'),(1,122,0,'test-car-122'),(1,123,0,'test-car-123'),(1,124,0,'test-car-124'),(1,125,0,'test-car-125'),(1,126,0,'test-car-126'),(1,127,0,'test-car-127'),(1,128,0,'test-car-128'),(1,129,0,'test-car-129'),(1,130,0,'test-car-130'),(1,131,0,'test-car-131'),(1,132,0,'test-car-132'),(1,133,0,'test-car-133'),(1,134,0,'test-car-134'),(1,135,0,'test-car-135'),(1,136,0,'test-car-136'),(1,137,0,'test-car-137'),(1,138,0,'test-car-138'),(1,139,0,'test-car-139'),(1,140,0,'test-car-140'),(1,141,0,'test-car-141'),(1,142,0,'test-car-142'),(1,143,0,'test-car-143'),(1,144,0,'test-car-144'),(1,145,0,'test-car-145'),(1,146,0,'test-car-146'),(1,147,0,'test-car-147'),(1,148,0,'test-car-148'),(1,149,0,'test-car-149'),(1,150,0,'test-car-150'),(1,151,0,'test-car-151'),(1,152,0,'test-car-152'),(1,153,0,'test-car-153'),(1,154,0,'test-car-154'),(1,155,0,'test-car-155'),(1,156,0,'test-car-156'),(1,157,0,'test-car-157'),(1,158,0,'test-car-158'),(1,159,0,'test-car-159'),(1,160,0,'test-car-160'),(1,161,0,'test-car-161'),(1,162,0,'test-car-162'),(1,163,0,'test-car-163'),(1,164,0,'test-car-164'),(1,165,0,'test-car-165'),(1,166,0,'test-car-166'),(1,167,0,'test-car-167'),(1,168,0,'test-car-168'),(1,169,0,'test-car-169'),(1,170,0,'test-car-170'),(1,171,0,'test-car-171'),(1,172,0,'test-car-172'),(1,173,0,'test-car-173'),(1,174,0,'test-car-174'),(1,175,0,'test-car-175'),(1,176,0,'test-car-176'),(1,177,0,'test-car-177'),(1,178,0,'test-car-178'),(1,179,0,'test-car-179'),(1,180,0,'test-car-180'),(1,181,0,'test-car-181'),(1,182,0,'test-car-182'),(1,183,0,'test-car-183'),(1,184,0,'test-car-184'),(1,185,0,'test-car-185'),(1,186,0,'test-car-186'),(1,187,0,'test-car-187'),(1,188,0,'test-car-188'),(1,189,0,'test-car-189'),(1,190,0,'test-car-190'),(1,191,0,'test-car-191'),(1,192,0,'test-car-192'),(1,193,0,'test-car-193'),(1,194,0,'test-car-194'),(1,195,0,'test-car-195'),(1,196,0,'test-car-196'),(1,197,0,'test-car-197'),(1,198,0,'test-car-198'),(1,199,0,'test-car-199'),(1,200,0,'test-car-200'),(1,201,0,'test-car-201'),(1,202,0,'test-car-202'),(1,203,0,'test-car-203');
/*!40000 ALTER TABLE `brand_item` ENABLE KEYS */;

--
-- Table structure for table `brands_pictures_cache`
--

DROP TABLE IF EXISTS `brands_pictures_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands_pictures_cache` (
  `brand_id` int(10) unsigned NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`brand_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `brands_pictures_cache_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `brands_pictures_cache_ibfk_2` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands_pictures_cache`
--

/*!40000 ALTER TABLE `brands_pictures_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `brands_pictures_cache` ENABLE KEYS */;

--
-- Table structure for table `car_language`
--

DROP TABLE IF EXISTS `car_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_language` (
  `car_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`car_id`,`language`),
  CONSTRAINT `car_language_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_language`
--

/*!40000 ALTER TABLE `car_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_language` ENABLE KEYS */;

--
-- Table structure for table `car_parent`
--

DROP TABLE IF EXISTS `car_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_parent` (
  `car_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `catname` varchar(50) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `manual_catname` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`car_id`,`parent_id`),
  UNIQUE KEY `unique_catname` (`parent_id`,`catname`),
  CONSTRAINT `car_parent_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `car_parent_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_parent`
--

/*!40000 ALTER TABLE `car_parent` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_parent` ENABLE KEYS */;

--
-- Table structure for table `item_parent_cache`
--

DROP TABLE IF EXISTS `item_parent_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_parent_cache` (
  `item_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `diff` int(11) NOT NULL DEFAULT '0',
  `tuning` tinyint(4) NOT NULL DEFAULT '0',
  `sport` tinyint(4) NOT NULL DEFAULT '0',
  `design` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `item_parent_cache_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_parent_cache_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_parent_cache`
--

/*!40000 ALTER TABLE `item_parent_cache` DISABLE KEYS */;
INSERT INTO `item_parent_cache` VALUES (1,1,0,0,0,0),(2,2,0,0,0,0),(3,3,0,0,0,0),(4,4,0,0,0,0),(5,5,0,0,0,0),(6,6,0,0,0,0),(7,7,0,0,0,0),(8,8,0,0,0,0),(9,9,0,0,0,0),(10,10,0,0,0,0),(11,11,0,0,0,0),(12,12,0,0,0,0),(13,13,0,0,0,0),(14,14,0,0,0,0),(15,15,0,0,0,0),(16,16,0,0,0,0),(17,17,0,0,0,0),(18,18,0,0,0,0),(19,19,0,0,0,0),(20,20,0,0,0,0),(21,21,0,0,0,0),(22,22,0,0,0,0),(23,23,0,0,0,0),(24,24,0,0,0,0),(25,25,0,0,0,0),(26,26,0,0,0,0),(27,27,0,0,0,0),(28,28,0,0,0,0),(29,29,0,0,0,0),(30,30,0,0,0,0),(31,31,0,0,0,0),(32,32,0,0,0,0),(33,33,0,0,0,0),(34,34,0,0,0,0),(35,35,0,0,0,0),(36,36,0,0,0,0),(37,37,0,0,0,0),(38,38,0,0,0,0),(39,39,0,0,0,0),(40,40,0,0,0,0),(41,41,0,0,0,0),(42,42,0,0,0,0),(43,43,0,0,0,0),(44,44,0,0,0,0),(45,45,0,0,0,0),(46,46,0,0,0,0),(47,47,0,0,0,0),(48,48,0,0,0,0),(49,49,0,0,0,0),(50,50,0,0,0,0),(51,51,0,0,0,0),(52,52,0,0,0,0),(53,53,0,0,0,0),(54,54,0,0,0,0),(55,55,0,0,0,0),(56,56,0,0,0,0),(57,57,0,0,0,0),(58,58,0,0,0,0),(59,59,0,0,0,0),(60,60,0,0,0,0),(61,61,0,0,0,0),(62,62,0,0,0,0),(63,63,0,0,0,0),(64,64,0,0,0,0),(65,65,0,0,0,0),(66,66,0,0,0,0),(67,67,0,0,0,0),(68,68,0,0,0,0),(69,69,0,0,0,0),(70,70,0,0,0,0),(71,71,0,0,0,0),(72,72,0,0,0,0),(73,73,0,0,0,0),(74,74,0,0,0,0),(75,75,0,0,0,0),(76,76,0,0,0,0),(77,77,0,0,0,0),(78,78,0,0,0,0),(79,79,0,0,0,0),(80,80,0,0,0,0),(81,81,0,0,0,0),(82,82,0,0,0,0),(83,83,0,0,0,0),(84,84,0,0,0,0),(85,85,0,0,0,0),(86,86,0,0,0,0),(87,87,0,0,0,0),(88,88,0,0,0,0),(89,89,0,0,0,0),(90,90,0,0,0,0),(91,91,0,0,0,0),(92,92,0,0,0,0),(93,93,0,0,0,0),(94,94,0,0,0,0),(95,95,0,0,0,0),(96,96,0,0,0,0),(97,97,0,0,0,0),(98,98,0,0,0,0),(99,99,0,0,0,0),(100,100,0,0,0,0),(101,101,0,0,0,0),(102,102,0,0,0,0),(103,103,0,0,0,0),(104,104,0,0,0,0),(105,105,0,0,0,0),(106,106,0,0,0,0),(107,107,0,0,0,0),(108,108,0,0,0,0),(109,109,0,0,0,0),(110,110,0,0,0,0),(111,111,0,0,0,0),(112,112,0,0,0,0),(113,113,0,0,0,0),(114,114,0,0,0,0),(115,115,0,0,0,0),(116,116,0,0,0,0),(117,117,0,0,0,0),(118,118,0,0,0,0),(119,119,0,0,0,0),(120,120,0,0,0,0),(121,121,0,0,0,0),(122,122,0,0,0,0),(123,123,0,0,0,0),(124,124,0,0,0,0),(125,125,0,0,0,0),(126,126,0,0,0,0),(127,127,0,0,0,0),(128,128,0,0,0,0),(129,129,0,0,0,0),(130,130,0,0,0,0),(131,131,0,0,0,0),(132,132,0,0,0,0),(133,133,0,0,0,0),(134,134,0,0,0,0),(135,135,0,0,0,0),(136,136,0,0,0,0),(137,137,0,0,0,0),(138,138,0,0,0,0),(139,139,0,0,0,0),(140,140,0,0,0,0),(141,141,0,0,0,0),(142,142,0,0,0,0),(143,143,0,0,0,0),(144,144,0,0,0,0),(145,145,0,0,0,0),(146,146,0,0,0,0),(147,147,0,0,0,0),(148,148,0,0,0,0),(149,149,0,0,0,0),(150,150,0,0,0,0),(151,151,0,0,0,0),(152,152,0,0,0,0),(153,153,0,0,0,0),(154,154,0,0,0,0),(155,155,0,0,0,0),(156,156,0,0,0,0),(157,157,0,0,0,0),(158,158,0,0,0,0),(159,159,0,0,0,0),(160,160,0,0,0,0),(161,161,0,0,0,0),(162,162,0,0,0,0),(163,163,0,0,0,0),(164,164,0,0,0,0),(165,165,0,0,0,0),(166,166,0,0,0,0),(167,167,0,0,0,0),(168,168,0,0,0,0),(169,169,0,0,0,0),(170,170,0,0,0,0),(171,171,0,0,0,0),(172,172,0,0,0,0),(173,173,0,0,0,0),(174,174,0,0,0,0),(175,175,0,0,0,0),(176,176,0,0,0,0),(177,177,0,0,0,0),(178,178,0,0,0,0),(179,179,0,0,0,0),(180,180,0,0,0,0),(181,181,0,0,0,0),(182,182,0,0,0,0),(183,183,0,0,0,0),(184,184,0,0,0,0),(185,185,0,0,0,0),(186,186,0,0,0,0),(187,187,0,0,0,0),(188,188,0,0,0,0),(189,189,0,0,0,0),(190,190,0,0,0,0),(191,191,0,0,0,0),(192,192,0,0,0,0),(193,193,0,0,0,0),(194,194,0,0,0,0),(195,195,0,0,0,0),(196,196,0,0,0,0),(197,197,0,0,0,0),(198,198,0,0,0,0),(199,199,0,0,0,0),(200,200,0,0,0,0),(201,201,0,0,0,0),(202,202,0,0,0,0),(203,203,0,0,0,0);
/*!40000 ALTER TABLE `item_parent_cache` ENABLE KEYS */;

--
-- Table structure for table `car_type_language`
--

DROP TABLE IF EXISTS `car_type_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_type_language` (
  `car_type_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `name_rp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`car_type_id`,`language`),
  CONSTRAINT `FK_car_type_language_car_types_id` FOREIGN KEY (`car_type_id`) REFERENCES `car_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_type_language`
--

/*!40000 ALTER TABLE `car_type_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_type_language` ENABLE KEYS */;

--
-- Table structure for table `car_types`
--

DROP TABLE IF EXISTS `car_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `catname` varchar(20) NOT NULL,
  `name` varchar(35) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `name_rp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catname` (`catname`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_types`
--

/*!40000 ALTER TABLE `car_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_types` ENABLE KEYS */;

--
-- Table structure for table `car_types_parents`
--

DROP TABLE IF EXISTS `car_types_parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_types_parents` (
  `id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `car_types_parents_ibfk_1` FOREIGN KEY (`id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `car_types_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `car_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_types_parents`
--

/*!40000 ALTER TABLE `car_types_parents` DISABLE KEYS */;
/*!40000 ALTER TABLE `car_types_parents` ENABLE KEYS */;

--
-- Table structure for table `cars`
--

DROP TABLE IF EXISTS `cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cars` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `begin_year` smallint(5) unsigned DEFAULT NULL,
  `end_year` smallint(5) unsigned DEFAULT NULL,
  `body` varchar(15) NOT NULL,
  `spec_id` int(10) unsigned DEFAULT NULL,
  `spec_inherit` tinyint(1) NOT NULL DEFAULT '1',
  `produced` int(10) unsigned DEFAULT NULL,
  `produced_exactly` tinyint(3) unsigned NOT NULL,
  `is_concept` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `pictures_count` int(10) unsigned NOT NULL DEFAULT '0',
  `today` tinyint(3) unsigned DEFAULT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL COMMENT 'Р”Р°С‚Р° СЃРѕР·РґР°РЅРёСЏ Р·Р°РїРёСЃРё',
  `begin_month` tinyint(3) unsigned DEFAULT NULL,
  `end_month` tinyint(3) unsigned DEFAULT NULL,
  `begin_order_cache` date DEFAULT NULL,
  `end_order_cache` date DEFAULT NULL,
  `begin_model_year` smallint(5) unsigned DEFAULT NULL,
  `end_model_year` smallint(5) DEFAULT NULL,
  `_html` text,
  `is_group` tinyint(4) NOT NULL DEFAULT '0',
  `car_type_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `is_concept_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `engine_id` int(10) unsigned DEFAULT NULL,
  `engine_inherit` tinyint(4) NOT NULL DEFAULT '1',
  `text_id` int(11) DEFAULT NULL,
  `full_text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`begin_year`,`body`,`end_year`,`begin_model_year`,`end_model_year`,`is_group`),
  KEY `fullCaptionOrder` (`name`,`body`,`begin_year`,`end_year`),
  KEY `primary_and_sorting` (`id`,`begin_order_cache`),
  KEY `engine_id` (`engine_id`),
  KEY `spec_id` (`spec_id`),
  KEY `text_id` (`text_id`),
  KEY `full_text_id` (`full_text_id`),
  CONSTRAINT `cars_ibfk_2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`),
  CONSTRAINT `cars_ibfk_3` FOREIGN KEY (`spec_id`) REFERENCES `spec` (`id`),
  CONSTRAINT `cars_ibfk_4` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  CONSTRAINT `cars_ibfk_5` FOREIGN KEY (`full_text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=99781 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=152;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cars`
--

/*!40000 ALTER TABLE `cars` DISABLE KEYS */;
INSERT INTO `cars` VALUES (1,'test car',1999,NULL,'',NULL,1,100,1,0,0,1,'2016-11-25 18:31:47',2,NULL,NULL,NULL,2000,NULL,NULL,0,0,0,1,1,NULL,NULL),(2,'test concept car',1999,2005,'',NULL,1,233,0,1,0,0,'2016-11-25 18:31:47',6,4,NULL,NULL,1999,2005,NULL,0,0,0,NULL,1,NULL,NULL),(3,'test car 3',1923,1927,'',NULL,1,50752,1,0,0,0,'2016-11-25 18:31:47',6,1,NULL,NULL,1923,1927,NULL,0,0,0,NULL,1,NULL,NULL),(4,'test car 4',1931,1937,'',NULL,1,51365,0,0,0,1,'2016-11-25 18:31:47',6,5,NULL,NULL,1932,1937,NULL,0,0,0,NULL,1,NULL,NULL),(5,'test car 5',1984,1984,'',NULL,1,79927,0,0,0,1,'2016-11-25 18:31:47',7,4,NULL,NULL,1985,1984,NULL,0,0,0,NULL,1,NULL,NULL),(6,'test car 6',1908,1908,'',NULL,1,73949,0,0,0,0,'2016-11-25 18:31:47',4,9,NULL,NULL,1908,1908,NULL,0,0,0,NULL,1,NULL,NULL),(7,'test car 7',1997,1997,'',NULL,1,73664,0,0,0,1,'2016-11-25 18:31:47',1,12,NULL,NULL,1997,1997,NULL,0,0,0,NULL,1,NULL,NULL),(8,'test car 8',1915,1920,'',NULL,1,90611,0,0,0,1,'2016-11-25 18:31:47',2,5,NULL,NULL,1916,1920,NULL,0,0,0,NULL,1,NULL,NULL),(9,'test car 9',2007,2017,'',NULL,1,66221,1,0,0,1,'2016-11-25 18:31:47',5,12,NULL,NULL,2008,2017,NULL,0,0,0,NULL,1,NULL,NULL),(10,'test car 10',1914,1919,'',NULL,1,39531,1,0,0,1,'2016-11-25 18:31:47',5,12,NULL,NULL,1915,1919,NULL,0,0,0,NULL,1,NULL,NULL),(11,'test car 11',1941,1946,'',NULL,1,2689,1,0,0,1,'2016-11-25 18:31:47',6,1,NULL,NULL,1942,1946,NULL,0,0,0,NULL,1,NULL,NULL),(12,'test car 12',1995,2005,'',NULL,1,3852,0,0,0,1,'2016-11-25 18:31:47',2,5,NULL,NULL,1996,2005,NULL,0,0,0,NULL,1,NULL,NULL),(13,'test car 13',1946,1947,'',NULL,1,43108,1,0,0,0,'2016-11-25 18:31:47',7,7,NULL,NULL,1946,1947,NULL,0,0,0,NULL,1,NULL,NULL),(14,'test car 14',1998,2006,'',NULL,1,50167,1,0,0,1,'2016-11-25 18:31:47',10,7,NULL,NULL,1998,2006,NULL,0,0,0,NULL,1,NULL,NULL),(15,'test car 15',1963,1966,'',NULL,1,83109,1,0,0,1,'2016-11-25 18:31:47',2,7,NULL,NULL,1963,1966,NULL,0,0,0,NULL,1,NULL,NULL),(16,'test car 16',1973,1975,'',NULL,1,629,0,0,0,1,'2016-11-25 18:31:47',11,8,NULL,NULL,1973,1975,NULL,0,0,0,NULL,1,NULL,NULL),(17,'test car 17',2001,2010,'',NULL,1,14989,1,0,0,0,'2016-11-25 18:31:47',7,3,NULL,NULL,2001,2010,NULL,0,0,0,NULL,1,NULL,NULL),(18,'test car 18',2011,2018,'',NULL,1,91731,0,0,0,1,'2016-11-25 18:31:47',4,8,NULL,NULL,2011,2018,NULL,0,0,0,NULL,1,NULL,NULL),(19,'test car 19',1931,1937,'',NULL,1,99928,1,0,0,0,'2016-11-25 18:31:47',1,11,NULL,NULL,1931,1937,NULL,0,0,0,NULL,1,NULL,NULL),(20,'test car 20',1951,1960,'',NULL,1,10416,1,0,0,0,'2016-11-25 18:31:47',9,11,NULL,NULL,1951,1960,NULL,0,0,0,NULL,1,NULL,NULL),(21,'test car 21',1908,1908,'',NULL,1,48926,0,0,0,0,'2016-11-25 18:31:47',10,5,NULL,NULL,1908,1908,NULL,0,0,0,NULL,1,NULL,NULL),(22,'test car 22',1965,1973,'',NULL,1,89032,0,0,0,1,'2016-11-25 18:31:47',7,4,NULL,NULL,1965,1973,NULL,0,0,0,NULL,1,NULL,NULL),(23,'test car 23',1970,1973,'',NULL,1,81666,0,0,0,0,'2016-11-25 18:31:47',5,1,NULL,NULL,1970,1973,NULL,0,0,0,NULL,1,NULL,NULL),(24,'test car 24',1955,1964,'',NULL,1,86179,0,0,0,0,'2016-11-25 18:31:47',9,12,NULL,NULL,1955,1964,NULL,0,0,0,NULL,1,NULL,NULL),(25,'test car 25',1970,1974,'',NULL,1,69277,1,0,0,1,'2016-11-25 18:31:47',1,12,NULL,NULL,1970,1974,NULL,0,0,0,NULL,1,NULL,NULL),(26,'test car 26',1931,1934,'',NULL,1,34539,1,0,0,1,'2016-11-25 18:31:47',11,11,NULL,NULL,1931,1934,NULL,0,0,0,NULL,1,NULL,NULL),(27,'test car 27',2005,2014,'',NULL,1,86922,1,0,0,1,'2016-11-25 18:31:47',7,11,NULL,NULL,2005,2014,NULL,0,0,0,NULL,1,NULL,NULL),(28,'test car 28',1948,1953,'',NULL,1,79648,0,0,0,1,'2016-11-25 18:31:47',10,8,NULL,NULL,1949,1953,NULL,0,0,0,NULL,1,NULL,NULL),(29,'test car 29',1985,1985,'',NULL,1,5579,0,0,0,0,'2016-11-25 18:31:47',4,10,NULL,NULL,1986,1985,NULL,0,0,0,NULL,1,NULL,NULL),(30,'test car 30',1906,1916,'',NULL,1,90636,0,0,0,1,'2016-11-25 18:31:47',12,3,NULL,NULL,1907,1916,NULL,0,0,0,NULL,1,NULL,NULL),(31,'test car 31',1985,1988,'',NULL,1,30356,1,0,0,1,'2016-11-25 18:31:47',6,9,NULL,NULL,1986,1988,NULL,0,0,0,NULL,1,NULL,NULL),(32,'test car 32',2010,2010,'',NULL,1,48412,1,0,0,1,'2016-11-25 18:31:47',2,11,NULL,NULL,2011,2010,NULL,0,0,0,NULL,1,NULL,NULL),(33,'test car 33',2003,2008,'',NULL,1,1648,1,0,0,0,'2016-11-25 18:31:47',1,10,NULL,NULL,2004,2008,NULL,0,0,0,NULL,1,NULL,NULL),(34,'test car 34',1943,1951,'',NULL,1,61029,1,0,0,0,'2016-11-25 18:31:47',12,4,NULL,NULL,1944,1951,NULL,0,0,0,NULL,1,NULL,NULL),(35,'test car 35',2005,2011,'',NULL,1,39854,0,0,0,0,'2016-11-25 18:31:47',8,8,NULL,NULL,2006,2011,NULL,0,0,0,NULL,1,NULL,NULL),(36,'test car 36',2015,2018,'',NULL,1,14150,1,0,0,1,'2016-11-25 18:31:47',10,12,NULL,NULL,2016,2018,NULL,0,0,0,NULL,1,NULL,NULL),(37,'test car 37',1972,1977,'',NULL,1,74150,0,0,0,1,'2016-11-25 18:31:47',5,8,NULL,NULL,1973,1977,NULL,0,0,0,NULL,1,NULL,NULL),(38,'test car 38',1940,1947,'',NULL,1,345,0,0,0,1,'2016-11-25 18:31:47',12,8,NULL,NULL,1940,1947,NULL,0,0,0,NULL,1,NULL,NULL),(39,'test car 39',1999,2009,'',NULL,1,39394,0,0,0,1,'2016-11-25 18:31:47',6,4,NULL,NULL,1999,2009,NULL,0,0,0,NULL,1,NULL,NULL),(40,'test car 40',1994,1994,'',NULL,1,9381,1,0,0,0,'2016-11-25 18:31:47',5,11,NULL,NULL,1995,1994,NULL,0,0,0,NULL,1,NULL,NULL),(41,'test car 41',1966,1970,'',NULL,1,19488,0,0,0,0,'2016-11-25 18:31:47',6,5,NULL,NULL,1966,1970,NULL,0,0,0,NULL,1,NULL,NULL),(42,'test car 42',1916,1916,'',NULL,1,12317,0,0,0,1,'2016-11-25 18:31:47',4,12,NULL,NULL,1916,1916,NULL,0,0,0,NULL,1,NULL,NULL),(43,'test car 43',1931,1941,'',NULL,1,33576,1,0,0,0,'2016-11-25 18:31:47',1,8,NULL,NULL,1931,1941,NULL,0,0,0,NULL,1,NULL,NULL),(44,'test car 44',2013,2022,'',NULL,1,67233,1,0,0,1,'2016-11-25 18:31:47',7,9,NULL,NULL,2013,2022,NULL,0,0,0,NULL,1,NULL,NULL),(45,'test car 45',2015,2023,'',NULL,1,31369,1,0,0,0,'2016-11-25 18:31:47',6,8,NULL,NULL,2015,2023,NULL,0,0,0,NULL,1,NULL,NULL),(46,'test car 46',1966,1972,'',NULL,1,48828,0,0,0,0,'2016-11-25 18:31:47',2,12,NULL,NULL,1967,1972,NULL,0,0,0,NULL,1,NULL,NULL),(47,'test car 47',1910,1915,'',NULL,1,12491,0,0,0,0,'2016-11-25 18:31:47',6,10,NULL,NULL,1910,1915,NULL,0,0,0,NULL,1,NULL,NULL),(48,'test car 48',1933,1934,'',NULL,1,80426,0,0,0,0,'2016-11-25 18:31:47',7,9,NULL,NULL,1933,1934,NULL,0,0,0,NULL,1,NULL,NULL),(49,'test car 49',1992,1996,'',NULL,1,81731,0,0,0,0,'2016-11-25 18:31:47',5,8,NULL,NULL,1992,1996,NULL,0,0,0,NULL,1,NULL,NULL),(50,'test car 50',1962,1971,'',NULL,1,69462,0,0,0,1,'2016-11-25 18:31:47',4,3,NULL,NULL,1963,1971,NULL,0,0,0,NULL,1,NULL,NULL),(51,'test car 51',1944,1954,'',NULL,1,98610,0,0,0,1,'2016-11-25 18:31:47',12,3,NULL,NULL,1945,1954,NULL,0,0,0,NULL,1,NULL,NULL),(52,'test car 52',2008,2016,'',NULL,1,97334,1,0,0,1,'2016-11-25 18:31:47',9,12,NULL,NULL,2009,2016,NULL,0,0,0,NULL,1,NULL,NULL),(53,'test car 53',2011,2016,'',NULL,1,71675,1,0,0,0,'2016-11-25 18:31:47',10,10,NULL,NULL,2012,2016,NULL,0,0,0,NULL,1,NULL,NULL),(54,'test car 54',1993,1994,'',NULL,1,62097,0,0,0,1,'2016-11-25 18:31:47',9,3,NULL,NULL,1993,1994,NULL,0,0,0,NULL,1,NULL,NULL),(55,'test car 55',1904,1910,'',NULL,1,7350,0,0,0,0,'2016-11-25 18:31:47',3,3,NULL,NULL,1904,1910,NULL,0,0,0,NULL,1,NULL,NULL),(56,'test car 56',1993,1993,'',NULL,1,36391,1,0,0,0,'2016-11-25 18:31:47',11,4,NULL,NULL,1993,1993,NULL,0,0,0,NULL,1,NULL,NULL),(57,'test car 57',2002,2003,'',NULL,1,85544,1,0,0,1,'2016-11-25 18:31:47',11,1,NULL,NULL,2002,2003,NULL,0,0,0,NULL,1,NULL,NULL),(58,'test car 58',1988,1997,'',NULL,1,38016,1,0,0,1,'2016-11-25 18:31:47',5,12,NULL,NULL,1989,1997,NULL,0,0,0,NULL,1,NULL,NULL),(59,'test car 59',1976,1978,'',NULL,1,97259,1,0,0,1,'2016-11-25 18:31:47',8,3,NULL,NULL,1976,1978,NULL,0,0,0,NULL,1,NULL,NULL),(60,'test car 60',2007,2016,'',NULL,1,77916,1,0,0,0,'2016-11-25 18:31:47',3,11,NULL,NULL,2007,2016,NULL,0,0,0,NULL,1,NULL,NULL),(61,'test car 61',1901,1907,'',NULL,1,48024,1,0,0,0,'2016-11-25 18:31:47',12,8,NULL,NULL,1901,1907,NULL,0,0,0,NULL,1,NULL,NULL),(62,'test car 62',1921,1922,'',NULL,1,76140,1,0,0,1,'2016-11-25 18:31:47',10,10,NULL,NULL,1921,1922,NULL,0,0,0,NULL,1,NULL,NULL),(63,'test car 63',1930,1937,'',NULL,1,17742,1,0,0,1,'2016-11-25 18:31:47',11,3,NULL,NULL,1930,1937,NULL,0,0,0,NULL,1,NULL,NULL),(64,'test car 64',1934,1936,'',NULL,1,96362,0,0,0,0,'2016-11-25 18:31:47',10,11,NULL,NULL,1935,1936,NULL,0,0,0,NULL,1,NULL,NULL),(65,'test car 65',1905,1906,'',NULL,1,64221,1,0,0,0,'2016-11-25 18:31:47',12,1,NULL,NULL,1905,1906,NULL,0,0,0,NULL,1,NULL,NULL),(66,'test car 66',2011,2020,'',NULL,1,76409,1,0,0,0,'2016-11-25 18:31:47',3,6,NULL,NULL,2012,2020,NULL,0,0,0,NULL,1,NULL,NULL),(67,'test car 67',1910,1910,'',NULL,1,34090,0,0,0,1,'2016-11-25 18:31:47',4,5,NULL,NULL,1911,1910,NULL,0,0,0,NULL,1,NULL,NULL),(68,'test car 68',1926,1930,'',NULL,1,2520,0,0,0,1,'2016-11-25 18:31:47',11,9,NULL,NULL,1927,1930,NULL,0,0,0,NULL,1,NULL,NULL),(69,'test car 69',1953,1963,'',NULL,1,27948,1,0,0,0,'2016-11-25 18:31:47',2,2,NULL,NULL,1954,1963,NULL,0,0,0,NULL,1,NULL,NULL),(70,'test car 70',1979,1983,'',NULL,1,81677,0,0,0,0,'2016-11-25 18:31:47',12,10,NULL,NULL,1980,1983,NULL,0,0,0,NULL,1,NULL,NULL),(71,'test car 71',1981,1981,'',NULL,1,78568,0,0,0,0,'2016-11-25 18:31:47',2,1,NULL,NULL,1981,1981,NULL,0,0,0,NULL,1,NULL,NULL),(72,'test car 72',1991,1993,'',NULL,1,67998,0,0,0,0,'2016-11-25 18:31:47',8,3,NULL,NULL,1991,1993,NULL,0,0,0,NULL,1,NULL,NULL),(73,'test car 73',1930,1934,'',NULL,1,16943,0,0,0,1,'2016-11-25 18:31:47',10,3,NULL,NULL,1930,1934,NULL,0,0,0,NULL,1,NULL,NULL),(74,'test car 74',1994,2003,'',NULL,1,27821,0,0,0,0,'2016-11-25 18:31:47',10,6,NULL,NULL,1994,2003,NULL,0,0,0,NULL,1,NULL,NULL),(75,'test car 75',1923,1923,'',NULL,1,5835,1,0,0,0,'2016-11-25 18:31:47',9,11,NULL,NULL,1923,1923,NULL,0,0,0,NULL,1,NULL,NULL),(76,'test car 76',1988,1991,'',NULL,1,49514,1,0,0,1,'2016-11-25 18:31:47',10,5,NULL,NULL,1989,1991,NULL,0,0,0,NULL,1,NULL,NULL),(77,'test car 77',1988,1991,'',NULL,1,65243,1,0,0,0,'2016-11-25 18:31:47',7,8,NULL,NULL,1989,1991,NULL,0,0,0,NULL,1,NULL,NULL),(78,'test car 78',1943,1952,'',NULL,1,36858,1,0,0,0,'2016-11-25 18:31:47',1,7,NULL,NULL,1944,1952,NULL,0,0,0,NULL,1,NULL,NULL),(79,'test car 79',1966,1967,'',NULL,1,27707,1,0,0,0,'2016-11-25 18:31:47',7,5,NULL,NULL,1967,1967,NULL,0,0,0,NULL,1,NULL,NULL),(80,'test car 80',1984,1984,'',NULL,1,90039,0,0,0,0,'2016-11-25 18:31:47',10,9,NULL,NULL,1984,1984,NULL,0,0,0,NULL,1,NULL,NULL),(81,'test car 81',1946,1948,'',NULL,1,32911,1,0,0,1,'2016-11-25 18:31:47',4,6,NULL,NULL,1946,1948,NULL,0,0,0,NULL,1,NULL,NULL),(82,'test car 82',1942,1951,'',NULL,1,83884,0,0,0,1,'2016-11-25 18:31:47',9,11,NULL,NULL,1942,1951,NULL,0,0,0,NULL,1,NULL,NULL),(83,'test car 83',1968,1974,'',NULL,1,51898,0,0,0,0,'2016-11-25 18:31:47',1,7,NULL,NULL,1969,1974,NULL,0,0,0,NULL,1,NULL,NULL),(84,'test car 84',1996,2006,'',NULL,1,56033,1,0,0,1,'2016-11-25 18:31:47',10,5,NULL,NULL,1996,2006,NULL,0,0,0,NULL,1,NULL,NULL),(85,'test car 85',1970,1980,'',NULL,1,78847,0,0,0,0,'2016-11-25 18:31:47',1,4,NULL,NULL,1971,1980,NULL,0,0,0,NULL,1,NULL,NULL),(86,'test car 86',1928,1932,'',NULL,1,88307,1,0,0,1,'2016-11-25 18:31:47',6,6,NULL,NULL,1928,1932,NULL,0,0,0,NULL,1,NULL,NULL),(87,'test car 87',1904,1911,'',NULL,1,96877,0,0,0,0,'2016-11-25 18:31:47',3,7,NULL,NULL,1905,1911,NULL,0,0,0,NULL,1,NULL,NULL),(88,'test car 88',1995,2001,'',NULL,1,41250,0,0,0,0,'2016-11-25 18:31:47',4,6,NULL,NULL,1995,2001,NULL,0,0,0,NULL,1,NULL,NULL),(89,'test car 89',1960,1967,'',NULL,1,35858,0,0,0,1,'2016-11-25 18:31:47',9,8,NULL,NULL,1960,1967,NULL,0,0,0,NULL,1,NULL,NULL),(90,'test car 90',1954,1958,'',NULL,1,9110,1,0,0,1,'2016-11-25 18:31:47',12,7,NULL,NULL,1954,1958,NULL,0,0,0,NULL,1,NULL,NULL),(91,'test car 91',1990,2000,'',NULL,1,66048,1,0,0,1,'2016-11-25 18:31:47',3,2,NULL,NULL,1991,2000,NULL,0,0,0,NULL,1,NULL,NULL),(92,'test car 92',1907,1909,'',NULL,1,77032,1,0,0,1,'2016-11-25 18:31:47',4,6,NULL,NULL,1908,1909,NULL,0,0,0,NULL,1,NULL,NULL),(93,'test car 93',1956,1958,'',NULL,1,96789,1,0,0,0,'2016-11-25 18:31:47',4,8,NULL,NULL,1957,1958,NULL,0,0,0,NULL,1,NULL,NULL),(94,'test car 94',1976,1984,'',NULL,1,83543,1,0,0,1,'2016-11-25 18:31:47',1,12,NULL,NULL,1977,1984,NULL,0,0,0,NULL,1,NULL,NULL),(95,'test car 95',1927,1936,'',NULL,1,20953,0,0,0,0,'2016-11-25 18:31:47',3,9,NULL,NULL,1927,1936,NULL,0,0,0,NULL,1,NULL,NULL),(96,'test car 96',1994,2001,'',NULL,1,43642,0,0,0,0,'2016-11-25 18:31:47',4,4,NULL,NULL,1994,2001,NULL,0,0,0,NULL,1,NULL,NULL),(97,'test car 97',1993,1994,'',NULL,1,29203,0,0,0,1,'2016-11-25 18:31:47',8,7,NULL,NULL,1993,1994,NULL,0,0,0,NULL,1,NULL,NULL),(98,'test car 98',1959,1964,'',NULL,1,85957,1,0,0,1,'2016-11-25 18:31:47',10,7,NULL,NULL,1959,1964,NULL,0,0,0,NULL,1,NULL,NULL),(99,'test car 99',1911,1918,'',NULL,1,92830,1,0,0,1,'2016-11-25 18:31:47',11,2,NULL,NULL,1912,1918,NULL,0,0,0,NULL,1,NULL,NULL),(100,'test car 100',1929,1937,'',NULL,1,32396,0,0,0,1,'2016-11-25 18:31:47',6,11,NULL,NULL,1929,1937,NULL,0,0,0,NULL,1,NULL,NULL),(101,'test car 101',1906,1915,'',NULL,1,28241,1,0,0,1,'2016-11-25 18:31:47',8,2,NULL,NULL,1906,1915,NULL,0,0,0,NULL,1,NULL,NULL),(102,'test car 102',1961,1965,'',NULL,1,32358,1,0,0,0,'2016-11-25 18:31:47',5,11,NULL,NULL,1961,1965,NULL,0,0,0,NULL,1,NULL,NULL),(103,'test car 103',1992,1998,'',NULL,1,47866,0,0,0,1,'2016-11-25 18:31:47',10,11,NULL,NULL,1992,1998,NULL,0,0,0,NULL,1,NULL,NULL),(104,'test car 104',2002,2007,'',NULL,1,98313,0,0,0,1,'2016-11-25 18:31:47',3,3,NULL,NULL,2003,2007,NULL,0,0,0,NULL,1,NULL,NULL),(105,'test car 105',2007,2012,'',NULL,1,31807,0,0,0,0,'2016-11-25 18:31:47',1,12,NULL,NULL,2008,2012,NULL,0,0,0,NULL,1,NULL,NULL),(106,'test car 106',1924,1925,'',NULL,1,39700,1,0,0,0,'2016-11-25 18:31:47',9,7,NULL,NULL,1925,1925,NULL,0,0,0,NULL,1,NULL,NULL),(107,'test car 107',1973,1976,'',NULL,1,29300,1,0,0,0,'2016-11-25 18:31:47',5,11,NULL,NULL,1974,1976,NULL,0,0,0,NULL,1,NULL,NULL),(108,'test car 108',1963,1965,'',NULL,1,33230,1,0,0,1,'2016-11-25 18:31:47',12,6,NULL,NULL,1963,1965,NULL,0,0,0,NULL,1,NULL,NULL),(109,'test car 109',2012,2017,'',NULL,1,84894,1,0,0,1,'2016-11-25 18:31:47',6,6,NULL,NULL,2013,2017,NULL,0,0,0,NULL,1,NULL,NULL),(110,'test car 110',1994,1999,'',NULL,1,4799,1,0,0,0,'2016-11-25 18:31:47',12,9,NULL,NULL,1994,1999,NULL,0,0,0,NULL,1,NULL,NULL),(111,'test car 111',2011,2017,'',NULL,1,72861,1,0,0,0,'2016-11-25 18:31:47',1,4,NULL,NULL,2011,2017,NULL,0,0,0,NULL,1,NULL,NULL),(112,'test car 112',1933,1940,'',NULL,1,29156,0,0,0,1,'2016-11-25 18:31:47',6,8,NULL,NULL,1933,1940,NULL,0,0,0,NULL,1,NULL,NULL),(113,'test car 113',1994,1995,'',NULL,1,18190,1,0,0,0,'2016-11-25 18:31:47',2,12,NULL,NULL,1995,1995,NULL,0,0,0,NULL,1,NULL,NULL),(114,'test car 114',1917,1918,'',NULL,1,12005,0,0,0,1,'2016-11-25 18:31:47',8,5,NULL,NULL,1917,1918,NULL,0,0,0,NULL,1,NULL,NULL),(115,'test car 115',1948,1952,'',NULL,1,23053,0,0,0,0,'2016-11-25 18:31:47',4,1,NULL,NULL,1949,1952,NULL,0,0,0,NULL,1,NULL,NULL),(116,'test car 116',1982,1982,'',NULL,1,7882,1,0,0,1,'2016-11-25 18:31:47',12,10,NULL,NULL,1983,1982,NULL,1,0,0,NULL,1,NULL,NULL),(117,'test car 117',2011,2013,'',NULL,1,40957,0,0,0,1,'2016-11-25 18:31:47',8,3,NULL,NULL,2012,2013,NULL,0,0,0,NULL,1,NULL,NULL),(118,'test car 118',1904,1913,'',NULL,1,56069,0,0,0,0,'2016-11-25 18:31:47',4,2,NULL,NULL,1905,1913,NULL,0,0,0,NULL,1,NULL,NULL),(119,'test car 119',1996,1999,'',NULL,1,36003,0,0,0,1,'2016-11-25 18:31:47',5,3,NULL,NULL,1996,1999,NULL,0,0,0,NULL,1,NULL,NULL),(120,'test car 120',1982,1985,'',NULL,1,6099,1,0,0,0,'2016-11-25 18:31:47',2,6,NULL,NULL,1983,1985,NULL,0,0,0,NULL,1,NULL,NULL),(121,'test car 121',1942,1943,'',NULL,1,57558,1,0,0,0,'2016-11-25 18:31:47',2,5,NULL,NULL,1942,1943,NULL,0,0,0,NULL,1,NULL,NULL),(122,'test car 122',1943,1947,'',NULL,1,92587,1,0,0,1,'2016-11-25 18:31:47',3,2,NULL,NULL,1944,1947,NULL,0,0,0,NULL,1,NULL,NULL),(123,'test car 123',1926,1932,'',NULL,1,79172,1,0,0,1,'2016-11-25 18:31:47',9,11,NULL,NULL,1927,1932,NULL,0,0,0,NULL,1,NULL,NULL),(124,'test car 124',1983,1984,'',NULL,1,812,0,0,0,1,'2016-11-25 18:31:47',12,6,NULL,NULL,1983,1984,NULL,0,0,0,NULL,1,NULL,NULL),(125,'test car 125',1970,1974,'',NULL,1,11369,1,0,0,0,'2016-11-25 18:31:47',3,1,NULL,NULL,1971,1974,NULL,0,0,0,NULL,1,NULL,NULL),(126,'test car 126',1997,2000,'',NULL,1,92630,0,0,0,1,'2016-11-25 18:31:47',1,5,NULL,NULL,1998,2000,NULL,0,0,0,NULL,1,NULL,NULL),(127,'test car 127',2004,2011,'',NULL,1,23922,1,0,0,1,'2016-11-25 18:31:47',8,11,NULL,NULL,2005,2011,NULL,0,0,0,NULL,1,NULL,NULL),(128,'test car 128',1968,1977,'',NULL,1,76249,0,0,0,1,'2016-11-25 18:31:47',6,3,NULL,NULL,1968,1977,NULL,0,0,0,NULL,1,NULL,NULL),(129,'test car 129',1979,1985,'',NULL,1,77277,0,0,0,1,'2016-11-25 18:31:47',10,4,NULL,NULL,1979,1985,NULL,0,0,0,NULL,1,NULL,NULL),(130,'test car 130',2003,2010,'',NULL,1,46842,0,0,0,1,'2016-11-25 18:31:47',12,11,NULL,NULL,2003,2010,NULL,0,0,0,NULL,1,NULL,NULL),(131,'test car 131',1949,1949,'',NULL,1,34967,1,0,0,0,'2016-11-25 18:31:47',8,3,NULL,NULL,1950,1949,NULL,0,0,0,NULL,1,NULL,NULL),(132,'test car 132',1982,1982,'',NULL,1,50011,1,0,0,1,'2016-11-25 18:31:47',10,3,NULL,NULL,1982,1982,NULL,0,0,0,NULL,1,NULL,NULL),(133,'test car 133',2004,2008,'',NULL,1,78284,0,0,0,0,'2016-11-25 18:31:47',4,9,NULL,NULL,2005,2008,NULL,0,0,0,NULL,1,NULL,NULL),(134,'test car 134',1965,1967,'',NULL,1,74228,0,0,0,0,'2016-11-25 18:31:47',5,2,NULL,NULL,1965,1967,NULL,0,0,0,NULL,1,NULL,NULL),(135,'test car 135',1902,1906,'',NULL,1,47874,1,0,0,0,'2016-11-25 18:31:47',10,5,NULL,NULL,1903,1906,NULL,0,0,0,NULL,1,NULL,NULL),(136,'test car 136',1907,1911,'',NULL,1,37818,1,0,0,1,'2016-11-25 18:31:47',11,12,NULL,NULL,1908,1911,NULL,0,0,0,NULL,1,NULL,NULL),(137,'test car 137',1917,1927,'',NULL,1,651,1,0,0,1,'2016-11-25 18:31:47',11,10,NULL,NULL,1917,1927,NULL,0,0,0,NULL,1,NULL,NULL),(138,'test car 138',1904,1904,'',NULL,1,95000,1,1,0,1,'2016-11-25 18:31:47',11,5,NULL,NULL,1905,1904,NULL,0,0,0,NULL,1,NULL,NULL),(139,'test car 139',1994,1994,'',NULL,1,35767,0,0,0,1,'2016-11-25 18:31:47',6,12,NULL,NULL,1994,1994,NULL,0,0,0,NULL,1,NULL,NULL),(140,'test car 140',1960,1965,'',NULL,1,30649,0,0,0,1,'2016-11-25 18:31:47',9,6,NULL,NULL,1961,1965,NULL,0,0,0,NULL,1,NULL,NULL),(141,'test car 141',1932,1935,'',NULL,1,99928,0,0,0,0,'2016-11-25 18:31:47',1,2,NULL,NULL,1932,1935,NULL,0,0,0,NULL,1,NULL,NULL),(142,'test car 142',1914,1922,'',NULL,1,60864,0,0,0,1,'2016-11-25 18:31:47',11,10,NULL,NULL,1914,1922,NULL,0,0,0,NULL,1,NULL,NULL),(143,'test car 143',1924,1925,'',NULL,1,31250,1,0,0,0,'2016-11-25 18:31:47',7,3,NULL,NULL,1925,1925,NULL,0,0,0,NULL,1,NULL,NULL),(144,'test car 144',1956,1962,'',NULL,1,5217,1,0,0,0,'2016-11-25 18:31:47',10,12,NULL,NULL,1957,1962,NULL,0,0,0,NULL,1,NULL,NULL),(145,'test car 145',1916,1917,'',NULL,1,56843,1,0,0,0,'2016-11-25 18:31:47',9,2,NULL,NULL,1916,1917,NULL,0,0,0,NULL,1,NULL,NULL),(146,'test car 146',1953,1961,'',NULL,1,57739,0,0,0,1,'2016-11-25 18:31:47',7,9,NULL,NULL,1953,1961,NULL,0,0,0,NULL,1,NULL,NULL),(147,'test car 147',1907,1907,'',NULL,1,52175,1,0,0,0,'2016-11-25 18:31:47',6,9,NULL,NULL,1908,1907,NULL,1,0,0,NULL,1,NULL,NULL),(148,'test car 148',1901,1908,'',NULL,1,83318,0,0,0,0,'2016-11-25 18:31:47',7,7,NULL,NULL,1902,1908,NULL,0,0,0,NULL,1,NULL,NULL),(149,'test car 149',1993,1996,'',NULL,1,68020,1,0,0,0,'2016-11-25 18:31:47',2,3,NULL,NULL,1994,1996,NULL,0,0,0,NULL,1,NULL,NULL),(150,'test car 150',1913,1921,'',NULL,1,87949,1,0,0,0,'2016-11-25 18:31:47',11,3,NULL,NULL,1914,1921,NULL,0,0,0,NULL,1,NULL,NULL),(151,'test car 151',1929,1938,'',NULL,1,38619,1,0,0,0,'2016-11-25 18:31:47',4,9,NULL,NULL,1929,1938,NULL,0,0,0,NULL,1,NULL,NULL),(152,'test car 152',1942,1944,'',NULL,1,38831,0,0,0,0,'2016-11-25 18:31:47',4,5,NULL,NULL,1942,1944,NULL,0,0,0,NULL,1,NULL,NULL),(153,'test car 153',1998,2003,'',NULL,1,99622,0,0,0,0,'2016-11-25 18:31:47',7,7,NULL,NULL,1999,2003,NULL,0,0,0,NULL,1,NULL,NULL),(154,'test car 154',1915,1917,'',NULL,1,30791,0,0,0,0,'2016-11-25 18:31:47',1,11,NULL,NULL,1916,1917,NULL,0,0,0,NULL,1,NULL,NULL),(155,'test car 155',1989,1996,'',NULL,1,37656,0,0,0,0,'2016-11-25 18:31:47',4,12,NULL,NULL,1990,1996,NULL,0,0,0,NULL,1,NULL,NULL),(156,'test car 156',1946,1948,'',NULL,1,44604,1,0,0,0,'2016-11-25 18:31:47',1,10,NULL,NULL,1946,1948,NULL,0,0,0,NULL,1,NULL,NULL),(157,'test car 157',2005,2011,'',NULL,1,69810,1,0,0,1,'2016-11-25 18:31:47',6,4,NULL,NULL,2005,2011,NULL,0,0,0,NULL,1,NULL,NULL),(158,'test car 158',1920,1925,'',NULL,1,49364,0,0,0,0,'2016-11-25 18:31:47',3,11,NULL,NULL,1921,1925,NULL,0,0,0,NULL,1,NULL,NULL),(159,'test car 159',1940,1949,'',NULL,1,89837,1,0,0,1,'2016-11-25 18:31:47',6,3,NULL,NULL,1940,1949,NULL,0,0,0,NULL,1,NULL,NULL),(160,'test car 160',1978,1986,'',NULL,1,27525,1,0,0,0,'2016-11-25 18:31:47',4,3,NULL,NULL,1979,1986,NULL,0,0,0,NULL,1,NULL,NULL),(161,'test car 161',2005,2010,'',NULL,1,51032,1,0,0,1,'2016-11-25 18:31:47',6,5,NULL,NULL,2005,2010,NULL,0,0,0,NULL,1,NULL,NULL),(162,'test car 162',1928,1937,'',NULL,1,77256,0,0,0,0,'2016-11-25 18:31:47',11,3,NULL,NULL,1929,1937,NULL,1,0,0,NULL,1,NULL,NULL),(163,'test car 163',1997,2001,'',NULL,1,24535,1,0,0,0,'2016-11-25 18:31:47',12,5,NULL,NULL,1997,2001,NULL,0,0,0,NULL,1,NULL,NULL),(164,'test car 164',1925,1926,'',NULL,1,13995,1,0,0,1,'2016-11-25 18:31:47',3,1,NULL,NULL,1926,1926,NULL,0,0,0,NULL,1,NULL,NULL),(165,'test car 165',1960,1964,'',NULL,1,64585,1,0,0,0,'2016-11-25 18:31:47',6,2,NULL,NULL,1960,1964,NULL,0,0,0,NULL,1,NULL,NULL),(166,'test car 166',1948,1953,'',NULL,1,83250,0,0,0,1,'2016-11-25 18:31:47',6,3,NULL,NULL,1948,1953,NULL,0,0,0,NULL,1,NULL,NULL),(167,'test car 167',1968,1968,'',NULL,1,63721,0,0,0,1,'2016-11-25 18:31:47',12,11,NULL,NULL,1969,1968,NULL,0,0,0,NULL,1,NULL,NULL),(168,'test car 168',1930,1931,'',NULL,1,88340,1,0,0,1,'2016-11-25 18:31:47',7,2,NULL,NULL,1931,1931,NULL,0,0,0,NULL,1,NULL,NULL),(169,'test car 169',1951,1954,'',NULL,1,73137,0,0,0,1,'2016-11-25 18:31:47',10,4,NULL,NULL,1952,1954,NULL,0,0,0,NULL,1,NULL,NULL),(170,'test car 170',1934,1937,'',NULL,1,50811,1,0,0,1,'2016-11-25 18:31:47',10,11,NULL,NULL,1935,1937,NULL,0,0,0,NULL,1,NULL,NULL),(171,'test car 171',2005,2013,'',NULL,1,64062,1,0,0,1,'2016-11-25 18:31:47',2,11,NULL,NULL,2005,2013,NULL,0,0,0,NULL,1,NULL,NULL),(172,'test car 172',1940,1940,'',NULL,1,47267,0,0,0,0,'2016-11-25 18:31:47',8,5,NULL,NULL,1941,1940,NULL,0,0,0,NULL,1,NULL,NULL),(173,'test car 173',1932,1940,'',NULL,1,37812,1,0,0,1,'2016-11-25 18:31:47',6,4,NULL,NULL,1932,1940,NULL,0,0,0,NULL,1,NULL,NULL),(174,'test car 174',1911,1914,'',NULL,1,58402,1,0,0,0,'2016-11-25 18:31:47',1,11,NULL,NULL,1912,1914,NULL,0,0,0,NULL,1,NULL,NULL),(175,'test car 175',1925,1935,'',NULL,1,63615,0,0,0,1,'2016-11-25 18:31:47',6,11,NULL,NULL,1925,1935,NULL,0,0,0,NULL,1,NULL,NULL),(176,'test car 176',1911,1921,'',NULL,1,43197,0,0,0,1,'2016-11-25 18:31:47',11,1,NULL,NULL,1912,1921,NULL,0,0,0,NULL,1,NULL,NULL),(177,'test car 177',1949,1954,'',NULL,1,78699,1,0,0,0,'2016-11-25 18:31:47',4,12,NULL,NULL,1950,1954,NULL,0,0,0,NULL,1,NULL,NULL),(178,'test car 178',1981,1981,'',NULL,1,391,0,0,0,0,'2016-11-25 18:31:47',1,10,NULL,NULL,1981,1981,NULL,0,0,0,NULL,1,NULL,NULL),(179,'test car 179',1967,1968,'',NULL,1,35593,0,0,0,1,'2016-11-25 18:31:47',9,8,NULL,NULL,1968,1968,NULL,0,0,0,NULL,1,NULL,NULL),(180,'test car 180',1915,1923,'',NULL,1,5430,1,0,0,1,'2016-11-25 18:31:47',11,2,NULL,NULL,1916,1923,NULL,0,0,0,NULL,1,NULL,NULL),(181,'test car 181',1936,1944,'',NULL,1,42998,1,0,0,1,'2016-11-25 18:31:47',3,11,NULL,NULL,1937,1944,NULL,0,0,0,NULL,1,NULL,NULL),(182,'test car 182',1928,1928,'',NULL,1,34945,1,0,0,0,'2016-11-25 18:31:47',8,4,NULL,NULL,1929,1928,NULL,0,0,0,NULL,1,NULL,NULL),(183,'test car 183',1943,1948,'',NULL,1,93051,1,0,0,0,'2016-11-25 18:31:47',3,10,NULL,NULL,1944,1948,NULL,0,0,0,NULL,1,NULL,NULL),(184,'test car 184',1951,1953,'',NULL,1,50497,0,0,0,1,'2016-11-25 18:31:47',9,8,NULL,NULL,1951,1953,NULL,0,0,0,NULL,1,NULL,NULL),(185,'test car 185',1991,1999,'',NULL,1,18952,1,0,0,1,'2016-11-25 18:31:47',11,5,NULL,NULL,1992,1999,NULL,0,0,0,NULL,1,NULL,NULL),(186,'test car 186',1926,1932,'',NULL,1,5821,1,0,0,0,'2016-11-25 18:31:47',7,11,NULL,NULL,1927,1932,NULL,0,0,0,NULL,1,NULL,NULL),(187,'test car 187',1920,1925,'',NULL,1,99237,1,1,0,1,'2016-11-25 18:31:47',1,6,NULL,NULL,1921,1925,NULL,0,0,0,NULL,1,NULL,NULL),(188,'test car 188',1971,1979,'',NULL,1,63271,1,0,0,1,'2016-11-25 18:31:47',1,7,NULL,NULL,1972,1979,NULL,0,0,0,NULL,1,NULL,NULL),(189,'test car 189',1979,1988,'',NULL,1,11408,1,0,0,0,'2016-11-25 18:31:47',10,6,NULL,NULL,1980,1988,NULL,0,0,0,NULL,1,NULL,NULL),(190,'test car 190',1913,1916,'',NULL,1,49593,0,0,0,1,'2016-11-25 18:31:47',12,3,NULL,NULL,1913,1916,NULL,0,0,0,NULL,1,NULL,NULL),(191,'test car 191',1959,1960,'',NULL,1,45372,1,0,0,0,'2016-11-25 18:31:47',7,4,NULL,NULL,1959,1960,NULL,0,0,0,NULL,1,NULL,NULL),(192,'test car 192',1908,1909,'',NULL,1,62856,0,0,0,1,'2016-11-25 18:31:47',4,3,NULL,NULL,1908,1909,NULL,0,0,0,NULL,1,NULL,NULL),(193,'test car 193',1908,1916,'',NULL,1,19414,1,0,0,0,'2016-11-25 18:31:47',8,1,NULL,NULL,1908,1916,NULL,0,0,0,NULL,1,NULL,NULL),(194,'test car 194',1931,1938,'',NULL,1,83551,0,0,0,0,'2016-11-25 18:31:47',6,10,NULL,NULL,1931,1938,NULL,0,0,0,NULL,1,NULL,NULL),(195,'test car 195',1909,1919,'',NULL,1,94561,1,0,0,0,'2016-11-25 18:31:47',5,2,NULL,NULL,1910,1919,NULL,0,0,0,NULL,1,NULL,NULL),(196,'test car 196',1986,1986,'',NULL,1,44962,0,0,0,0,'2016-11-25 18:31:47',5,12,NULL,NULL,1986,1986,NULL,0,0,0,NULL,1,NULL,NULL),(197,'test car 197',1988,1995,'',NULL,1,99262,1,0,0,1,'2016-11-25 18:31:47',10,12,NULL,NULL,1988,1995,NULL,0,0,0,NULL,1,NULL,NULL),(198,'test car 198',2008,2008,'',NULL,1,41663,1,0,0,0,'2016-11-25 18:31:47',12,9,NULL,NULL,2008,2008,NULL,0,0,0,NULL,1,NULL,NULL),(199,'test car 199',1990,1991,'',NULL,1,727,0,0,0,0,'2016-11-25 18:31:47',10,8,NULL,NULL,1990,1991,NULL,0,0,0,NULL,1,NULL,NULL),(200,'test car 200',1973,1981,'',NULL,1,50120,0,0,0,0,'2016-11-25 18:31:47',10,4,NULL,NULL,1974,1981,NULL,0,0,0,NULL,1,NULL,NULL),(201,'test car 201',2003,2007,'',NULL,1,32557,1,0,0,0,'2016-11-25 18:31:47',4,7,NULL,NULL,2004,2007,NULL,0,0,0,NULL,1,NULL,NULL),(202,'test car 202',1909,1910,'',NULL,1,5563,1,0,0,0,'2016-11-25 18:31:47',8,7,NULL,NULL,1910,1910,NULL,0,0,0,NULL,1,NULL,NULL),(203,'test car 203',1911,1918,'',NULL,1,67707,0,0,0,1,'2016-11-25 18:31:47',7,9,NULL,NULL,1912,1918,NULL,0,0,0,NULL,1,NULL,NULL);
/*!40000 ALTER TABLE `cars` ENABLE KEYS */;

--
-- Table structure for table `cars_pictures`
--

DROP TABLE IF EXISTS `cars_pictures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cars_pictures` (
  `car_id` int(10) unsigned NOT NULL DEFAULT '0',
  `picture_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`car_id`,`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cars_pictures`
--

/*!40000 ALTER TABLE `cars_pictures` DISABLE KEYS */;
/*!40000 ALTER TABLE `cars_pictures` ENABLE KEYS */;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  `catname` varchar(35) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1054 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 123904 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

/*!40000 ALTER TABLE `category` DISABLE KEYS */;
/*!40000 ALTER TABLE `category` ENABLE KEYS */;

--
-- Table structure for table `category_item`
--

DROP TABLE IF EXISTS `category_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_item` (
  `category_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`category_id`,`item_id`),
  KEY `car_id` (`item_id`),
  KEY `category_id` (`category_id`,`add_datetime`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `category_item_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_item_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_item_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_item`
--

/*!40000 ALTER TABLE `category_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_item` ENABLE KEYS */;

--
-- Table structure for table `category_language`
--

DROP TABLE IF EXISTS `category_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_language` (
  `category_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  PRIMARY KEY (`category_id`,`language`),
  CONSTRAINT `category_language_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_language`
--

/*!40000 ALTER TABLE `category_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_language` ENABLE KEYS */;

--
-- Table structure for table `category_parent`
--

DROP TABLE IF EXISTS `category_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_parent` (
  `category_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `level` int(10) unsigned NOT NULL,
  PRIMARY KEY (`category_id`,`parent_id`),
  KEY `FK_category_parent_category_id2` (`parent_id`),
  CONSTRAINT `FK_category_parent_category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_category_parent_category_id2` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=45;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_parent`
--

/*!40000 ALTER TABLE `category_parent` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_parent` ENABLE KEYS */;

--
-- Table structure for table `comment_topic`
--

DROP TABLE IF EXISTS `comment_topic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_topic` (
  `type_id` tinyint(3) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `messages` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`type_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_topic`
--

/*!40000 ALTER TABLE `comment_topic` DISABLE KEYS */;
/*!40000 ALTER TABLE `comment_topic` ENABLE KEYS */;

--
-- Table structure for table `comment_topic_view`
--

DROP TABLE IF EXISTS `comment_topic_view`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_topic_view` (
  `type_id` tinyint(3) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`,`item_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_topic_view_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_topic_view`
--

/*!40000 ALTER TABLE `comment_topic_view` DISABLE KEYS */;
/*!40000 ALTER TABLE `comment_topic_view` ENABLE KEYS */;

--
-- Table structure for table `comment_vote`
--

DROP TABLE IF EXISTS `comment_vote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_vote` (
  `comment_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_vote_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_vote_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_vote`
--

/*!40000 ALTER TABLE `comment_vote` DISABLE KEYS */;
/*!40000 ALTER TABLE `comment_vote` ENABLE KEYS */;

--
-- Table structure for table `comments_messages`
--

DROP TABLE IF EXISTS `comments_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `type_id` tinyint(11) unsigned NOT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `author_id` int(11) unsigned DEFAULT NULL,
  `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `message` mediumtext NOT NULL,
  `moderator_attention` tinyint(3) unsigned NOT NULL,
  `vote` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `replies_count` int(10) unsigned NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `type_id` (`type_id`,`item_id`),
  KEY `datetime_sort` (`datetime`),
  KEY `deleted_by` (`deleted_by`),
  KEY `parent_id` (`parent_id`),
  KEY `moderator_attention` (`moderator_attention`),
  CONSTRAINT `comments_messages_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comments_messages_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=932834 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=266 COMMENT='InnoDB free: 124928 kB; (`author_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments_messages`
--

/*!40000 ALTER TABLE `comments_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments_messages` ENABLE KEYS */;

--
-- Table structure for table `comments_types`
--

DROP TABLE IF EXISTS `comments_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments_types` (
  `id` tinyint(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments_types`
--

/*!40000 ALTER TABLE `comments_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments_types` ENABLE KEYS */;

--
-- Table structure for table `contact`
--

DROP TABLE IF EXISTS `contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact` (
  `user_id` int(10) unsigned NOT NULL,
  `contact_user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`contact_user_id`),
  KEY `contact_user_id` (`contact_user_id`),
  CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact`
--

/*!40000 ALTER TABLE `contact` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact` ENABLE KEYS */;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  `group_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;

--
-- Table structure for table `countries_groups`
--

DROP TABLE IF EXISTS `countries_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `catname` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `catname` (`catname`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries_groups`
--

/*!40000 ALTER TABLE `countries_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `countries_groups` ENABLE KEYS */;

--
-- Table structure for table `day_stat`
--

DROP TABLE IF EXISTS `day_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `day_stat` (
  `day_date` date NOT NULL,
  `hits` mediumint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`day_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `day_stat`
--

/*!40000 ALTER TABLE `day_stat` DISABLE KEYS */;
/*!40000 ALTER TABLE `day_stat` ENABLE KEYS */;

--
-- Table structure for table `engine_parent_cache`
--

DROP TABLE IF EXISTS `engine_parent_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engine_parent_cache` (
  `engine_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`engine_id`,`parent_id`),
  KEY `parent_fk` (`parent_id`),
  CONSTRAINT `engine_fk` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engine_parent_cache`
--

/*!40000 ALTER TABLE `engine_parent_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `engine_parent_cache` ENABLE KEYS */;

--
-- Table structure for table `engines`
--

DROP TABLE IF EXISTS `engines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `owner_id` int(10) unsigned NOT NULL DEFAULT '0',
  `last_editor_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `last_editor_id` (`last_editor_id`),
  CONSTRAINT `engines_ibfk_1` FOREIGN KEY (`last_editor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `engines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1748 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB; (`brand_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engines`
--

/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO `engines` VALUES (1,NULL,'Test engine',1,NULL);
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;

--
-- Table structure for table `factory`
--

DROP TABLE IF EXISTS `factory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `year_from` smallint(5) unsigned DEFAULT NULL,
  `year_to` smallint(5) unsigned DEFAULT NULL,
  `point` point DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `text_id` (`text_id`),
  KEY `point` (`point`(25))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factory`
--

/*!40000 ALTER TABLE `factory` DISABLE KEYS */;
INSERT INTO `factory` VALUES (1,'Test factory',1999,2005,NULL,NULL);
/*!40000 ALTER TABLE `factory` ENABLE KEYS */;

--
-- Table structure for table `factory_item`
--

DROP TABLE IF EXISTS `factory_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_item` (
  `factory_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`factory_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `factory_item_ibfk_1` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`),
  CONSTRAINT `factory_item_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factory_item`
--

/*!40000 ALTER TABLE `factory_item` DISABLE KEYS */;
INSERT INTO `factory_item` VALUES (1,1);
/*!40000 ALTER TABLE `factory_item` ENABLE KEYS */;

--
-- Table structure for table `formated_image`
--

DROP TABLE IF EXISTS `formated_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `formated_image` (
  `image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(255) NOT NULL,
  `formated_image_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`image_id`,`format`),
  KEY `formated_image_id` (`formated_image_id`,`image_id`) USING BTREE,
  CONSTRAINT `formated_image_ibfk_1` FOREIGN KEY (`formated_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3617324 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formated_image`
--

/*!40000 ALTER TABLE `formated_image` DISABLE KEYS */;
/*!40000 ALTER TABLE `formated_image` ENABLE KEYS */;

--
-- Table structure for table `forums_theme_parent`
--

DROP TABLE IF EXISTS `forums_theme_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_theme_parent` (
  `forum_theme_id` int(11) unsigned NOT NULL,
  `parent_id` int(11) unsigned NOT NULL,
  `level` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`forum_theme_id`,`parent_id`),
  KEY `FK_forum_theme_parent_forums_themes_id2` (`parent_id`),
  CONSTRAINT `FK_forum_theme_parent_forums_themes_id` FOREIGN KEY (`forum_theme_id`) REFERENCES `forums_themes` (`id`),
  CONSTRAINT `FK_forum_theme_parent_forums_themes_id2` FOREIGN KEY (`parent_id`) REFERENCES `forums_themes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forums_theme_parent`
--

/*!40000 ALTER TABLE `forums_theme_parent` DISABLE KEYS */;
/*!40000 ALTER TABLE `forums_theme_parent` ENABLE KEYS */;

--
-- Table structure for table `forums_themes`
--

DROP TABLE IF EXISTS `forums_themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_themes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `folder` varchar(30) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `position` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `description` tinytext NOT NULL,
  `topics` int(10) unsigned NOT NULL DEFAULT '0',
  `messages` int(10) unsigned NOT NULL DEFAULT '0',
  `is_moderator` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `disable_topics` tinyint(4) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder` (`folder`),
  UNIQUE KEY `caption` (`name`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `FK_forums_themes_forums_themes_id` FOREIGN KEY (`parent_id`) REFERENCES `forums_themes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=1170 COMMENT='InnoDB free: 125952 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forums_themes`
--

/*!40000 ALTER TABLE `forums_themes` DISABLE KEYS */;
INSERT INTO `forums_themes` VALUES (1,NULL,'test','Test',1,'That is test theme',1,1,0,0);
/*!40000 ALTER TABLE `forums_themes` ENABLE KEYS */;

--
-- Table structure for table `forums_topics`
--

DROP TABLE IF EXISTS `forums_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_topics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theme_id` int(11) unsigned DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `author_id` int(10) unsigned NOT NULL DEFAULT '0',
  `add_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `_messages` int(10) unsigned NOT NULL DEFAULT '0',
  `views` int(10) unsigned NOT NULL DEFAULT '0',
  `status` enum('normal','closed','deleted') NOT NULL DEFAULT 'normal',
  `author_ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `theme_id` (`theme_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `forums_topics_fk` FOREIGN KEY (`theme_id`) REFERENCES `forums_themes` (`id`),
  CONSTRAINT `forums_topics_fk1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3077 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`theme_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forums_topics`
--

/*!40000 ALTER TABLE `forums_topics` DISABLE KEYS */;
INSERT INTO `forums_topics` VALUES (1,1,'Test topic',1,'2016-11-25 18:31:48',0,0,'normal','0');
/*!40000 ALTER TABLE `forums_topics` ENABLE KEYS */;

--
-- Table structure for table `forums_topics_subscribers`
--

DROP TABLE IF EXISTS `forums_topics_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_topics_subscribers` (
  `topic_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`topic_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `topics_subscribers_fk` FOREIGN KEY (`topic_id`) REFERENCES `forums_topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topics_subscribers_fk1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forums_topics_subscribers`
--

/*!40000 ALTER TABLE `forums_topics_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `forums_topics_subscribers` ENABLE KEYS */;

--
-- Table structure for table `htmls`
--

DROP TABLE IF EXISTS `htmls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `htmls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `html` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `htmls`
--

/*!40000 ALTER TABLE `htmls` DISABLE KEYS */;
/*!40000 ALTER TABLE `htmls` ENABLE KEYS */;

--
-- Table structure for table `image`
--

DROP TABLE IF EXISTS `image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `image` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filepath` varchar(255) NOT NULL,
  `filesize` int(10) unsigned NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dir` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filepath`,`dir`),
  KEY `image_dir_id` (`dir`)
) ENGINE=InnoDB AUTO_INCREMENT=3617343 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `image`
--

/*!40000 ALTER TABLE `image` DISABLE KEYS */;
INSERT INTO `image` VALUES (1,'1.jpg',242405,1200,800,'2016-11-25 18:31:48','picture'),(33,'2.jpg',242405,1200,800,'2016-11-25 18:31:48','picture'),(35,'3.jpg',242405,1200,800,'2016-11-25 18:31:48','picture'),(37,'4.jpg',242405,1200,800,'2016-11-25 18:31:48','picture'),(38,'5.jpg',242405,1200,800,'2016-11-25 18:31:48','picture');
/*!40000 ALTER TABLE `image` ENABLE KEYS */;

--
-- Table structure for table `image_dir`
--

DROP TABLE IF EXISTS `image_dir`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `image_dir` (
  `dir` varchar(255) NOT NULL,
  `count` int(10) unsigned NOT NULL,
  PRIMARY KEY (`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `image_dir`
--

/*!40000 ALTER TABLE `image_dir` DISABLE KEYS */;
/*!40000 ALTER TABLE `image_dir` ENABLE KEYS */;

--
-- Table structure for table `ip_monitoring4`
--

DROP TABLE IF EXISTS `ip_monitoring4`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_monitoring4` (
  `day_date` date NOT NULL,
  `hour` tinyint(3) unsigned NOT NULL,
  `tenminute` tinyint(3) unsigned NOT NULL,
  `minute` tinyint(3) unsigned NOT NULL,
  `count` int(10) unsigned NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`,`day_date`,`hour`,`tenminute`,`minute`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_monitoring4`
--

/*!40000 ALTER TABLE `ip_monitoring4` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_monitoring4` ENABLE KEYS */;

--
-- Table structure for table `ip_whitelist`
--

DROP TABLE IF EXISTS `ip_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_whitelist` (
  `description` varchar(255) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_whitelist`
--

/*!40000 ALTER TABLE `ip_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_whitelist` ENABLE KEYS */;

--
-- Table structure for table `lang_pages`
--

DROP TABLE IF EXISTS `lang_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lang_pages` (
  `page_id` int(10) unsigned NOT NULL,
  `language_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`page_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lang_pages`
--

/*!40000 ALTER TABLE `lang_pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `lang_pages` ENABLE KEYS */;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `locale` varchar(10) NOT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `locale` (`locale`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;

--
-- Table structure for table `links`
--

DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('default','official','helper','club') NOT NULL DEFAULT 'default' COMMENT 'Г’ГЁГЇ',
  `brandId` int(10) unsigned NOT NULL DEFAULT '0',
  `url` varchar(100) NOT NULL COMMENT 'Г Г¤Г°ГҐГ±',
  `name` varchar(250) NOT NULL COMMENT 'ГЌГ Г§ГўГ Г­ГЁГҐ',
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brandId`)
) ENGINE=InnoDB AUTO_INCREMENT=1040 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `links`
--

/*!40000 ALTER TABLE `links` DISABLE KEYS */;
/*!40000 ALTER TABLE `links` ENABLE KEYS */;

--
-- Table structure for table `log_events`
--

DROP TABLE IF EXISTS `log_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `add_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `add_datetime` (`add_datetime`),
  CONSTRAINT `log_events_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2775694 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events`
--

/*!40000 ALTER TABLE `log_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events` ENABLE KEYS */;

--
-- Table structure for table `log_events_articles`
--

DROP TABLE IF EXISTS `log_events_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_articles` (
  `log_event_id` int(10) unsigned NOT NULL,
  `article_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`article_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `log_events_articles_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_articles_fk1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_articles`
--

/*!40000 ALTER TABLE `log_events_articles` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_articles` ENABLE KEYS */;

--
-- Table structure for table `log_events_brands`
--

DROP TABLE IF EXISTS `log_events_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_brands` (
  `log_event_id` int(10) unsigned NOT NULL,
  `brand_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`brand_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `log_events_brands_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_brands_fk1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_brands`
--

/*!40000 ALTER TABLE `log_events_brands` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_brands` ENABLE KEYS */;

--
-- Table structure for table `log_events_cars`
--

DROP TABLE IF EXISTS `log_events_cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_cars` (
  `log_event_id` int(10) unsigned NOT NULL,
  `car_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`car_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `log_events_cars_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_cars`
--

/*!40000 ALTER TABLE `log_events_cars` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_cars` ENABLE KEYS */;

--
-- Table structure for table `log_events_engines`
--

DROP TABLE IF EXISTS `log_events_engines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_engines` (
  `log_event_id` int(10) unsigned NOT NULL,
  `engine_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`engine_id`),
  KEY `engine_id` (`engine_id`),
  CONSTRAINT `log_events_engines_fk` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_engines_fk1` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_engines`
--

/*!40000 ALTER TABLE `log_events_engines` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_engines` ENABLE KEYS */;

--
-- Table structure for table `log_events_factory`
--

DROP TABLE IF EXISTS `log_events_factory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_factory` (
  `log_event_id` int(10) unsigned NOT NULL,
  `factory_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`factory_id`),
  KEY `factory_id` (`factory_id`),
  CONSTRAINT `log_events_factory_ibfk_1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_factory_ibfk_2` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_factory`
--

/*!40000 ALTER TABLE `log_events_factory` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_factory` ENABLE KEYS */;

--
-- Table structure for table `log_events_pictures`
--

DROP TABLE IF EXISTS `log_events_pictures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_pictures` (
  `log_event_id` int(10) unsigned NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `log_events_pictures_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_pictures_fk1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_pictures`
--

/*!40000 ALTER TABLE `log_events_pictures` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_pictures` ENABLE KEYS */;

--
-- Table structure for table `log_events_twins_groups`
--

DROP TABLE IF EXISTS `log_events_twins_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_twins_groups` (
  `log_event_id` int(10) unsigned NOT NULL,
  `twins_group_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`twins_group_id`),
  KEY `twins_group_id` (`twins_group_id`),
  CONSTRAINT `log_events_twins_groups_ibfk_1` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_events_twins_groups_ibfk_2` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_twins_groups`
--

/*!40000 ALTER TABLE `log_events_twins_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_twins_groups` ENABLE KEYS */;

--
-- Table structure for table `log_events_user`
--

DROP TABLE IF EXISTS `log_events_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_events_user` (
  `log_event_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`log_event_id`,`user_id`),
  KEY `FK_log_events_user_users_id` (`user_id`),
  CONSTRAINT `FK_log_events_user_log_events_id` FOREIGN KEY (`log_event_id`) REFERENCES `log_events` (`id`),
  CONSTRAINT `FK_log_events_user_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_events_user`
--

/*!40000 ALTER TABLE `log_events_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_events_user` ENABLE KEYS */;

--
-- Table structure for table `login_state`
--

DROP TABLE IF EXISTS `login_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_state` (
  `state` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `language` varchar(2) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `service` varchar(50) NOT NULL,
  PRIMARY KEY (`state`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_state`
--

/*!40000 ALTER TABLE `login_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_state` ENABLE KEYS */;

--
-- Table structure for table `message`
--

DROP TABLE IF EXISTS `message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message` (
  `id` int(11) unsigned NOT NULL,
  `account_id` int(11) unsigned NOT NULL,
  `with_account_id` int(11) unsigned NOT NULL,
  `by_account_id` int(11) unsigned NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message`
--

/*!40000 ALTER TABLE `message` DISABLE KEYS */;
/*!40000 ALTER TABLE `message` ENABLE KEYS */;

--
-- Table structure for table `modification`
--

DROP TABLE IF EXISTS `modification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `car_id` int(10) unsigned NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `modification_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `modification_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `modification_group` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modification`
--

/*!40000 ALTER TABLE `modification` DISABLE KEYS */;
/*!40000 ALTER TABLE `modification` ENABLE KEYS */;

--
-- Table structure for table `modification_group`
--

DROP TABLE IF EXISTS `modification_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modification_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modification_group`
--

/*!40000 ALTER TABLE `modification_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `modification_group` ENABLE KEYS */;

--
-- Table structure for table `modification_picture`
--

DROP TABLE IF EXISTS `modification_picture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modification_picture` (
  `modification_id` int(11) NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`modification_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `modification_picture_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modification_picture_ibfk_2` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modification_picture`
--

/*!40000 ALTER TABLE `modification_picture` DISABLE KEYS */;
/*!40000 ALTER TABLE `modification_picture` ENABLE KEYS */;

--
-- Table structure for table `modification_value`
--

DROP TABLE IF EXISTS `modification_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modification_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modification_id` int(11) NOT NULL,
  `value` varchar(100) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value` (`value`,`modification_id`),
  KEY `modification_id` (`modification_id`),
  CONSTRAINT `modification_value_ibfk_1` FOREIGN KEY (`modification_id`) REFERENCES `modification` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modification_value`
--

/*!40000 ALTER TABLE `modification_value` DISABLE KEYS */;
/*!40000 ALTER TABLE `modification_value` ENABLE KEYS */;

--
-- Table structure for table `museum`
--

DROP TABLE IF EXISTS `museum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `museum` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `_lat` double DEFAULT NULL,
  `_lng` double DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `description` text CHARACTER SET ucs2 NOT NULL,
  `address` text NOT NULL,
  `img` int(10) unsigned DEFAULT NULL,
  `point` point DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `point` (`point`(25))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `museum`
--

/*!40000 ALTER TABLE `museum` DISABLE KEYS */;
/*!40000 ALTER TABLE `museum` ENABLE KEYS */;

--
-- Table structure for table `of_day`
--

DROP TABLE IF EXISTS `of_day`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `of_day` (
  `day_date` date NOT NULL,
  `picture_id` int(10) unsigned DEFAULT NULL,
  `car_id` int(10) unsigned DEFAULT NULL,
  `twitter_sent` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`day_date`),
  KEY `of_day_fk` (`picture_id`),
  KEY `FK_of_day_cars_id` (`car_id`),
  CONSTRAINT `FK_of_day_cars_id` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `of_day_fk` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`picture_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `of_day`
--

/*!40000 ALTER TABLE `of_day` DISABLE KEYS */;
INSERT INTO `of_day` VALUES ('2016-11-25',NULL,1,0);
/*!40000 ALTER TABLE `of_day` ENABLE KEYS */;

--
-- Table structure for table `page_language`
--

DROP TABLE IF EXISTS `page_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_language` (
  `page_id` int(10) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `breadcrumbs` varchar(100) NOT NULL,
  PRIMARY KEY (`page_id`,`language`),
  CONSTRAINT `page_language_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_language`
--

/*!40000 ALTER TABLE `page_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `page_language` ENABLE KEYS */;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `breadcrumbs` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_group_node` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `registered_only` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `class` varchar(30) DEFAULT NULL,
  `guest_only` tinyint(3) unsigned NOT NULL,
  `position` smallint(6) NOT NULL,
  `inherit_blocks` tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_position` (`parent_id`,`position`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `pages_fk` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,NULL,'Index page','Encyclopedia of cars in the pictures. AutoWP.ru','','/',0,0,'',0,1,0),(2,1,'Главное меню','','','',1,0,'',0,1,0),(10,1,'Brand','%BRAND_NAME%','%BRAND_NAME%','/%BRAND_CATNAME%/',0,0,'',0,1045,0),(14,10,'%BRAND_NAME% cars in chronological order','%BRAND_NAME% cars in chronological order','Cars in chronological order','/%BRAND_CATNAME%/cars/',0,0,'',0,913,1),(15,10,'Last pictures of %BRAND_NAME%','Last pictures of %BRAND_NAME%','Last pictures','/%BRAND_CATNAME%/recent/',0,0,'',0,918,1),(18,1,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/picture/%PICTURE_ID%',0,0,'',0,1021,0),(19,1,'Brands','Brands','Brands','',0,0,'',0,1035,0),(20,19,'Тип производителей','',NULL,NULL,0,0,NULL,0,1,1),(21,2,'Mostly','Mostly','','/mosts',0,0,'',0,24,0),(22,2,'Categories','Categories','','/category',0,0,'',0,25,1),(23,22,'%CATEGORY_NAME%','%CATEGORY_NAME%','%CATEGORY_SHORT_NAME%','/category/%CATEGORY_CATNAME%',0,0,'/category/%CATEGORY_CATNAME%',0,1,1),(24,1,'Лимитированные и специальные серии автомобилей','','','/limitededitions/',0,0,'',0,777,0),(25,2,'Twins','','','/twins',0,0,'',0,23,0),(26,25,'%TWINS_GROUP_NAME%','%TWINS_GROUP_NAME%','%TWINS_GROUP_NAME%','/twins/group%TWINS_GROUP_ID%',0,0,'',0,6,1),(27,26,'Specifications of %TWINS_GROUP_NAME%','Specifications of %TWINS_GROUP_NAME%','Specifications','/twins/group%TWINS_GROUP_ID%/specifications',0,0,'',0,9,1),(28,26,'All pictures of %TWINS_GROUP_NAME%','All pictures of %TWINS_GROUP_NAME%','All pictures','/twins/group%TWINS_GROUP_ID%/pictures',0,0,'',0,7,1),(29,87,'Add picture','Add picture','','/upload/',0,1,'',0,18,0),(30,29,'Select brand','Select brand','Select brand','',0,0,'',0,5,1),(31,1,'Articles','Articles','Articles','/articles/',0,0,'',0,1046,1),(32,31,'%ARTICLE_NAME%','%ARTICLE_NAME%','%ARTICLE_NAME%','',0,0,'/articles/%ARTICLE_CATNAME%/',0,1,1),(33,10,'%CAR_NAME%','%CAR_NAME%','%SHORT_CAR_NAME%','/%BRAND_CATNAME%/%CAR_CATNAME%/',0,0,'',0,909,1),(34,33,'All pictures of %CAR_NAME%','All pictures of %CAR_NAME%','All pictures','/%BRAND_CATNAME%/%CAR_CATNAME%/pictures/',0,0,'',0,13,1),(36,33,'Specifications of %CAR_NAME%','Specifications of %CAR_NAME%','Specifications','/%BRAND_CATNAME%/%CAR_CATNAME%/specifications/',0,0,'',0,14,1),(37,10,'Concepts & prototypes','Concepts & prototypes','Concepts & prototypes','/%BRAND_CATNAME%/concepts/',0,0,'',0,915,1),(38,10,'%BRAND_NAME% engines','%BRAND_NAME% engines','Engines','/%BRAND_CATNAME%/engines/',0,0,'',0,914,1),(39,10,'%BRAND_NAME% logotypes','%BRAND_NAME% logotypes','Logotypes','/%BRAND_CATNAME%/logotypes/',0,0,'',0,916,1),(40,10,'%BRAND_NAME% miscellaneous','%BRAND_NAME% miscellaneous','Miscellaneous','/%BRAND_CATNAME%/mixed/',0,0,'',0,917,1),(41,10,'Unsorted','Unsorted','Unsorted','/%BRAND_CATNAME%/other/',0,0,'',0,920,1),(42,2,'Forums','Forums','','/forums',0,0,'',0,27,0),(43,42,'%THEME_NAME%','%THEME_NAME%','%THEME_NAME%','/forums/index/%THEME_ID%',0,0,'',0,1,1),(44,43,'%TOPIC_NAME%','%TOPIC_NAME%','%TOPIC_NAME%','/forums/topic/topic/topic_id/%TOPIC_ID%',0,0,'',0,5,1),(45,43,'New topic','New topic','New topic','/forums/topic/new/theme_id/%THEME_ID%',0,0,'',0,4,1),(48,87,'Cabinet','Cabinet','','/account',0,1,'',0,27,0),(49,48,'Personal messages','Personal messages','','/account/pm',0,0,'',0,23,1),(51,1,'New pictures','','','/new',0,0,'',0,1036,0),(52,1,'Registration','Registration','','/registration',0,0,'',0,1056,0),(53,52,'ok','Успешная регистрация','','',0,0,'',0,1,1),(54,48,'Confirm the email address','Confirm the email address','','',0,0,'',0,21,1),(55,48,'My e-mail','My e-mail','','/account/email',0,1,'',0,26,1),(56,55,'Changed','Changing e-mail','','',0,1,'',0,1,1),(57,48,'Forums subscriptions','Forums subscriptions','','/account/forums',0,1,'',0,31,1),(58,10,'%BRAND_NAME% %DPBRAND_NAME%','%BRAND_NAME% %DPBRAND_NAME%','%DPBRAND_NAME%','/%BRAND_CATNAME%/%DPBRAND_CATNAME%/',0,0,'',0,901,1),(59,10,'%BRAND_NAME% %DESIGN_PROJECT_NAME%','%BRAND_NAME% %DESIGN_PROJECT_NAME%','%DESIGN_PROJECT_NAME%','/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/',0,0,'',0,902,1),(60,1,'Password recovery','Password recovery','','',0,0,'',0,1038,0),(61,1,'All brands','','','/brands/',0,0,'',0,1039,0),(62,1,'%USER_NAME%','%USER_NAME%','%USER_NAME%','/users/%USER_IDENTITY%',0,0,'',0,1020,1),(63,62,'User\'s pictures','User\'s pictures','Pictures','/users/%USER_IDENTITY%',0,0,'',0,1,1),(66,59,'All pictures of %BRAND_NAME% %DESIGN_PROJECT_NAME%','All pictures of %BRAND_NAME% %DESIGN_PROJECT_NAME%','All pictures','/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/pictures/',0,0,'',0,1,1),(67,1,'Moderator page','','','/moder',0,0,'',0,1040,0),(68,67,'Страницы сайта','','','/moder/pages',0,0,NULL,0,1,1),(69,68,'Добавить','','','',0,0,NULL,0,1,1),(70,68,'Изменить','','','',0,0,NULL,0,2,1),(71,67,'Права','','','/moder/rights',0,0,NULL,0,2,1),(72,73,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/moder/pictures/picture/picture_id/%PICTURE_ID%',0,1,'',0,3,1),(73,67,'Картинки','','','/moder/pictures',0,1,'',0,20,1),(74,67,'Автомобили по алфавиту','','','/moder/alpha-cars',0,0,NULL,0,5,1),(75,67,'Журнал событий','','','/log/',0,0,NULL,0,6,1),(76,1,'Немодерированное','Немодерированное','','',0,0,'',0,1053,0),(77,67,'Трафик','','','/moder/trafic',0,0,NULL,0,7,1),(78,131,'%CAR_NAME%','%CAR_NAME%','%CAR_NAME%','/moder/cars/car/car_id/%CAR_ID%',0,1,'',0,26,1),(79,1,'Sign in','Sign in','','/login',0,0,'',1,1058,0),(80,49,'Sent','Sent','','/account/pm/sent',0,0,'',0,15,1),(81,49,'System messages','System messages','','/account/pm/system',0,0,'',0,18,1),(82,67,'Engines','Engines','Engines','/moder/engines',0,1,'',0,27,1),(83,44,'Move','Move','Move','/forums/topic/move/topic_id/%TOPIC_ID%',0,0,'',0,1,1),(85,67,'%BRAND_NAME%','%BRAND_NAME%','%BRAND_NAME%','/moder/brands/brand/brand_id/%BRAND_ID%',0,1,'',0,24,1),(86,29,'Image successfully uploaded to the site','Image successfully uploaded to the site','Success','/upload/success',0,0,'',0,6,1),(87,1,'More','More','','',1,0,'',0,1043,1),(89,87,'Feedback','','','/feedback',0,0,'',0,19,0),(90,87,'Sign out','','','/login/logout',0,1,'',0,28,1),(91,87,'Registration','','','/registration',0,0,'',1,4,1),(93,89,'Message sent','','','',0,0,'',0,0,1),(94,48,'Unmoderated','Unmoderated','','/account/not-taken-pictures',0,1,'',0,25,1),(96,67,'Автомобили-близнецы','','','',0,1,'',0,11,1),(97,67,'Ракурсы','','','',0,1,'',0,12,1),(100,67,'Аттрибуты','','','/moder/attrs',0,1,'',0,14,1),(101,100,'%ATTR_NAME%','%ATTR_NAME%','%ATTR_NAME%','',0,1,'',0,1,1),(102,1,'Specs editors %CAR_NAME%','Specs editors %CAR_NAME%','Specs editors','',0,0,'',0,1047,1),(103,102,'История изменения','История изменения','','/moder/index/attrs-change-log',0,1,'',0,18,1),(104,1,'Пользовательская статистика','','','',0,0,'',0,1000,0),(105,1,'Add a comment','Add a comment','','',0,0,'',0,1041,0),(106,1,'Rules','Rules','','/rules',0,0,'',0,1042,0),(107,67,'Заявки на удаление','Заявки на удаление','','',0,0,'',0,15,1),(109,1,'Cutaway','','Cutaway','/cutaway',0,0,'',0,1003,0),(110,67,'Комментарии','Комментарии','','/moder/comments',0,1,'',0,16,1),(111,1,'Engine spec editor %ENGINE_NAME%','Engine spec editor %ENGINE_NAME%','Engine spec editor','',0,0,'',0,1048,1),(114,67,'Журнал ТТХ','Журнал ТТХ','','/moder/spec',0,1,'',0,17,1),(115,67,'Музеи','Музеи','Музеи','/moder/museum',0,1,'',0,18,1),(116,115,'Музей','Музей','%MUSEUM_NAME%','/moder/museum/edit/museum_id/%MUSEUM_ID%/',0,1,'',0,1,1),(117,2,'Map','Map','','/map',0,0,'',0,26,0),(118,115,'Новый','Новый','Новый','/moder/museum/new',0,1,'',0,2,1),(119,67,'Статистика','','','/moder/index/stat',0,1,'',0,19,1),(120,68,'Блоки','','','',0,1,'',0,3,1),(122,1,'Specifications','Specifications','Specifications','/spec/',0,0,'',0,1057,1),(123,48,'My accounts','My accounts','My accounts','/profile/accounts/',0,1,'',0,22,0),(124,87,'Who is online?','','','/users/online',0,0,'online',0,24,1),(125,67,'Categories','Categories','','/moder/category',0,1,'',0,22,1),(126,125,'Add','Add','','/moder/category/new/',0,1,'',0,1,1),(127,125,'Edit','Edit','','',0,1,'',0,3,1),(128,49,'Inbox','Inbox','','/account/pm',0,1,'',0,17,0),(129,48,'Profile','Profile','','/account/profile',0,1,'',0,12,0),(130,48,'My pictures','My pictures','','',0,1,'',0,30,0),(131,67,'Vehicles','Vehicles','Vehicles','/moder/cars',0,1,'',0,26,1),(133,48,'Access','Access Control','','/account/access',0,1,'',0,27,1),(134,60,'New password','New password','','',0,0,'',0,4,0),(135,60,'New password saved','','','',0,1,'',0,5,0),(136,87,'About us','About us','','/about',0,0,'',0,29,1),(137,48,'Account delete','','','/account/delete',0,1,'',0,28,1),(138,14,'%BRAND_NAME% %CAR_TYPE_NAME% in chronological order','%BRAND_NAME% %CAR_TYPE_NAME% in chronological order','%CAR_TYPE_NAME%','/%BRAND_CATNAME%/cars/%CAR_TYPE_CATNAME%/',0,0,'',0,1,0),(140,61,'%BRAND_TYPE_NAME%','%BRAND_TYPE_NAME%','%BRAND_TYPE_NAME%','/brands/%BRAND_TYPE_NAME%',0,0,'',0,1,1),(141,63,'%BRAND_NAME% pictures','%BRAND_NAME% pictures','%BRAND_NAME% pictures','/users/%USER_IDENTITY%/pictures/%BRAND_CATNAME%',0,0,'',0,1,1),(142,100,'%ATTR_ITEMTYPE_NAME% %ZONE_NAME%','%ATTR_ITEMTYPE_NAME% %ZONE_NAME%','%ATTR_ITEMTYPE_NAME% %ZONE_NAME%','/moder/attrs/zone/zone_id/%ZONE_ID%',0,1,'',0,4,1),(143,96,'%TWINS_GROUP_NAME%','%TWINS_GROUP_NAME%','%TWINS_GROUP_NAME%','/moder/twins/twins-group/twins_group_id/%TWINS_GROUP_ID%',0,1,'',0,1,1),(144,78,'Brand selection','Brand selection','Brand selection','',0,1,'',0,1,1),(146,78,'Twins group selection','Twins group selection','Twins group selection','',0,1,'',0,5,1),(147,78,'Design project selection','Design project selection','Design project selection','',0,1,'',0,7,1),(148,72,'Cropper','Cropper','Cropper','',0,1,'',0,1,1),(149,72,'Move picture','Move picture','Move picture','',0,1,'',0,12,1),(153,25,'%BRAND_NAME% Twins','%BRAND_NAME% Twins','%BRAND_NAME%','/twins/%BRAND_CATNAME%',0,0,'',0,7,1),(154,21,'%MOST_NAME%','%MOST_NAME%','%MOST_NAME%','/mosts/%MOST_CATNAME%',0,0,'',0,1,1),(155,154,'Most %MOST_NAME% %CAR_TYPE_NAME%','Most %MOST_NAME% %CAR_TYPE_NAME%','%CAR_TYPE_NAME%','/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%',0,0,'',0,1,1),(156,155,'Most %MOST_NAME% %CAR_TYPE_NAME% %YEAR_NAME%','Most %MOST_NAME% %CAR_TYPE_NAME% %YEAR_NAME%','%YEAR_NAME%','/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%/%YEAR_CATNAME%',0,0,'',0,1,1),(157,1,'%VOTING_NAME%','%VOTING_NAME%','%VOTING_NAME%','/voting/voting/id/%VOTING_ID%',0,0,'',0,1022,0),(159,117,'Museum','%MUSEUM_NAME%','%MUSEUM_NAME%','/museums/museum/id/%MUSEUM_ID%',0,0,'',0,1,0),(161,1,'Pulse','Pulse','Pulse','/pulse/',0,0,'',0,1049,0),(162,23,'Pictures','Pictures','','/category/%CATEGORY_CATNAME%/pictures',0,0,'',0,4,0),(163,131,'New vehicle','New vehicle','New vehicle','',0,0,'',0,28,0),(164,10,'Mosts','Mosts','Mosts','/%BRAND_CATNAME%/mosts/',0,0,'',0,919,0),(165,164,'Most %MOST_NAME% %BRAND_NAME%','Most %MOST_NAME% %BRAND_NAME%','%MOST_NAME%','/%BRAND_CATNAME%/mosts/%MOST_CATNAME%',0,0,'',0,1,0),(166,165,'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME%','Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME%','%CAR_TYPE_NAME%','/%BRAND_CATNAME%/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%',0,0,'',0,1,0),(167,166,'Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME% %YEAR_NAME%','Most %MOST_NAME% %CAR_TYPE_NAME% %BRAND_NAME% %YEAR_NAME%','%YEAR_NAME%','/%BRAND_CATNAME%/mosts/%MOST_CATNAME%/%CAR_TYPE_CATNAME%/%YEAR_CATNAME%',0,0,'',0,1,0),(168,38,'%ENGINE_NAME% engine','%ENGINE_NAME% engine','%ENGINE_NAME% engine','/%BRAND_CATNAME%/engines/%ENGINE_ID%/',0,0,'',0,913,0),(169,82,'Engine %ENGINE_NAME%','Engine %ENGINE_NAME%','Engine %ENGINE_NAME%','/moder/engines/engine_id/%ENGINE_ID%/',0,0,'',0,1,0),(170,82,'Add','Add','Add','/moder/engines/add',0,0,'',0,3,0),(171,169,'Select parent','Select parent','Select parent','',0,0,'',0,1,0),(172,168,'Vehicles with engine %ENGINE_NAME%','Vehicles with engine %ENGINE_NAME%','Vehicles','',0,0,'',0,4,0),(173,1,'Statistics','Statistics','Statistics','/users/rating',0,0,'',0,1050,0),(174,1,'Specs','Specs','Specs','/info/spec',0,0,'',0,1051,0),(175,67,'Factories','Factories','Factories','/moder/factory',0,0,'',0,29,0),(176,175,'Add','Add','Add','/moder/factory/add',0,0,'',0,1,0),(177,175,'%FACTORY_NAME%','%FACTORY_NAME%','%FACTORY_NAME%','',0,0,'',0,3,0),(178,78,'Factory selection','Factory selection','Factory selection','',0,0,'',0,9,0),(180,1,'Factories','Factories','Factories','/factory',0,0,'',0,1052,0),(181,117,'%FACTORY_NAME%','%FACTORY_NAME%','%FACTORY_NAME%','/factory/factory/id/%FACTORY_ID%',0,0,'',0,2,0),(182,181,'Vehicles','Vehicles','Vehicles','/factory/factory-cars/id/%FACTORY_ID%',0,0,'',0,1,0),(183,28,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/twins/group%TWINS_GROUP_ID%/pictures/%PICTURE_ID%',0,0,'',0,1,0),(184,162,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/category/%CATEGORY_CATNAME%/pictures/%PICTURE_ID%',0,0,'',0,1,0),(185,23,'%CAR_NAME%','%CAR_NAME%','%CAR_NAME%','/category/%CATEGORY_CATNAME%/%CAR_ID%',0,0,'',0,3,0),(186,185,'Pictures','Pictures','Pictures','/category/%CATEGORY_CATNAME%/%CAR_ID%/pictures',0,0,'',0,1,0),(187,186,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/category/%CATEGORY_CATNAME%/%CAR_ID%/pictures/%PICTURE_ID%',0,0,'',0,1,0),(188,48,'Conflicts','Conflicts','Conflicts','/account/specs-conflics',0,1,'',0,29,0),(189,102,'Low weight','Low weight','Low weight','',0,0,'',0,17,0),(190,40,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/%BRAND_CATNAME%/mixed/%PICTURE_ID%',0,0,'',0,1,0),(191,41,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/%BRAND_CATNAME%/other/%PICTURE_ID%',0,0,'',0,1,0),(192,39,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/%BRAND_CATNAME%/logotypes/%PICTURE_ID%',0,0,'',0,1,0),(193,66,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/%BRAND_CATNAME%/%DESIGN_PROJECT_CATNAME%/%PICTURE_ID%',0,0,'',0,1,0),(194,34,'%PICTURE_NAME%','%PICTURE_NAME%','%PICTURE_NAME%','/%BRAND_CATNAME%/%CAR_CATNAME%/pictures/%PICTURE_ID%',0,0,'',0,1,0),(195,168,'%ENGINE_NAME% pictures','%ENGINE_NAME% pictures','%ENGINE_NAME% pictures','',0,0,'',0,3,0),(196,1,'Donate','Donate','Donate','/donate',0,0,'',0,1061,0),(197,1,'Text history','Text history','Text history','/info/text',0,0,'',0,1064,0),(198,48,'Contacts','Contacts','Contacts','/account/contacts',0,1,'',0,33,0),(201,1,'Mascots','Mascots','Mascots','/mascots',0,0,'',0,1065,1),(202,67,'Perspectives','Perspectives','Perspectives','/moder/perspectives',0,0,'',0,30,1),(203,67,'Users','Users','Users','/moder/users',0,1,'',0,31,1),(204,1,'Telegram','Telegram','Telegram','/telegram',0,0,'',0,1066,1),(205,62,'User\'s comments','User\'s comments','Comments','/users/%USER_IDENTITY%/comments',0,0,'',0,2,1);
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;

--
-- Table structure for table `personal_messages`
--

DROP TABLE IF EXISTS `personal_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_user_id` int(10) unsigned DEFAULT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `contents` mediumtext NOT NULL,
  `add_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=1031214 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=281 COMMENT='InnoDB free: 124928 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_messages`
--

/*!40000 ALTER TABLE `personal_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_messages` ENABLE KEYS */;

--
-- Table structure for table `perspective_language`
--

DROP TABLE IF EXISTS `perspective_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perspective_language` (
  `perspective_id` int(11) unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`perspective_id`,`language`),
  CONSTRAINT `perspective_language_ibfk_1` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perspective_language`
--

/*!40000 ALTER TABLE `perspective_language` DISABLE KEYS */;
/*!40000 ALTER TABLE `perspective_language` ENABLE KEYS */;

--
-- Table structure for table `perspectives`
--

DROP TABLE IF EXISTS `perspectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perspectives` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_2` (`position`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perspectives`
--

/*!40000 ALTER TABLE `perspectives` DISABLE KEYS */;
INSERT INTO `perspectives` (`id`, `name`, `position`) VALUES
(1, 'perspective/front', 1),
(2, 'perspective/back', 9),
(3, 'perspective/left', 5),
(4, 'perspective/right', 7),
(5, 'perspective/interior', 14),
(6, 'perspective/front-panel', 11),
(7, 'perspective/3/4-left', 3),
(8, 'perspective/3/4-right', 4),
(9, 'perspective/cutaway', 21),
(10, 'perspective/front-strict', 2),
(11, 'perspective/left-strict', 6),
(12, 'perspective/right-strict', 8),
(13, 'perspective/back-strict', 10),
(14, 'perspective/n/a', 50),
(15, 'perspective/label', 17),
(16, 'perspective/upper', 19),
(17, 'perspective/under-the-hood', 16),
(18, 'perspective/upper-strict', 20),
(19, 'perspective/bottom', 18),
(20, 'perspective/dashboard', 12),
(21, 'perspective/boot', 15),
(22, 'perspective/logo', 22),
(23, 'perspective/mascot', 25),
(24, 'perspective/sketch', 26);
/*!40000 ALTER TABLE `perspectives` ENABLE KEYS */;

--
-- Table structure for table `perspectives_groups`
--

DROP TABLE IF EXISTS `perspectives_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perspectives_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `position` tinyint(11) unsigned NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_id` (`page_id`,`position`),
  CONSTRAINT `perspectives_groups_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `perspectives_pages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perspectives_groups`
--

/*!40000 ALTER TABLE `perspectives_groups` DISABLE KEYS */;
INSERT INTO `perspectives_groups` (`id`, `page_id`, `position`, `name`) VALUES
(1, 1, 1, 'Спереди'),
(2, 1, 2, 'Сзади'),
(3, 1, 3, 'Салон'),
(4, 2, 1, 'спереди'),
(5, 2, 2, 'сбоку'),
(6, 2, 3, 'сзади'),
(7, 2, 4, 'салон'),
(8, 3, 1, 'спереди'),
(9, 3, 2, 'сбоку'),
(10, 3, 3, 'сзади'),
(11, 3, 4, 'салон'),
(12, 5, 1, 'спереди'),
(14, 5, 2, 'сбоку'),
(15, 5, 4, 'под капотом, шильдик, снизу, cutaway'),
(16, 5, 5, 'салон, интерьер'),
(17, 5, 3, 'сзади'),
(18, 4, 1, 'спереди'),
(19, 4, 2, 'сбоку'),
(20, 4, 3, 'сзади'),
(21, 4, 4, 'салон'),
(22, 6, 1, 'спереди'),
(23, 6, 2, 'левый бок'),
(24, 6, 3, 'сзади'),
(25, 6, 4, 'правый бок'),
(26, 6, 5, 'под капотом, шильдик, снизу, cutaway'),
(27, 6, 6, 'салон, интерьер'),
(28, 7, 1, 'Спереди'),
(29, 7, 2, 'Сбоку'),
(30, 7, 3, 'Интерьер / Сзади'),
(31, 8, 1, 'Api');
/*!40000 ALTER TABLE `perspectives_groups` ENABLE KEYS */;

--
-- Table structure for table `perspectives_groups_perspectives`
--

DROP TABLE IF EXISTS `perspectives_groups_perspectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perspectives_groups_perspectives` (
  `group_id` int(11) unsigned NOT NULL,
  `perspective_id` int(11) unsigned NOT NULL,
  `position` int(11) unsigned NOT NULL,
  PRIMARY KEY (`perspective_id`,`group_id`),
  UNIQUE KEY `position` (`position`,`group_id`),
  KEY `FK_perspectives_groups_perspectives_perspectives_groups_id` (`group_id`),
  CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_groups_id` FOREIGN KEY (`group_id`) REFERENCES `perspectives_groups` (`id`),
  CONSTRAINT `FK_perspectives_groups_perspectives_perspectives_id` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perspectives_groups_perspectives`
--

/*!40000 ALTER TABLE `perspectives_groups_perspectives` DISABLE KEYS */;
INSERT INTO `perspectives_groups_perspectives` (`group_id`, `perspective_id`, `position`) VALUES
(1, 7, 1),
(2, 13, 1),
(3, 6, 1),
(4, 10, 1),
(5, 11, 1),
(6, 13, 1),
(7, 6, 1),
(8, 10, 1),
(9, 11, 1),
(10, 13, 1),
(11, 6, 1),
(12, 10, 1),
(14, 11, 1),
(15, 17, 1),
(16, 6, 1),
(17, 13, 1),
(18, 10, 1),
(19, 11, 1),
(20, 13, 1),
(21, 6, 1),
(22, 10, 1),
(23, 11, 1),
(24, 13, 1),
(25, 12, 1),
(26, 17, 1),
(27, 6, 1),
(28, 10, 1),
(29, 11, 1),
(30, 6, 1),
(31, 7, 1),
(1, 8, 2),
(2, 2, 2),
(3, 20, 2),
(4, 1, 2),
(5, 12, 2),
(6, 2, 2),
(7, 20, 2),
(8, 1, 2),
(9, 12, 2),
(10, 2, 2),
(11, 20, 2),
(12, 1, 2),
(14, 12, 2),
(15, 15, 2),
(16, 20, 2),
(17, 2, 2),
(18, 1, 2),
(19, 12, 2),
(20, 2, 2),
(21, 20, 2),
(22, 1, 2),
(23, 3, 2),
(24, 2, 2),
(25, 4, 2),
(26, 15, 2),
(27, 20, 2),
(28, 1, 2),
(29, 12, 2),
(30, 20, 2),
(31, 8, 2),
(1, 1, 3),
(2, 3, 3),
(3, 5, 3),
(4, 7, 3),
(5, 3, 3),
(7, 5, 3),
(8, 7, 3),
(9, 3, 3),
(11, 5, 3),
(12, 7, 3),
(14, 3, 3),
(15, 19, 3),
(16, 5, 3),
(18, 7, 3),
(19, 3, 3),
(21, 5, 3),
(22, 7, 3),
(23, 7, 3),
(25, 8, 3),
(26, 19, 3),
(27, 5, 3),
(28, 7, 3),
(29, 3, 3),
(30, 5, 3),
(31, 1, 3),
(1, 10, 4),
(2, 4, 4),
(3, 15, 4),
(4, 8, 4),
(5, 4, 4),
(7, 15, 4),
(8, 8, 4),
(9, 4, 4),
(11, 15, 4),
(12, 8, 4),
(14, 4, 4),
(15, 9, 4),
(16, 15, 4),
(19, 4, 4),
(21, 15, 4),
(22, 8, 4),
(26, 9, 4),
(27, 15, 4),
(28, 8, 4),
(29, 4, 4),
(30, 15, 4),
(31, 2, 4),
(29, 13, 5),
(30, 13, 5),
(31, 3, 5),
(29, 2, 6),
(30, 2, 6),
(31, 4, 6),
(18, 8, 7),
(31, 11, 7),
(31, 12, 8);
/*!40000 ALTER TABLE `perspectives_groups_perspectives` ENABLE KEYS */;

--
-- Table structure for table `perspectives_pages`
--

DROP TABLE IF EXISTS `perspectives_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perspectives_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perspectives_pages`
--

/*!40000 ALTER TABLE `perspectives_pages` DISABLE KEYS */;
INSERT INTO `perspectives_pages` (`id`, `name`) VALUES
(1, ''),
(2, ''),
(3, ''),
(4, ''),
(5, ''),
(6, ''),
(7, ''),
(8, '');
/*!40000 ALTER TABLE `perspectives_pages` ENABLE KEYS */;

--
-- Table structure for table `picture_item`
--

DROP TABLE IF EXISTS `picture_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `picture_item` (
  `picture_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `perspective_id` int(10) unsigned DEFAULT NULL,
  `crop_left` smallint(5) unsigned DEFAULT NULL,
  `crop_top` smallint(5) unsigned DEFAULT NULL,
  `crop_width` smallint(5) unsigned DEFAULT NULL,
  `crop_height` smallint(5) unsigned DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`picture_id`,`item_id`),
  KEY `item_id` (`item_id`),
  KEY `perspective_id` (`perspective_id`),
  CONSTRAINT `picture_item_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`),
  CONSTRAINT `picture_item_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `picture_item_ibfk_3` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `picture_item`
--

/*!40000 ALTER TABLE `picture_item` DISABLE KEYS */;
INSERT INTO `picture_item` VALUES (1,1,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:36:48');
/*!40000 ALTER TABLE `picture_item` ENABLE KEYS */;

--
-- Table structure for table `picture_view`
--

DROP TABLE IF EXISTS `picture_view`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `picture_view` (
  `picture_id` int(10) unsigned NOT NULL,
  `views` int(10) unsigned NOT NULL,
  PRIMARY KEY (`picture_id`),
  CONSTRAINT `picture_view_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `picture_view`
--

/*!40000 ALTER TABLE `picture_view` DISABLE KEYS */;
/*!40000 ALTER TABLE `picture_view` ENABLE KEYS */;

--
-- Table structure for table `picture_votes_ips`
--

DROP TABLE IF EXISTS `picture_votes_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `picture_votes_ips` (
  `picture_id` int(10) unsigned NOT NULL,
  `ip` varchar(15) NOT NULL,
  `vote_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `vote` int(11) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`picture_id`,`ip`),
  CONSTRAINT `picture_votes_ips_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `picture_votes_ips`
--

/*!40000 ALTER TABLE `picture_votes_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `picture_votes_ips` ENABLE KEYS */;

--
-- Table structure for table `pictures`
--

DROP TABLE IF EXISTS `pictures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pictures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `width` smallint(5) unsigned NOT NULL DEFAULT '0',
  `height` smallint(5) unsigned NOT NULL DEFAULT '0',
  `filesize` int(8) unsigned NOT NULL DEFAULT '0',
  `owner_id` int(10) unsigned DEFAULT '0',
  `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(255) NOT NULL DEFAULT '',
  `crc` int(11) DEFAULT NULL,
  `status` enum('new','accepted','removing','removed','inbox') NOT NULL,
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `removing_date` date DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `engine_id` int(10) unsigned DEFAULT NULL,
  `change_status_user_id` int(10) unsigned DEFAULT NULL,
  `crop_left` smallint(6) unsigned DEFAULT NULL,
  `crop_top` smallint(11) unsigned DEFAULT NULL,
  `crop_width` smallint(6) unsigned DEFAULT NULL,
  `crop_height` smallint(11) unsigned DEFAULT NULL,
  `accept_datetime` timestamp NULL DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `source_id` int(10) unsigned DEFAULT NULL,
  `copyrights` text,
  `identity` varchar(10) DEFAULT NULL,
  `replace_picture_id` int(10) unsigned DEFAULT NULL,
  `image_id` int(10) unsigned DEFAULT NULL,
  `factory_id` int(10) unsigned DEFAULT NULL,
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
  KEY `car_id` (`type`,`status`),
  KEY `brandIndex` (`brand_id`,`type`,`status`),
  KEY `owner_id` (`owner_id`,`status`),
  KEY `accept_datetime` (`status`,`accept_datetime`),
  KEY `pictures_fk5` (`type`),
  KEY `pictures_fk6` (`replace_picture_id`),
  KEY `factory_id` (`factory_id`),
  KEY `width` (`width`,`height`,`add_date`,`id`),
  KEY `copyrights_text_id` (`copyrights_text_id`),
  CONSTRAINT `pictures_fk` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pictures_fk2` FOREIGN KEY (`engine_id`) REFERENCES `engines` (`id`),
  CONSTRAINT `pictures_fk4` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `pictures_fk5` FOREIGN KEY (`type`) REFERENCES `pictures_types` (`id`),
  CONSTRAINT `pictures_fk6` FOREIGN KEY (`replace_picture_id`) REFERENCES `pictures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pictures_fk7` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `pictures_ibfk_1` FOREIGN KEY (`factory_id`) REFERENCES `factory` (`id`),
  CONSTRAINT `pictures_ibfk_2` FOREIGN KEY (`copyrights_text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=917309 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`owner_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pictures`
--

/*!40000 ALTER TABLE `pictures` DISABLE KEYS */;
INSERT INTO `pictures` VALUES 
(1,1600,1200,0,1,'2016-11-25 18:31:50','',NULL,'accepted',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:31:50',NULL,NULL,NULL,NULL,NULL,1,NULL,'\0\0',NULL,NULL),
(2,1600,1200,0,1,'2016-11-25 18:31:50','',NULL,'accepted',0,NULL,1,   NULL,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:31:50',NULL,NULL,NULL,NULL,NULL,33,NULL,'\0\0',NULL,NULL),
(3,1600,1200,0,1,'2016-11-25 18:31:50','',NULL,'accepted',3,NULL,1,   NULL,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:31:50',NULL,NULL,NULL,NULL,NULL,35,NULL,'\0\0',NULL,NULL),
(4,1600,1200,0,1,'2016-11-25 18:31:50','',NULL,'accepted',2,NULL,1,   NULL,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:31:50',NULL,NULL,NULL,NULL,NULL,37,NULL,'\0\0',NULL,NULL),
(5,1600,1200,0,1,'2016-11-25 18:31:50','',NULL,'inbox',   2,NULL,1,   NULL,NULL,NULL,NULL,NULL,NULL,'2016-11-25 18:31:50',NULL,NULL,NULL,NULL,NULL,38,NULL,'\0\0',NULL,NULL);
/*!40000 ALTER TABLE `pictures` ENABLE KEYS */;

--
-- Table structure for table `pictures_moder_votes`
--

DROP TABLE IF EXISTS `pictures_moder_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pictures_moder_votes` (
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `picture_id` int(10) unsigned NOT NULL DEFAULT '0',
  `day_date` datetime NOT NULL,
  `reason` varchar(50) NOT NULL DEFAULT '',
  `vote` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`picture_id`),
  KEY `picture_id` (`picture_id`),
  CONSTRAINT `picture_id_ref` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pictures_moder_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB; (`picture_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pictures_moder_votes`
--

/*!40000 ALTER TABLE `pictures_moder_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `pictures_moder_votes` ENABLE KEYS */;

--
-- Table structure for table `pictures_types`
--

DROP TABLE IF EXISTS `pictures_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pictures_types` (
  `id` tinyint(3) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pictures_types`
--

/*!40000 ALTER TABLE `pictures_types` DISABLE KEYS */;
INSERT INTO `pictures_types` VALUES (1,'Автомобиль'),(4,'Двигатель'),(7,'Завод'),(6,'Интерьер'),(2,'Логотип бренда'),(5,'Модель'),(0,'Несортировано'),(3,'Разное');
/*!40000 ALTER TABLE `pictures_types` ENABLE KEYS */;

--
-- Table structure for table `pma__bookmark`
--

DROP TABLE IF EXISTS `pma__bookmark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__bookmark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dbase` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `user` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `query` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__bookmark`
--

/*!40000 ALTER TABLE `pma__bookmark` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__bookmark` ENABLE KEYS */;

--
-- Table structure for table `pma__central_columns`
--

DROP TABLE IF EXISTS `pma__central_columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__central_columns` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__central_columns`
--

/*!40000 ALTER TABLE `pma__central_columns` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__central_columns` ENABLE KEYS */;

--
-- Table structure for table `pma__column_info`
--

DROP TABLE IF EXISTS `pma__column_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__column_info` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__column_info`
--

/*!40000 ALTER TABLE `pma__column_info` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__column_info` ENABLE KEYS */;

--
-- Table structure for table `pma__designer_settings`
--

DROP TABLE IF EXISTS `pma__designer_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `settings_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__designer_settings`
--

/*!40000 ALTER TABLE `pma__designer_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__designer_settings` ENABLE KEYS */;

--
-- Table structure for table `pma__export_templates`
--

DROP TABLE IF EXISTS `pma__export_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__export_templates` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `export_type` varchar(10) COLLATE utf8_bin NOT NULL,
  `template_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `template_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__export_templates`
--

/*!40000 ALTER TABLE `pma__export_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__export_templates` ENABLE KEYS */;

--
-- Table structure for table `pma__favorite`
--

DROP TABLE IF EXISTS `pma__favorite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__favorite` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `tables` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__favorite`
--

/*!40000 ALTER TABLE `pma__favorite` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__favorite` ENABLE KEYS */;

--
-- Table structure for table `pma__history`
--

DROP TABLE IF EXISTS `pma__history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sqlquery` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`,`db`,`table`,`timevalue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__history`
--

/*!40000 ALTER TABLE `pma__history` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__history` ENABLE KEYS */;

--
-- Table structure for table `pma__navigationhiding`
--

DROP TABLE IF EXISTS `pma__navigationhiding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `item_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `item_type` varchar(64) COLLATE utf8_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__navigationhiding`
--

/*!40000 ALTER TABLE `pma__navigationhiding` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__navigationhiding` ENABLE KEYS */;

--
-- Table structure for table `pma__pdf_pages`
--

DROP TABLE IF EXISTS `pma__pdf_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `page_nr` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_descr` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`page_nr`),
  KEY `db_name` (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__pdf_pages`
--

/*!40000 ALTER TABLE `pma__pdf_pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__pdf_pages` ENABLE KEYS */;

--
-- Table structure for table `pma__recent`
--

DROP TABLE IF EXISTS `pma__recent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__recent` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `tables` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__recent`
--

/*!40000 ALTER TABLE `pma__recent` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__recent` ENABLE KEYS */;

--
-- Table structure for table `pma__relation`
--

DROP TABLE IF EXISTS `pma__relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__relation` (
  `master_db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `master_table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `master_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_db` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_table` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `foreign_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  KEY `foreign_field` (`foreign_db`,`foreign_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__relation`
--

/*!40000 ALTER TABLE `pma__relation` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__relation` ENABLE KEYS */;

--
-- Table structure for table `pma__savedsearches`
--

DROP TABLE IF EXISTS `pma__savedsearches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__savedsearches` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `search_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `search_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__savedsearches`
--

/*!40000 ALTER TABLE `pma__savedsearches` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__savedsearches` ENABLE KEYS */;

--
-- Table structure for table `pma__table_coords`
--

DROP TABLE IF EXISTS `pma__table_coords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT '0',
  `x` float unsigned NOT NULL DEFAULT '0',
  `y` float unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__table_coords`
--

/*!40000 ALTER TABLE `pma__table_coords` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__table_coords` ENABLE KEYS */;

--
-- Table structure for table `pma__table_info`
--

DROP TABLE IF EXISTS `pma__table_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  `display_field` varchar(64) COLLATE utf8_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__table_info`
--

/*!40000 ALTER TABLE `pma__table_info` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__table_info` ENABLE KEYS */;

--
-- Table structure for table `pma__table_uiprefs`
--

DROP TABLE IF EXISTS `pma__table_uiprefs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `prefs` text COLLATE utf8_bin NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`,`db_name`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__table_uiprefs`
--

/*!40000 ALTER TABLE `pma__table_uiprefs` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__table_uiprefs` ENABLE KEYS */;

--
-- Table structure for table `pma__tracking`
--

DROP TABLE IF EXISTS `pma__tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `table_name` varchar(64) COLLATE utf8_bin NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text COLLATE utf8_bin NOT NULL,
  `schema_sql` text COLLATE utf8_bin,
  `data_sql` longtext COLLATE utf8_bin,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') COLLATE utf8_bin DEFAULT NULL,
  `tracking_active` int(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`db_name`,`table_name`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__tracking`
--

/*!40000 ALTER TABLE `pma__tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__tracking` ENABLE KEYS */;

--
-- Table structure for table `pma__userconfig`
--

DROP TABLE IF EXISTS `pma__userconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__userconfig` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `config_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__userconfig`
--

/*!40000 ALTER TABLE `pma__userconfig` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__userconfig` ENABLE KEYS */;

--
-- Table structure for table `pma__usergroups`
--

DROP TABLE IF EXISTS `pma__usergroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) COLLATE utf8_bin NOT NULL,
  `tab` varchar(64) COLLATE utf8_bin NOT NULL,
  `allowed` enum('Y','N') COLLATE utf8_bin NOT NULL DEFAULT 'N',
  PRIMARY KEY (`usergroup`,`tab`,`allowed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__usergroups`
--

/*!40000 ALTER TABLE `pma__usergroups` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__usergroups` ENABLE KEYS */;

--
-- Table structure for table `pma__users`
--

DROP TABLE IF EXISTS `pma__users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pma__users` (
  `username` varchar(64) COLLATE utf8_bin NOT NULL,
  `usergroup` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`,`usergroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pma__users`
--

/*!40000 ALTER TABLE `pma__users` DISABLE KEYS */;
/*!40000 ALTER TABLE `pma__users` ENABLE KEYS */;

--
-- Table structure for table `referer`
--

DROP TABLE IF EXISTS `referer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referer` (
  `host` varchar(255) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `count` int(11) unsigned NOT NULL DEFAULT '0',
  `last_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `accept` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`url`),
  KEY `UK_referer_host` (`host`,`last_date`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referer`
--

/*!40000 ALTER TABLE `referer` DISABLE KEYS */;
/*!40000 ALTER TABLE `referer` ENABLE KEYS */;

--
-- Table structure for table `referer_blacklist`
--

DROP TABLE IF EXISTS `referer_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referer_blacklist` (
  `host` varchar(255) NOT NULL,
  `hard` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referer_blacklist`
--

/*!40000 ALTER TABLE `referer_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `referer_blacklist` ENABLE KEYS */;

--
-- Table structure for table `referer_whitelist`
--

DROP TABLE IF EXISTS `referer_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referer_whitelist` (
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referer_whitelist`
--

/*!40000 ALTER TABLE `referer_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `referer_whitelist` ENABLE KEYS */;

--
-- Table structure for table `referrers`
--

DROP TABLE IF EXISTS `referrers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referrers` (
  `url` varchar(255) NOT NULL DEFAULT '',
  `day_date` date NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`url`,`day_date`),
  KEY `day_date` (`day_date`),
  KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrers`
--

/*!40000 ALTER TABLE `referrers` DISABLE KEYS */;
/*!40000 ALTER TABLE `referrers` ENABLE KEYS */;

--
-- Table structure for table `session`
--

DROP TABLE IF EXISTS `session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `session` (
  `id` char(32) NOT NULL DEFAULT '',
  `modified` int(11) DEFAULT NULL,
  `lifetime` int(11) DEFAULT NULL,
  `data` text,
  `user_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_session_modified` (`modified`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `session_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=84;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `session`
--

/*!40000 ALTER TABLE `session` DISABLE KEYS */;
/*!40000 ALTER TABLE `session` ENABLE KEYS */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;

--
-- Table structure for table `sources`
--

DROP TABLE IF EXISTS `sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sources`
--

/*!40000 ALTER TABLE `sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `sources` ENABLE KEYS */;

--
-- Table structure for table `spec`
--

DROP TABLE IF EXISTS `spec`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spec` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `short_name` (`short_name`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `spec_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `spec` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spec`
--

/*!40000 ALTER TABLE `spec` DISABLE KEYS */;
/*!40000 ALTER TABLE `spec` ENABLE KEYS */;

--
-- Table structure for table `telegram_brand`
--

DROP TABLE IF EXISTS `telegram_brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `telegram_brand` (
  `brand_id` int(10) unsigned NOT NULL,
  `chat_id` int(11) NOT NULL,
  `inbox` tinyint(1) NOT NULL DEFAULT '0',
  `new` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`brand_id`,`chat_id`),
  CONSTRAINT `telegram_brand_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_brand`
--

/*!40000 ALTER TABLE `telegram_brand` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_brand` ENABLE KEYS */;

--
-- Table structure for table `telegram_chat`
--

DROP TABLE IF EXISTS `telegram_chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `telegram_chat` (
  `chat_id` int(11) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `token` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`chat_id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  CONSTRAINT `telegram_chat_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_chat`
--

/*!40000 ALTER TABLE `telegram_chat` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_chat` ENABLE KEYS */;

--
-- Table structure for table `textstorage_revision`
--

DROP TABLE IF EXISTS `textstorage_revision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `textstorage_revision` (
  `text_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `text` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`text_id`,`revision`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `textstorage_revision_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`),
  CONSTRAINT `textstorage_revision_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `textstorage_revision`
--

/*!40000 ALTER TABLE `textstorage_revision` DISABLE KEYS */;
/*!40000 ALTER TABLE `textstorage_revision` ENABLE KEYS */;

--
-- Table structure for table `textstorage_text`
--

DROP TABLE IF EXISTS `textstorage_text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `textstorage_text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `revision` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31128 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `textstorage_text`
--

/*!40000 ALTER TABLE `textstorage_text` DISABLE KEYS */;
/*!40000 ALTER TABLE `textstorage_text` ENABLE KEYS */;

--
-- Table structure for table `twins_groups`
--

DROP TABLE IF EXISTS `twins_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twins_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `add_datetime` timestamp NULL DEFAULT NULL,
  `text_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `text_id` (`text_id`),
  CONSTRAINT `twins_groups_ibfk_1` FOREIGN KEY (`text_id`) REFERENCES `textstorage_text` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2020 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `twins_groups`
--

/*!40000 ALTER TABLE `twins_groups` DISABLE KEYS */;
INSERT INTO `twins_groups` VALUES (1,'test twins','2016-11-25 18:31:51',NULL);
/*!40000 ALTER TABLE `twins_groups` ENABLE KEYS */;

--
-- Table structure for table `twins_groups_cars`
--

DROP TABLE IF EXISTS `twins_groups_cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twins_groups_cars` (
  `twins_group_id` int(10) unsigned NOT NULL,
  `car_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`twins_group_id`,`car_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `twins_groups_cars_fk` FOREIGN KEY (`twins_group_id`) REFERENCES `twins_groups` (`id`),
  CONSTRAINT `twins_groups_cars_fk1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 122880 kB; (`twins_group_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `twins_groups_cars`
--

/*!40000 ALTER TABLE `twins_groups_cars` DISABLE KEYS */;
INSERT INTO `twins_groups_cars` VALUES (1,1);
/*!40000 ALTER TABLE `twins_groups_cars` ENABLE KEYS */;

--
-- Table structure for table `user_account`
--

DROP TABLE IF EXISTS `user_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2311 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_account`
--

/*!40000 ALTER TABLE `user_account` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_account` ENABLE KEYS */;

--
-- Table structure for table `user_authority`
--

DROP TABLE IF EXISTS `user_authority`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_authority` (
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `authority` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`from_user_id`,`to_user_id`),
  KEY `to_user_id` (`to_user_id`),
  CONSTRAINT `user_authority_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_authority_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_authority`
--

/*!40000 ALTER TABLE `user_authority` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_authority` ENABLE KEYS */;

--
-- Table structure for table `user_item_subscribe`
--

DROP TABLE IF EXISTS `user_item_subscribe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_item_subscribe` (
  `user_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`item_id`),
  KEY `item_id_index` (`item_id`),
  CONSTRAINT `user_item_subscribe_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_item_subscribe_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_item_subscribe`
--

/*!40000 ALTER TABLE `user_item_subscribe` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_item_subscribe` ENABLE KEYS */;

--
-- Table structure for table `user_password_remind`
--

DROP TABLE IF EXISTS `user_password_remind`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_password_remind` (
  `hash` varchar(255) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`),
  KEY `FK_user_password_remind_users_id` (`user_id`),
  CONSTRAINT `FK_user_password_remind_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_password_remind`
--

/*!40000 ALTER TABLE `user_password_remind` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_password_remind` ENABLE KEYS */;

--
-- Table structure for table `user_remember`
--

DROP TABLE IF EXISTS `user_remember`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_remember` (
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_remember_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_remember`
--

/*!40000 ALTER TABLE `user_remember` DISABLE KEYS */;
INSERT INTO `user_remember` VALUES (3,'admin-token','2016-11-25 18:31:51');
/*!40000 ALTER TABLE `user_remember` ENABLE KEYS */;

--
-- Table structure for table `user_renames`
--

DROP TABLE IF EXISTS `user_renames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_renames` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `old_name` varchar(255) NOT NULL,
  `new_name` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  CONSTRAINT `user_renames_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3212 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_renames`
--

/*!40000 ALTER TABLE `user_renames` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_renames` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `e_mail_checked` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `hide_e_mail` int(11) DEFAULT NULL,
  `authority` float DEFAULT '0',
  `pictures_ratio` double unsigned DEFAULT NULL,
  `email_to_check` varchar(50) DEFAULT NULL,
  `email_check_code` varchar(32) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=25161 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=227 COMMENT='InnoDB free: 124928 kB; (`group_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'test','26cc2d23a03a8f07ed1e3d000a244636','test@example.com','tester',NULL,'2016-11-25 18:31:52',0,'','','',0,0,0,0,NULL,0,NULL,NULL,NULL,'user',NULL,NULL,10,NULL,0,NULL,NULL,1,0,'Europe/Moscow',0,0,NULL,NULL,0,'\0\0','ru'),(2,NULL,'',NULL,'tester2',NULL,'2016-11-25 18:31:52',0,'','','',0,0,0,0,NULL,0,NULL,NULL,NULL,'user',NULL,NULL,10,NULL,0,'identity',NULL,1,0,'UTC',0,0,NULL,NULL,0,'\0\0','ru'),(3,NULL,'',NULL,'admin',NULL,'2016-11-25 18:31:52',0,'','','',0,0,0,0,NULL,0,NULL,NULL,NULL,'admin',NULL,NULL,10,NULL,0,'admin',NULL,1,0,'UTC',0,0,NULL,NULL,0,'\0\0','ru');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Table structure for table `vehicle_vehicle_type`
--

DROP TABLE IF EXISTS `vehicle_vehicle_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_vehicle_type` (
  `vehicle_id` int(10) unsigned NOT NULL,
  `vehicle_type_id` int(10) unsigned NOT NULL,
  `inherited` tinyint(1) NOT NULL,
  PRIMARY KEY (`vehicle_id`,`vehicle_type_id`),
  KEY `vehicle_type_id` (`vehicle_type_id`),
  CONSTRAINT `vehicle_vehicle_type_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `vehicle_vehicle_type_ibfk_2` FOREIGN KEY (`vehicle_type_id`) REFERENCES `car_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_vehicle_type`
--

/*!40000 ALTER TABLE `vehicle_vehicle_type` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_vehicle_type` ENABLE KEYS */;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votes` (
  `picture_id` int(10) unsigned NOT NULL DEFAULT '0',
  `day_date` date NOT NULL,
  `count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `summary` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`picture_id`,`day_date`),
  CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `pictures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 124928 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;

--
-- Table structure for table `voting`
--

DROP TABLE IF EXISTS `voting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `multivariant` tinyint(1) NOT NULL DEFAULT '0',
  `begin_date` date NOT NULL,
  `end_date` date NOT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT '0',
  `text` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COMMENT='InnoDB free: 125952 kB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voting`
--

/*!40000 ALTER TABLE `voting` DISABLE KEYS */;
/*!40000 ALTER TABLE `voting` ENABLE KEYS */;

--
-- Table structure for table `voting_variant`
--

DROP TABLE IF EXISTS `voting_variant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=197 COMMENT='InnoDB free: 124928 kB; (`voting_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voting_variant`
--

/*!40000 ALTER TABLE `voting_variant` DISABLE KEYS */;
/*!40000 ALTER TABLE `voting_variant` ENABLE KEYS */;

--
-- Table structure for table `voting_variant_vote`
--

DROP TABLE IF EXISTS `voting_variant_vote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voting_variant_vote` (
  `voting_variant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`voting_variant_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `voting_variant_id` (`voting_variant_id`),
  CONSTRAINT `voting_variant_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `voting_variant_votes_ibfk_2` FOREIGN KEY (`voting_variant_id`) REFERENCES `voting_variant` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=30 COMMENT='InnoDB free: 124928 kB; (`user_id`)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voting_variant_vote`
--

/*!40000 ALTER TABLE `voting_variant_vote` DISABLE KEYS */;
/*!40000 ALTER TABLE `voting_variant_vote` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-11-25 21:52:15