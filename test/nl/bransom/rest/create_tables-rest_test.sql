DROP TABLE IF EXISTS `item`;
DROP TABLE IF EXISTS `image`;
DROP TABLE IF EXISTS `itemset`;
DROP TABLE IF EXISTS `settings_a`;
DROP TABLE IF EXISTS `settings_b`;
DROP TABLE IF EXISTS `settings_c`;
DROP TABLE IF EXISTS `site`;

DROP TABLE IF EXISTS `_state`;
DROP TABLE IF EXISTS `_audit`;
DROP TABLE IF EXISTS `_account`;
DROP TABLE IF EXISTS `_entity`;
DROP TABLE IF EXISTS `_relationship`;

CREATE TABLE IF NOT EXISTS `_entity` (
  `name` varchar(80) NOT NULL,
  `namespaceUri` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `_relationship` (
  `fromEntity` varchar(80) NOT NULL,
  `toEntity` varchar(80) NOT NULL,
  `fkEntity` varchar(80) NOT NULL,
  `fkColumn` varchar(80) NOT NULL,
  `multiplicity` varchar(4) NOT NULL,
  KEY `FK_relationships_from_entities` (`fromEntity`),
  KEY `FK_relationships_to_entities` (`toEntity`),
  KEY `FK_relationships_fk_entities` (`fkEntity`),
  CONSTRAINT `FK_relationships_fk_entities` FOREIGN KEY (`fkEntity`) REFERENCES `_entity` (`name`),
  CONSTRAINT `FK_relationships_from_entities` FOREIGN KEY (`fromEntity`) REFERENCES `_entity` (`name`),
  CONSTRAINT `FK_relationships_to_entities` FOREIGN KEY (`toEntity`) REFERENCES `_entity` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `password` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int(10) unsigned NOT NULL,
  `at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `FK_audit_account` (`id_account`),
  CONSTRAINT `FK_audit_account` FOREIGN KEY (`id_account`) REFERENCES `_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  CONSTRAINT `FK_state_published` FOREIGN KEY (`id_published`) REFERENCES `_audit` (`id`),
  CONSTRAINT `FK_state_terminated` FOREIGN KEY (`id_terminated`) REFERENCES `_audit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `site` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_site_state` (`id_state`),
  CONSTRAINT `FK_site_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `settings_a` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `totalWidth` smallint(10) unsigned DEFAULT NULL,
  `totalHeight` smallint(10) unsigned DEFAULT NULL,
  `bgRadius` tinyint(1) unsigned DEFAULT NULL,
  `bgColor` varchar(10) DEFAULT NULL,
  `htmlFieldWidth` smallint(10) unsigned DEFAULT NULL,
  `imageStrokePx` tinyint(1) unsigned DEFAULT NULL,
  `stroke1Px` tinyint(1) unsigned DEFAULT NULL,
  `stroke1Radius` tinyint(1) unsigned DEFAULT NULL,
  `stroke1Color` varchar(10) DEFAULT NULL,
  `stroke2Px` tinyint(1) unsigned DEFAULT NULL,
  `stroke2Radius` tinyint(1) unsigned DEFAULT NULL,
  `stroke2Color` varchar(10) DEFAULT NULL,
  `bgWidth` smallint(10) unsigned DEFAULT NULL,
  `bgHeight` smallint(10) unsigned DEFAULT NULL,
  `butDistance` tinyint(1) unsigned DEFAULT NULL,
  `butHeight` smallint(10) unsigned DEFAULT NULL,
  `maskHeight` smallint(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_settings_a_state` (`id_state`),
  CONSTRAINT `FK_settings_a_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `settings_b` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `width` smallint(10) unsigned DEFAULT NULL,
  `height` smallint(10) unsigned DEFAULT NULL,
  `visibleItems` tinyint(10) unsigned DEFAULT NULL,
  `itemWidth` smallint(10) unsigned DEFAULT NULL,
  `itemHeight` smallint(10) unsigned DEFAULT NULL,
  `itemsHSpacing` smallint(10) unsigned DEFAULT NULL,
  `itemsVSpacing` smallint(10) unsigned DEFAULT NULL,
  `arrowVSpacing` smallint(10) unsigned DEFAULT NULL,
  `showArrows` tinyint(1) unsigned DEFAULT NULL,
  `showScrollbar` tinyint(1) unsigned DEFAULT NULL,
  `scrollbarHeight` smallint(10) unsigned DEFAULT NULL,
  `maxBlur` smallint(10) unsigned DEFAULT NULL,
  `slideTime` float DEFAULT NULL,
  `autoPlay` tinyint(1) unsigned DEFAULT NULL,
  `autoPlayDelay` smallint(10) unsigned DEFAULT NULL,
  `pauseOnItemMouseOver` tinyint(1) unsigned DEFAULT NULL,
  `itemReflection` tinyint(1) unsigned DEFAULT NULL,
  `reflectionAlpha` smallint(10) unsigned DEFAULT NULL,
  `reflectionHeight` smallint(10) unsigned DEFAULT NULL,
  `reflectionDistance` smallint(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_settings_b_state` (`id_state`),
  CONSTRAINT `FK_settings_b_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `settings_c` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `listWidth` smallint(10) unsigned DEFAULT NULL,
  `listHeight` smallint(10) unsigned DEFAULT NULL,
  `buttonHeight` smallint(10) unsigned DEFAULT NULL,
  `blurXAmount` smallint(10) unsigned DEFAULT NULL,
  `blurYAmount` smallint(10) unsigned DEFAULT NULL,
  `animationTime` float DEFAULT NULL,
  `animationType` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_settings_c_state` (`id_state`),
  CONSTRAINT `FK_settings_c_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `itemset` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `id_site` int(10) unsigned DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `type` char(1) DEFAULT NULL,
  `id_settings_a` int(10) unsigned DEFAULT NULL,
  `id_settings_b` int(10) unsigned DEFAULT NULL,
  `id_settings_c` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_itemset_state` (`id_state`),
  KEY `FK_itemset_site` (`id_site`),
  KEY `FK_itemset_settings_a` (`id_settings_a`),
  KEY `FK_itemset_settings_b` (`id_settings_b`),
  KEY `FK_itemset_settings_c` (`id_settings_c`),
  CONSTRAINT `FK_itemset_settings_a` FOREIGN KEY (`id_settings_a`) REFERENCES `settings_a` (`id_object`),
  CONSTRAINT `FK_itemset_settings_b` FOREIGN KEY (`id_settings_b`) REFERENCES `settings_b` (`id_object`),
  CONSTRAINT `FK_itemset_settings_c` FOREIGN KEY (`id_settings_c`) REFERENCES `settings_c` (`id_object`),
  CONSTRAINT `FK_itemset_site` FOREIGN KEY (`id_site`) REFERENCES `site` (`id_object`),
  CONSTRAINT `FK_itemset_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `image` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL DEFAULT '0',
  `width` smallint(10) unsigned NOT NULL DEFAULT '0',
  `height` smallint(10) unsigned NOT NULL DEFAULT '0',
  `caption` varchar(80) DEFAULT NULL,
  `mediatype` varchar(10) NOT NULL,
  `url` varchar(512) DEFAULT NULL,
  `data` longblob,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_image_state` (`id_state`),
  CONSTRAINT `FK_image_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `item` (
  `id_object` int(10) unsigned NOT NULL,
  `id_state` int(10) unsigned NOT NULL,
  `id_itemset` int(10) unsigned NOT NULL,
  `sortIndex_itemset` smallint(10) unsigned NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `title` varchar(1024) NOT NULL,
  `subTitle` varchar(1024) NOT NULL,
  `content` text NOT NULL,
  `id_image` int(10) unsigned DEFAULT NULL,
  `linkText` varchar(80) DEFAULT NULL,
  `linkUrl` varchar(1024) DEFAULT NULL,
  `linkTarget` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_object`,`id_state`),
  KEY `FK_item_itemset` (`id_itemset`),
  KEY `FK_item_image` (`id_image`),
  KEY `FK_item_state` (`id_state`),
  CONSTRAINT `FK_item_image` FOREIGN KEY (`id_image`) REFERENCES `image` (`id_object`),
  CONSTRAINT `FK_item_itemset` FOREIGN KEY (`id_itemset`) REFERENCES `itemset` (`id_object`),
  CONSTRAINT `FK_item_state` FOREIGN KEY (`id_state`) REFERENCES `_state` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO `_entity` (`name`, `namespaceUri`) VALUES
	('image', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('item', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('itemset', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('settings_a', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('settings_b', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('settings_c', 'http://ns.bransom.nl/vanace/webitems/v20110101'),
	('site', 'http://ns.bransom.nl/vanace/webitems/v20110101');
REPLACE INTO `_relationship` (`fromEntity`, `toEntity`, `fkEntity`, `fkColumn`, `multiplicity`) VALUES
	('site', 'itemset', 'itemset', 'id_site', '1'),
	('itemset', 'settings_b', 'itemset', 'id_settings_b', '0..1'),
	('itemset', 'item', 'item', 'id_itemset', '0..n'),
	('item', 'image', 'item', 'id_image', '0..1'),
	('itemset', 'settings_c', 'itemset', 'id_settings_c', '0..1'),
	('itemset', 'settings_a', 'itemset', 'id_settings_a', '0..1');