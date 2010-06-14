# ************************************************************
# Sequel Pro SQL dump
# Version 2282
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 127.0.0.1 (MySQL 5.5.0-m2)
# Database: wolk
# Generation Time: 2010-06-14 22:57:15 +0200
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table api_keys
# ------------------------------------------------------------

DROP TABLE IF EXISTS `api_keys`;

CREATE TABLE `api_keys` (
  `user_id` int(11) DEFAULT NULL,
  `api_key` char(32) NOT NULL,
  `added_on` datetime NOT NULL,
  `revoked_on` datetime DEFAULT NULL,
  PRIMARY KEY (`api_key`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table events
# ------------------------------------------------------------

DROP TABLE IF EXISTS `events`;

CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `origin_id` int(11) NOT NULL,
  `api_key` char(32) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `created_on` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `origin_id` (`origin_id`),
  KEY `api_key` (`api_key`),
  CONSTRAINT `events_ibfk_3` FOREIGN KEY (`api_key`) REFERENCES `api_keys` (`api_key`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`origin_id`) REFERENCES `origins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;



# Dump of table origins
# ------------------------------------------------------------

DROP TABLE IF EXISTS `origins`;

CREATE TABLE `origins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `origin` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_origin` (`origin`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;



# Dump of table pairs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `pairs`;

CREATE TABLE `pairs` (
  `pair_key` varchar(255) NOT NULL,
  `pair_value` varchar(255) DEFAULT NULL,
  `last_modified_on` datetime NOT NULL,
  `origin_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pair_key`,`origin_id`,`user_id`),
  KEY `remote_read_index` (`last_modified_on`,`origin_id`),
  KEY `origin_id` (`origin_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pairs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pairs_ibfk_1` FOREIGN KEY (`origin_id`) REFERENCES `origins` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(255) NOT NULL,
  `added_on` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_openid` (`openid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
