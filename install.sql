CREATE TABLE IF NOT EXISTS `#__virtuemart_shipment_plg_zasilkovna` (
	`id` int(1) unsigned NOT NULL AUTO_INCREMENT,
	`virtuemart_order_id` int(11) unsigned,
	`virtuemart_shipmentmethod_id` mediumint(1) unsigned,
	`order_number` char(32),
	`zasilkovna_packet_id` decimal(10,0),
	`zasilkovna_packet_price` decimal(15,2),
	`weight` decimal(10,4),
	`length` smallint(5) unsigned NULL,
	`width` smallint(5) unsigned NULL,
	`height` smallint(5) unsigned NULL,
	`branch_id` decimal(10,0),
	`branch_currency` char(5),
	`branch_name_street` varchar(500),
	`is_carrier` smallint(1) NOT NULL DEFAULT '0',
	`carrier_pickup_point` varchar(40),
	`email` varchar(255),
	`phone` varchar(255),
	`first_name` varchar(255),
	`last_name` varchar(255),
	`address` varchar(255),
	`city` varchar(255),
	`zip_code` varchar(255),
	`virtuemart_country_id` varchar(255),
	`adult_content` smallint(1) DEFAULT '0',
	`is_cod` smallint(1),
	`packet_cod` decimal(15,2) DEFAULT '0.00',
	`exported` smallint(1),
	`printed_label` smallint(1) DEFAULT '0',
	`shipment_name` varchar(5000),
	`shipment_cost` decimal(10,2),
	`shipment_package_fee` decimal(10,2),
	`tax_id` smallint(1),
	PRIMARY KEY (`id`),
	INDEX (virtuemart_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='zasilkovna';

CREATE TABLE IF NOT EXISTS `#__virtuemart_zasilkovna_carriers` (
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
