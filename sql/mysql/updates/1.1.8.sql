alter table `#__virtuemart_shipment_plg_zasilkovna`
    add packet_cod decimal(15,2) NOT NULL default 0;

UPDATE `#__virtuemart_shipment_plg_zasilkovna`
SET packet_cod = zasilkovna_packet_price WHERE is_cod = 1