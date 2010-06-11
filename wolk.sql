# ************************************************************
# Sequel Pro SQL dump
# Version 2282
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 127.0.0.1 (MySQL 5.5.0-m2)
# Database: wolk
# Generation Time: 2010-06-11 19:20:46 +0200
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table origins
# ------------------------------------------------------------

DROP TABLE IF EXISTS `origins`;

CREATE TABLE `origins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `origin` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_origin` (`origin`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

LOCK TABLES `origins` WRITE;
/*!40000 ALTER TABLE `origins` DISABLE KEYS */;

INSERT INTO `origins` (`id`,`origin`)
VALUES
	(2,'http://wolk.ikhoefgeen.nl'),
	(1,'http://www.ikhoefgeen.nl');

/*!40000 ALTER TABLE `origins` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table pairs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `pairs`;

CREATE TABLE `pairs` (
  `pair_key` varchar(255) NOT NULL,
  `pair_value` varchar(255) DEFAULT NULL,
  `mtime` datetime NOT NULL,
  `origin_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pair_key`,`origin_id`,`user_id`),
  KEY `remote_read_index` (`mtime`,`origin_id`),
  KEY `origin_id` (`origin_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pairs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pairs_ibfk_1` FOREIGN KEY (`origin_id`) REFERENCES `origins` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `pairs` WRITE;
/*!40000 ALTER TABLE `pairs` DISABLE KEYS */;

INSERT INTO `pairs` (`pair_key`,`pair_value`,`mtime`,`origin_id`,`user_id`)
VALUES
	('test.key1','testwaarde Ã©Ã©n','2010-06-11 18:31:49',2,1),
	('test.key2','testwaarde due','2010-06-11 18:31:49',2,1);

/*!40000 ALTER TABLE `pairs` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `api_key` char(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_email` (`email`),
  UNIQUE KEY `u_api_key` (`api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;

INSERT INTO `users` (`id`,`email`,`api_key`)
VALUES
	(1,'jelmer@ikhoefgeen.nl','c012fe520c1d85db118a7f415d8c9db8');

/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;



/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
