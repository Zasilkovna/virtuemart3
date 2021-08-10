ALTER TABLE `#__virtuemart_shipment_plg_zasilkovna`
	ADD `weight` DECIMAL(10,4) NOT NULL DEFAULT '0.0000' AFTER `zasilkovna_packet_price`;
