<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

$mod_name='zasilkovna';

$com_zasilkovna=JPATH_ADMINISTRATOR."/components/com_".$mod_name;
$com_virtuemart=JPATH_ADMINISTRATOR."/components/com_virtuemart";


//delete from the virtuemart shipping module

//delete dir content
$files = glob($com_virtuemart."/classes/shipping/".$mod_name.'/*'); // get all file names
foreach($files as $file){ 
  if(is_file($file))
    unlink($file); 
}
rmdir($com_virtuemart."/classes/shipping/".$mod_name);

unlink($com_virtuemart."/classes/shipping/".$mod_name.".php");
unlink($com_virtuemart."/classes/shipping/".$mod_name.".cfg.php");
unlink($com_virtuemart."/classes/shipping/".$mod_name.".ini");

//delete from orders
unlink($com_virtuemart."/html/order.".$mod_name.".php");
unlink($com_virtuemart."/html/order.".$mod_name."_export.php");

//disabled ship-payment combination cfg files
unlink($com_virtuemart."/html/store.ship_payment.php");
unlink($com_virtuemart."/html/store.ship_payment_save.php");


$db =& JFactory::getDBO();
$q="DROP TABLE IF EXISTS #__zasilkovna_branches;";
$db->setQuery($q);
$db->query();

//$q="DROP TABLE IF EXISTS #__zasilkovna_ship_payment;";
//$db->setQuery($q);
//$db->query();

/*
$q="DROP TABLE IF EXISTS #__zasilkovna_orders;";
$db->setQuery($q);
$db->query();
*/