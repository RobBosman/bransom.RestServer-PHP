DELETE FROM `item`;
DELETE FROM `itemset`;
DELETE FROM `image`;
DELETE FROM `settings_a`;
DELETE FROM `settings_b`;
DELETE FROM `settings_c`;
DELETE FROM `site`;

DELETE FROM `_state`;
DELETE FROM `_audit`;

ALTER TABLE `_state` AUTO_INCREMENT=1;
REPLACE INTO `_account` (`name`, `password`) VALUES
	('Rob', '');
