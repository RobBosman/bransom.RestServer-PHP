# --------------------------------------------------------
# Host:                         127.0.0.1
# Server version:               5.1.53-community-log
# Server OS:                    Win64
# HeidiSQL version:             6.0.0.3603
# Date/time:                    2011-12-26 13:19:27
# --------------------------------------------------------

SET GLOBAL NET_READ_TIMEOUT=500;
SET GLOBAL NET_WRITE_TIMEOUT=500;
SET GLOBAL MAX_ALLOWED_PACKET=1073741824;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping database structure for bransom_init
DROP DATABASE IF EXISTS `bransom_init`;
CREATE DATABASE IF NOT EXISTS `bransom_init` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `bransom_init`;


# Dumping structure for table bransom_init._account
DROP TABLE IF EXISTS `_account`;
CREATE TABLE IF NOT EXISTS `_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `password` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_init._account: ~1 rows (approximately)
DELETE FROM `_account`;
/*!40000 ALTER TABLE `_account` DISABLE KEYS */;
INSERT INTO `_account` (`id`, `name`, `password`) VALUES
	(1, '_admin', '');
/*!40000 ALTER TABLE `_account` ENABLE KEYS */;


# Dumping structure for table bransom_init._audit
DROP TABLE IF EXISTS `_audit`;
CREATE TABLE IF NOT EXISTS `_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int(10) unsigned NOT NULL,
  `at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_audit_account` (`id_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table bransom_init._audit: ~0 rows (approximately)
DELETE FROM `_audit`;
/*!40000 ALTER TABLE `_audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `_audit` ENABLE KEYS */;


# Dumping structure for table bransom_init._entity
DROP TABLE IF EXISTS `_entity`;
CREATE TABLE IF NOT EXISTS `_entity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `id_object_column_name` varchar(80) DEFAULT NULL,
  `id_state_column_name` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

# Dumping data for table bransom_init._entity: ~1 rows (approximately)
DELETE FROM `_entity`;
/*!40000 ALTER TABLE `_entity` DISABLE KEYS */;
INSERT INTO `_entity` (`id`, `name`, `id_object_column_name`, `id_state_column_name`) VALUES
	(1, '_account', 'id', NULL);
/*!40000 ALTER TABLE `_entity` ENABLE KEYS */;


# Dumping structure for table bransom_init._relationship
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

# Dumping data for table bransom_init._relationship: ~0 rows (approximately)
DELETE FROM `_relationship`;
/*!40000 ALTER TABLE `_relationship` DISABLE KEYS */;
/*!40000 ALTER TABLE `_relationship` ENABLE KEYS */;


# Dumping structure for view bransom_init._relationship_vw
DROP VIEW IF EXISTS `_relationship_vw`;
# Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `_relationship_vw` (
	`fk_entity` VARCHAR(80) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`fk_column_name` VARCHAR(80) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`referred_entity` VARCHAR(80) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`owner_entity` VARCHAR(80) NULL DEFAULT NULL COLLATE 'utf8_general_ci'
) ENGINE=MyISAM;


# Dumping structure for table bransom_init._state
DROP TABLE IF EXISTS `_state`;
CREATE TABLE IF NOT EXISTS `_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_created` int(10) unsigned NOT NULL,
  `id_terminated` int(10) unsigned DEFAULT NULL,
  `id_published` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_state_created` (`id_created`),
  KEY `FK_state_terminated` (`id_terminated`),
  KEY `FK_state_published` (`id_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table bransom_init._state: ~0 rows (approximately)
DELETE FROM `_state`;
/*!40000 ALTER TABLE `_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `_state` ENABLE KEYS */;


# Dumping structure for view bransom_init._relationship_vw
DROP VIEW IF EXISTS `_relationship_vw`;
# Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `_relationship_vw`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `_relationship_vw` AS select `e_fk`.`name` AS `fk_entity`,`r`.`fk_column_name` AS `fk_column_name`,`e_referred`.`name` AS `referred_entity`,`e_owner`.`name` AS `owner_entity` from (((`_relationship` `r` join `_entity` `e_fk` on((`e_fk`.`id` = `r`.`id_fk_entity`))) join `_entity` `e_referred` on((`e_referred`.`id` = `r`.`id_referred_entity`))) left join `_entity` `e_owner` on((`e_owner`.`id` = `r`.`id_owner_entity`)));
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
