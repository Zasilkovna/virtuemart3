DROP TABLE `#__virtuemart_zasilkovna_branches`;
CREATE TABLE `#__virtuemart_zasilkovna_carriers` (
	`id` INT(10) NOT NULL,
	`name` VARCHAR(255) NOT NULL,
	`is_pickup_points` TINYINT(1) NOT NULL,
	`has_carrier_direct_label` TINYINT(1) NOT NULL,
	`separate_house_number` TINYINT(1) NOT NULL,
	`customs_declarations` TINYINT(1) NOT NULL,
	`requires_email` TINYINT(1) NOT NULL,
	`requires_phone` TINYINT(1) NOT NULL,
	`requires_size` TINYINT(1) NOT NULL,
	`disallows_cod` TINYINT(1) NOT NULL,
	`country` VARCHAR(255) NOT NULL,
	`currency` VARCHAR(255) NOT NULL,
	`max_weight` FLOAT NOT NULL,
	`deleted` TINYINT(1) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
