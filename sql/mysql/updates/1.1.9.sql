ALTER TABLE `#__virtuemart_shipment_plg_zasilkovna`
	ADD `is_carrier` smallint(1) NOT NULL DEFAULT '0' AFTER `branch_name_street`,
	ADD `carrier_pickup_point` varchar(40) DEFAULT NULL AFTER `is_carrier`;
