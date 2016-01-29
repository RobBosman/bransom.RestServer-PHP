# --------------------------------------------------------
# Host:                         127.0.0.1
# Server version:               5.5.8-log - MySQL Community Server (GPL)
# Server OS:                    Win32
# HeidiSQL version:             6.0.0.3945
# Date/time:                    2012-01-17 13:44:04
# --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping database structure for bransom_test
DROP DATABASE IF EXISTS `bransom_test`;
CREATE DATABASE IF NOT EXISTS `bransom_test` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `bransom_test`;


# Dumping structure for table bransom_test.a_nost_fk
DROP TABLE IF EXISTS `a_nost_fk`;
CREATE TABLE IF NOT EXISTS `a_nost_fk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int(10) unsigned NOT NULL,
  `id_b_nost_nofk` int(10) unsigned DEFAULT NULL,
  `id_b_st_nofk` int(10) unsigned DEFAULT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_a_nost_fk_b_nost_nofk` (`id_b_nost_nofk`),
  KEY `FK_a_nost_fk_b_st_nofk` (`id_b_st_nofk`),
  KEY `FK_a_nost_fk_account` (`id_account`),
  CONSTRAINT `FK_a_nost_fk_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`),
  CONSTRAINT `FK_a_nost_fk_b_nost_nofk` FOREIGN KEY (`id_b_nost_nofk`) REFERENCES `b_nost_nofk` (`id`),
  CONSTRAINT `FK_a_nost_fk_b_st_nofk` FOREIGN KEY (`id_b_st_nofk`) REFERENCES `b_st_nofk` (`id_object`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test.a_nost_fk: ~0 rows (approximately)
DELETE FROM `a_nost_fk`;
/*!40000 ALTER TABLE `a_nost_fk` DISABLE KEYS */;
/*!40000 ALTER TABLE `a_nost_fk` ENABLE KEYS */;


# Dumping structure for table bransom_test.a_nost_nofk
DROP TABLE IF EXISTS `a_nost_nofk`;
CREATE TABLE IF NOT EXISTS `a_nost_nofk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int(10) unsigned NOT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_a_nost_nofk_account` (`id_account`),
  CONSTRAINT `FK_a_nost_nofk_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.a_nost_nofk: ~0 rows (approximately)
DELETE FROM `a_nost_nofk`;
/*!40000 ALTER TABLE `a_nost_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `a_nost_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.a_st_fk
DROP TABLE IF EXISTS `a_st_fk`;
CREATE TABLE IF NOT EXISTS `a_st_fk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `id_account` int(10) unsigned NOT NULL,
  `id_b_nost_nofk` int(10) unsigned DEFAULT NULL,
  `id_b_st_nofk` int(10) unsigned DEFAULT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_a_st_fk_state` (`id_state`),
  KEY `FK_a_st_fk_account` (`id_account`),
  KEY `FK_a_st_fk_b_nost_nokf` (`id_b_nost_nofk`),
  KEY `FK_a_st_fk_b_st_nofk` (`id_b_st_nofk`),
  CONSTRAINT `FK_a_st_fk_b_nost_nokf` FOREIGN KEY (`id_b_nost_nofk`) REFERENCES `b_nost_nofk` (`id`),
  CONSTRAINT `FK_a_st_fk_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`),
  CONSTRAINT `FK_a_st_fk_b_st_nofk` FOREIGN KEY (`id_b_st_nofk`) REFERENCES `b_st_nofk` (`id_object`),
  CONSTRAINT `FK_a_st_fk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test.a_st_fk: ~0 rows (approximately)
DELETE FROM `a_st_fk`;
/*!40000 ALTER TABLE `a_st_fk` DISABLE KEYS */;
/*!40000 ALTER TABLE `a_st_fk` ENABLE KEYS */;


# Dumping structure for table bransom_test.a_st_nofk
DROP TABLE IF EXISTS `a_st_nofk`;
CREATE TABLE IF NOT EXISTS `a_st_nofk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `id_account` int(10) unsigned NOT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_a_st_nofk_state` (`id_state`),
  KEY `FK_a_st_nofk_account` (`id_account`),
  CONSTRAINT `FK_a_st_nofk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`),
  CONSTRAINT `FK_a_st_nofk_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.a_st_nofk: ~0 rows (approximately)
DELETE FROM `a_st_nofk`;
/*!40000 ALTER TABLE `a_st_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `a_st_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.b_nost_fk
DROP TABLE IF EXISTS `b_nost_fk`;
CREATE TABLE IF NOT EXISTS `b_nost_fk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_a_nost_nofk` int(10) unsigned DEFAULT NULL,
  `id_a_st_nofk` int(10) unsigned DEFAULT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_b_nost_fk_a_nost_nofk` (`id_a_nost_nofk`),
  KEY `FK_b_nost_fk_a_st_nofk` (`id_a_st_nofk`),
  CONSTRAINT `FK_b_nost_fk_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`),
  CONSTRAINT `FK_b_nost_fk_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.b_nost_fk: ~0 rows (approximately)
DELETE FROM `b_nost_fk`;
/*!40000 ALTER TABLE `b_nost_fk` DISABLE KEYS */;
/*!40000 ALTER TABLE `b_nost_fk` ENABLE KEYS */;


# Dumping structure for table bransom_test.b_nost_nofk
DROP TABLE IF EXISTS `b_nost_nofk`;
CREATE TABLE IF NOT EXISTS `b_nost_nofk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test.b_nost_nofk: ~0 rows (approximately)
DELETE FROM `b_nost_nofk`;
/*!40000 ALTER TABLE `b_nost_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `b_nost_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.b_st_fk
DROP TABLE IF EXISTS `b_st_fk`;
CREATE TABLE IF NOT EXISTS `b_st_fk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `id_a_nost_nofk` int(10) unsigned DEFAULT NULL,
  `id_a_st_nofk` int(10) unsigned DEFAULT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_b_st_fk_state` (`id_state`),
  KEY `FK_b_st_fk_a_nost_nofk` (`id_a_nost_nofk`),
  KEY `FK_b_st_fk_a_st_nofk` (`id_a_st_nofk`),
  CONSTRAINT `FK_b_st_fk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`),
  CONSTRAINT `FK_b_st_fk_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`),
  CONSTRAINT `FK_b_st_fk_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.b_st_fk: ~0 rows (approximately)
DELETE FROM `b_st_fk`;
/*!40000 ALTER TABLE `b_st_fk` DISABLE KEYS */;
/*!40000 ALTER TABLE `b_st_fk` ENABLE KEYS */;


# Dumping structure for table bransom_test.b_st_nofk
DROP TABLE IF EXISTS `b_st_nofk`;
CREATE TABLE IF NOT EXISTS `b_st_nofk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_b_st_nofk_state` (`id_state`),
  CONSTRAINT `FK_b_st_nofk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.b_st_nofk: ~0 rows (approximately)
DELETE FROM `b_st_nofk`;
/*!40000 ALTER TABLE `b_st_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `b_st_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.c_nost_nofk
DROP TABLE IF EXISTS `c_nost_nofk`;
CREATE TABLE IF NOT EXISTS `c_nost_nofk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.c_nost_nofk: ~0 rows (approximately)
DELETE FROM `c_nost_nofk`;
/*!40000 ALTER TABLE `c_nost_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `c_nost_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.c_st_nofk
DROP TABLE IF EXISTS `c_st_nofk`;
CREATE TABLE IF NOT EXISTS `c_st_nofk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK1_c_st_nofk_state` (`id_state`),
  CONSTRAINT `FK1_c_st_nofk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.c_st_nofk: ~0 rows (approximately)
DELETE FROM `c_st_nofk`;
/*!40000 ALTER TABLE `c_st_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `c_st_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.d_nost_nofk
DROP TABLE IF EXISTS `d_nost_nofk`;
CREATE TABLE IF NOT EXISTS `d_nost_nofk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.d_nost_nofk: ~0 rows (approximately)
DELETE FROM `d_nost_nofk`;
/*!40000 ALTER TABLE `d_nost_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `d_nost_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.d_st_nofk
DROP TABLE IF EXISTS `d_st_nofk`;
CREATE TABLE IF NOT EXISTS `d_st_nofk` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK1_d_st_nofk_state` (`id_state`),
  CONSTRAINT `FK1_d_st_nofk_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.d_st_nofk: ~0 rows (approximately)
DELETE FROM `d_st_nofk`;
/*!40000 ALTER TABLE `d_st_nofk` DISABLE KEYS */;
/*!40000 ALTER TABLE `d_st_nofk` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ac_nost_nost_nost
DROP TABLE IF EXISTS `link_ac_nost_nost_nost`;
CREATE TABLE IF NOT EXISTS `link_ac_nost_nost_nost` (
  `id_a_nost_nofk` int(10) unsigned NOT NULL,
  `id_c_nost_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_nost_nofk`,`id_c_nost_nofk`),
  KEY `FK_link_ac_nost_nost_nost_c_nost_nofk` (`id_c_nost_nofk`),
  CONSTRAINT `FK_link_ac_nost_nost_nost_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`),
  CONSTRAINT `FK_link_ac_nost_nost_nost_c_nost_nofk` FOREIGN KEY (`id_c_nost_nofk`) REFERENCES `c_nost_nofk` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test.link_ac_nost_nost_nost: ~0 rows (approximately)
DELETE FROM `link_ac_nost_nost_nost`;
/*!40000 ALTER TABLE `link_ac_nost_nost_nost` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ac_nost_nost_nost` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ac_nost_nost_st
DROP TABLE IF EXISTS `link_ac_nost_nost_st`;
CREATE TABLE IF NOT EXISTS `link_ac_nost_nost_st` (
  `id_a_nost_nofk` int(10) unsigned NOT NULL,
  `id_c_st_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_nost_nofk`,`id_c_st_nofk`),
  KEY `FK_link_ac_nost_nost_st_c_st_nofk` (`id_c_st_nofk`),
  CONSTRAINT `FK_link_ac_nost_nost_st_c_st_nofk` FOREIGN KEY (`id_c_st_nofk`) REFERENCES `c_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ac_nost_nost_st_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ac_nost_nost_st: ~0 rows (approximately)
DELETE FROM `link_ac_nost_nost_st`;
/*!40000 ALTER TABLE `link_ac_nost_nost_st` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ac_nost_nost_st` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ac_nost_st_nost
DROP TABLE IF EXISTS `link_ac_nost_st_nost`;
CREATE TABLE IF NOT EXISTS `link_ac_nost_st_nost` (
  `id_a_st_nofk` int(10) unsigned NOT NULL,
  `id_c_nost_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_st_nofk`,`id_c_nost_nofk`),
  KEY `FK_link_ac_nost_st_nost_c_nost_nofk` (`id_c_nost_nofk`),
  CONSTRAINT `FK_link_ac_nost_st_nost_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ac_nost_st_nost_c_nost_nofk` FOREIGN KEY (`id_c_nost_nofk`) REFERENCES `c_nost_nofk` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ac_nost_st_nost: ~0 rows (approximately)
DELETE FROM `link_ac_nost_st_nost`;
/*!40000 ALTER TABLE `link_ac_nost_st_nost` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ac_nost_st_nost` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ac_nost_st_st
DROP TABLE IF EXISTS `link_ac_nost_st_st`;
CREATE TABLE IF NOT EXISTS `link_ac_nost_st_st` (
  `id_a_st_nofk` int(10) unsigned NOT NULL,
  `id_c_st_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_st_nofk`,`id_c_st_nofk`),
  KEY `FK_link_ac_nost_st_st_c_st_nofk` (`id_c_st_nofk`),
  CONSTRAINT `FK_link_ac_nost_st_st_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ac_nost_st_st_c_st_nofk` FOREIGN KEY (`id_c_st_nofk`) REFERENCES `c_st_nofk` (`id_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ac_nost_st_st: ~0 rows (approximately)
DELETE FROM `link_ac_nost_st_st`;
/*!40000 ALTER TABLE `link_ac_nost_st_st` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ac_nost_st_st` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ad_st_nost_nost
DROP TABLE IF EXISTS `link_ad_st_nost_nost`;
CREATE TABLE IF NOT EXISTS `link_ad_st_nost_nost` (
  `id_state` int(10) unsigned NOT NULL,
  `id_a_nost_nofk` int(10) unsigned NOT NULL,
  `id_d_nost_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_nost_nofk`,`id_d_nost_nofk`,`id_state`),
  KEY `FK_link_ad_nost_nost_nost_d_nost_nofk` (`id_d_nost_nofk`),
  KEY `FK_link_ad_nost_nost_nost_state` (`id_state`),
  CONSTRAINT `FK_link_ad_nost_nost_nost_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`),
  CONSTRAINT `FK_link_ad_nost_nost_nost_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`),
  CONSTRAINT `FK_link_ad_nost_nost_nost_d_nost_nofk` FOREIGN KEY (`id_d_nost_nofk`) REFERENCES `d_nost_nofk` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ad_st_nost_nost: ~0 rows (approximately)
DELETE FROM `link_ad_st_nost_nost`;
/*!40000 ALTER TABLE `link_ad_st_nost_nost` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ad_st_nost_nost` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ad_st_nost_st
DROP TABLE IF EXISTS `link_ad_st_nost_st`;
CREATE TABLE IF NOT EXISTS `link_ad_st_nost_st` (
  `id_state` int(10) unsigned NOT NULL,
  `id_a_nost_nofk` int(10) unsigned NOT NULL,
  `id_d_st_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_nost_nofk`,`id_d_st_nofk`,`id_state`),
  KEY `FK_link_ad_st_nost_nost_d_st_nofk` (`id_d_st_nofk`),
  KEY `FK_link_ad_st_nost_st_state` (`id_state`),
  CONSTRAINT `FK_link_ad_st_nost_st_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`),
  CONSTRAINT `FK_link_ad_st_nost_nost_a_nost_nofk` FOREIGN KEY (`id_a_nost_nofk`) REFERENCES `a_nost_nofk` (`id`),
  CONSTRAINT `FK_link_ad_st_nost_nost_d_st_nofk` FOREIGN KEY (`id_d_st_nofk`) REFERENCES `d_st_nofk` (`id_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ad_st_nost_st: ~0 rows (approximately)
DELETE FROM `link_ad_st_nost_st`;
/*!40000 ALTER TABLE `link_ad_st_nost_st` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ad_st_nost_st` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ad_st_st_nost
DROP TABLE IF EXISTS `link_ad_st_st_nost`;
CREATE TABLE IF NOT EXISTS `link_ad_st_st_nost` (
  `id_state` int(10) unsigned NOT NULL,
  `id_a_st_nofk` int(10) unsigned NOT NULL,
  `id_d_nost_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_st_nofk`,`id_d_nost_nofk`,`id_state`),
  KEY `FK_link_ad_st_st_nost_state` (`id_state`),
  KEY `FK_link_ad_st_st_nost_d_nost_nofk` (`id_d_nost_nofk`),
  CONSTRAINT `FK_link_ad_st_st_nost_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ad_st_st_nost_d_nost_nofk` FOREIGN KEY (`id_d_nost_nofk`) REFERENCES `d_nost_nofk` (`id`),
  CONSTRAINT `FK_link_ad_st_st_nost_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ad_st_st_nost: ~0 rows (approximately)
DELETE FROM `link_ad_st_st_nost`;
/*!40000 ALTER TABLE `link_ad_st_st_nost` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ad_st_st_nost` ENABLE KEYS */;


# Dumping structure for table bransom_test.link_ad_st_st_st
DROP TABLE IF EXISTS `link_ad_st_st_st`;
CREATE TABLE IF NOT EXISTS `link_ad_st_st_st` (
  `id_state` int(10) unsigned NOT NULL,
  `id_a_st_nofk` int(10) unsigned NOT NULL,
  `id_d_st_nofk` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_a_st_nofk`,`id_d_st_nofk`,`id_state`),
  KEY `FK_link_ad_st_st_st_state` (`id_state`),
  KEY `FK_link_ad_st_st_st_d_st_nofk` (`id_d_st_nofk`),
  CONSTRAINT `FK_link_ad_st_st_st_a_st_nofk` FOREIGN KEY (`id_a_st_nofk`) REFERENCES `a_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ad_st_st_st_d_st_nofk` FOREIGN KEY (`id_d_st_nofk`) REFERENCES `d_st_nofk` (`id_object`),
  CONSTRAINT `FK_link_ad_st_st_st_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

# Dumping data for table bransom_test.link_ad_st_st_st: ~0 rows (approximately)
DELETE FROM `link_ad_st_st_st`;
/*!40000 ALTER TABLE `link_ad_st_st_st` DISABLE KEYS */;
/*!40000 ALTER TABLE `link_ad_st_st_st` ENABLE KEYS */;


# Dumping structure for table bransom_test._account
DROP TABLE IF EXISTS `_account`;
CREATE TABLE IF NOT EXISTS `_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `password` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test._account: ~1 rows (approximately)
DELETE FROM `_account`;
/*!40000 ALTER TABLE `_account` DISABLE KEYS */;
INSERT INTO `_account` (`id`, `name`, `password`) VALUES
	(1, '_admin', '');
/*!40000 ALTER TABLE `_account` ENABLE KEYS */;


# Dumping structure for table bransom_test._audit
DROP TABLE IF EXISTS `_audit`;
CREATE TABLE IF NOT EXISTS `_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int(10) unsigned NOT NULL,
  `at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_audit_account` (`id_account`),
  CONSTRAINT `FK_audit_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test._audit: ~0 rows (approximately)
DELETE FROM `_audit`;
/*!40000 ALTER TABLE `_audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `_audit` ENABLE KEYS */;


# Dumping structure for table bransom_test._entity
DROP TABLE IF EXISTS `_entity`;
CREATE TABLE IF NOT EXISTS `_entity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `id_object_column_name` varchar(80) DEFAULT NULL,
  `id_state_column_name` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test._entity: ~21 rows (approximately)
DELETE FROM `_entity`;
/*!40000 ALTER TABLE `_entity` DISABLE KEYS */;
INSERT INTO `_entity` (`id`, `name`, `id_object_column_name`, `id_state_column_name`) VALUES
	(1, '_account', 'id', NULL),
	(2, 'a_nost_fk', 'id', NULL),
	(3, 'a_nost_nofk', 'id', NULL),
	(4, 'a_st_fk', 'id_object', 'id_state'),
	(5, 'a_st_nofk', 'id_object', 'id_state'),
	(6, 'b_nost_fk', 'id', NULL),
	(7, 'b_nost_nofk', 'id', NULL),
	(8, 'b_st_fk', 'id_object', 'id_state'),
	(9, 'b_st_nofk', 'id_object', 'id_state'),
	(10, 'c_nost_nofk', 'id', NULL),
	(11, 'c_st_nofk', 'id_object', 'id_state'),
	(12, 'd_nost_nofk', 'id', NULL),
	(13, 'd_st_nofk', 'id_object', 'id_state'),
	(14, 'link_ac_nost_nost_nost', NULL, NULL),
	(15, 'link_ac_nost_nost_st', NULL, NULL),
	(16, 'link_ac_nost_st_nost', NULL, NULL),
	(17, 'link_ac_nost_st_st', NULL, NULL),
	(18, 'link_ad_st_nost_nost', NULL, 'id_state'),
	(19, 'link_ad_st_nost_st', NULL, 'id_state'),
	(20, 'link_ad_st_st_nost', NULL, 'id_state'),
	(21, 'link_ad_st_st_st', NULL, 'id_state');
/*!40000 ALTER TABLE `_entity` ENABLE KEYS */;


# Dumping structure for table bransom_test._relationship
DROP TABLE IF EXISTS `_relationship`;
CREATE TABLE IF NOT EXISTS `_relationship` (
  `id_fk_entity` int(10) unsigned NOT NULL,
  `fk_column_name` varchar(80) NOT NULL,
  `id_referred_entity` int(10) unsigned DEFAULT NULL,
  `id_owner_entity` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_fk_entity`,`fk_column_name`),
  KEY `FK_relationship_owner_entity` (`id_owner_entity`),
  KEY `FK_relationship_referred_entity` (`id_referred_entity`),
  CONSTRAINT `FK_relationship_fk_entity` FOREIGN KEY (`id_fk_entity`) REFERENCES `_entity` (`id`),
  CONSTRAINT `FK_relationship_owner_entity` FOREIGN KEY (`id_owner_entity`) REFERENCES `_entity` (`id`),
  CONSTRAINT `FK_relationship_referred_entity` FOREIGN KEY (`id_referred_entity`) REFERENCES `_entity` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test._relationship: ~28 rows (approximately)
DELETE FROM `_relationship`;
/*!40000 ALTER TABLE `_relationship` DISABLE KEYS */;
INSERT INTO `_relationship` (`id_fk_entity`, `fk_column_name`, `id_referred_entity`, `id_owner_entity`) VALUES
	(2, 'id_account', 1, 1),
	(2, 'id_b_nost_nofk', 7, 2),
	(2, 'id_b_st_nofk', 9, 2),
	(3, 'id_account', 1, 1),
	(4, 'id_account', 1, 1),
	(4, 'id_b_nost_nofk', 7, 4),
	(4, 'id_b_st_nofk', 9, 4),
	(5, 'id_account', 1, 1),
	(6, 'id_a_nost_nofk', 3, 3),
	(6, 'id_a_st_nofk', 5, 5),
	(8, 'id_a_nost_nofk', 3, 3),
	(8, 'id_a_st_nofk', 5, 5),
	(14, 'id_a_nost_nofk', 3, 3),
	(14, 'id_c_nost_nofk', 10, 10),
	(15, 'id_a_nost_nofk', 3, 3),
	(15, 'id_c_st_nofk', 11, 11),
	(16, 'id_a_st_nofk', 5, 5),
	(16, 'id_c_nost_nofk', 10, 10),
	(17, 'id_a_st_nofk', 5, 5),
	(17, 'id_c_st_nofk', 11, 11),
	(18, 'id_a_nost_nofk', 3, 3),
	(18, 'id_d_nost_nofk', 12, 12),
	(19, 'id_a_nost_nofk', 3, 3),
	(19, 'id_d_st_nofk', 13, 13),
	(20, 'id_a_st_nofk', 5, 5),
	(20, 'id_d_nost_nofk', 12, 12),
	(21, 'id_a_st_nofk', 5, 5),
	(21, 'id_d_st_nofk', 13, 13);
/*!40000 ALTER TABLE `_relationship` ENABLE KEYS */;


# Dumping structure for view bransom_test._relationship_vw
DROP VIEW IF EXISTS `_relationship_vw`;
# Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `_relationship_vw` (
	fk_entity VARCHAR(80) NOT NULL COLLATE 'utf8_general_ci',
	fk_column_name VARCHAR(80) NOT NULL COLLATE 'utf8_general_ci',
	referred_entity VARCHAR(80) NOT NULL COLLATE 'utf8_general_ci',
	owner_entity VARCHAR(80) NULL DEFAULT NULL COLLATE 'utf8_general_ci'
) ENGINE=MyISAM;


# Dumping structure for table bransom_test._state
DROP TABLE IF EXISTS `_state`;
CREATE TABLE IF NOT EXISTS `_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_created` int(10) unsigned NOT NULL,
  `id_terminated` int(10) unsigned DEFAULT NULL,
  `id_published` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_state_created` (`id_created`),
  KEY `FK_state_terminated` (`id_terminated`),
  KEY `FK_state_published` (`id_published`),
  CONSTRAINT `FK_state_created` FOREIGN KEY (`id_created`) REFERENCES `_audit` (`id`),
  CONSTRAINT `FK_state_terminated` FOREIGN KEY (`id_terminated`) REFERENCES `_audit` (`id`),
  CONSTRAINT `FK_state_published` FOREIGN KEY (`id_published`) REFERENCES `_audit` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_test._state: ~0 rows (approximately)
DELETE FROM `_state`;
/*!40000 ALTER TABLE `_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `_state` ENABLE KEYS */;


# Dumping structure for view bransom_test._relationship_vw
DROP VIEW IF EXISTS `_relationship_vw`;
# Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `_relationship_vw`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `_relationship_vw` AS select `e_fk`.`name` AS `fk_entity`,`r`.`fk_column_name` AS `fk_column_name`,`e_referred`.`name` AS `referred_entity`,`e_owner`.`name` AS `owner_entity` from (((`_relationship` `r` join `_entity` `e_fk` on((`e_fk`.`id` = `r`.`id_fk_entity`))) join `_entity` `e_referred` on((`e_referred`.`id` = `r`.`id_referred_entity`))) left join `_entity` `e_owner` on((`e_owner`.`id` = `r`.`id_owner_entity`)));
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
