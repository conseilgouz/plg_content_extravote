CREATE TABLE IF NOT EXISTS  `#__content_extravote_user` (
	`content_id` INT(11) NOT NULL,
 	`extra_id` INT(11) NOT NULL,
	`user_id` VARCHAR(50) NOT NULL,
	`rating` FLOAT NOT NULL,
	`created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	KEY `extravote_user_idx` (`content_id`)
 	);