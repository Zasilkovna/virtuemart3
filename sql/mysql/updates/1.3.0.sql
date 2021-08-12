ALTER TABLE `#__virtuemart_shipment_plg_zasilkovna`
	ADD `weight` DECIMAL(10,3) NOT NULL DEFAULT '0.000' AFTER `zasilkovna_packet_price`;
