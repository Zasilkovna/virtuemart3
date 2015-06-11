<?php
/**
*
* Description
*
* @package	VirtueMart
* @subpackage Config
* @author RickG
* @link http://www.virtuemart.net
* @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* @version $Id: default_shop.php 6147 2012-06-22 13:45:47Z alatak $
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
$db = JFactory::getDBO ();
$db->setQuery($q);
$obj = $db->loadObject ();
$zasConfig = unserialize($obj->custom_data);

?>
<br />
<fieldset>
    <legend><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SETTINGS') ?></legend>
    <table class="admintable">
	<tr>
		<?php
			echo VmHTML::row('input','PLG_VMSHIPMENT_ZASILKOVNA_API_PASS','zasilkovna_api_pass',$zasConfig['zasilkovna_api_pass']);
		?>
	</tr>
	<tr>
		<?php
			echo VmHTML::row('input','PLG_VMSHIPMENT_ZASILKOVNA_ESHOP_DOMAIN','zasilkovna_eshop_domain',$zasConfig['zasilkovna_eshop_domain']);
		?>
	</tr>
	<tr>
		<?php
			echo VmHTML::row('value','PLG_VMSHIPMENT_ZASILKOVNA_VERSION',$this->moduleVersion);
		?>
	</tr>
	<tr>

		<?php
		echo VmHTML::row('checkbox','PLG_VMSHIPMENT_ZASILKOVNA_DEFAULT_SELECT','zasilkovna_default_select',$zasConfig['zasilkovna_default_select']);
		?>
	</tr>
	</table>
</fieldset>

<fieldset>
<legend><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_COD') ?></legend>
<table class="admintable">
	<?php
	foreach ($this->paymentMethods as $paymentMethod) {?>
	<tr>
		<td>
			<?php echo $paymentMethod->payment_name;?>
		</td>
		<td>
			<?php echo VmHTML::checkbox('zasilkovna_payment_method_'.$paymentMethod->virtuemart_paymentmethod_id, (isset($zasConfig['zasilkovna_payment_method_'.$paymentMethod->virtuemart_paymentmethod_id]) ? $zasConfig['zasilkovna_payment_method_'.$paymentMethod->virtuemart_paymentmethod_id] : '0'));?>
		</td>
	</tr>
	<?php
	}
	?>

</table>
</fieldset>

<fieldset>
<legend><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION') ?></legend>
<?php
	if($this->restrictionInstalled){
		echo "<span>".JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_INSTALLED')."</span>";
	}else{
		echo "<span style='color:red;font-weight:bold;'>".JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_NOT_INSTALLED')."</span>";
	}
?>
<table class="adminlist jgrid table table-striped" cellspacing="0" cellpadding="0">
	<thead>
		<tr>
			<th></th>
			<?php
			foreach ($this->paymentMethods as $paymentMethod) {?>
				<th>
					<?php echo $paymentMethod->payment_name;?>
				</th>
			<?php
				}
			?>
		</tr>
	</thead>
	<?php
	//$shipmentMethod->virtuemart_shipmentmethod_id;
	$row=0;
	foreach ($this->shipmentMethods as $shipmentMethod) {?>
	<tr class="row<?php echo $row%2;?>">
		<td>
			<?php echo $shipmentMethod->shipment_name;?>
		</td>
		<?php
		foreach ($this->paymentMethods as $paymentMethod) {?>
			<td>
				<?php
				$configRecordName='zasilkovna_combination_payment_'.$paymentMethod->virtuemart_paymentmethod_id.'_shipment_'.$shipmentMethod->virtuemart_shipmentmethod_id;
				echo VmHTML::checkbox($configRecordName, (isset($zasConfig[$configRecordName]) ? $zasConfig[$configRecordName] : '1'));
				?>
			</td>
		<?php
		$row++;
			}
		?>
	</tr>
	<?php
	}
	?>
</table>
<br>
Jak nainstalovat omezení: <br>
  1. v souboru <i>/components/com_virtuemart/views/cart/tmpl/select_payment.php</i> najít tuto část kodu: (řádek cca 60)<br>
  <textarea onfocus="this.select();" onclick="this.select();"  readonly=""   rows="3" cols="80">
foreach ($paymentplugin_payments as $paymentplugin_payment) {
	echo $paymentplugin_payment.&#39;<br />&#39;;
}
  </textarea><br><br> <br><br><br>
  2. A nahradit ji tímto:<br>
  <textarea onfocus="this.select();" onclick="this.select();"  readonly=""    rows="13" cols="130">
$q = "SELECT custom_data FROM #__extensions WHERE element=&#39;zasilkovna&#39;";
$db = JFactory::getDBO ();
$db->setQuery($q);
$obj = $db->loadObject ();

$config = unserialize($obj->custom_data);
foreach ($paymentplugin_payments as $paymentplugin_payment) {
	//ZASILKOVNA - payment-shipment combination restriction
	$selectedShipment = (empty($this->cart->virtuemart_shipmentmethod_id) ? 0 : $this->cart->virtuemart_shipmentmethod_id);
	if($selectedShipment!=0){
		preg_match(&#39;#\s+value\s*=\s*"([^"]*)"#&#39;, $paymentplugin_payment, $matches);
		$paymentId=$matches[1];
		$configRecordName=&#39;zasilkovna_combination_payment_&#39;.$paymentId.&#39;_shipment_&#39;.$selectedShipment;
		if((isset($config[$configRecordName]) ? $config[$configRecordName] : &#39;1&#39;) == &#39;0&#39;) continue;
	}
	echo $paymentplugin_payment.&#39;<br />&#39;;
}
  </textarea>
</fieldset>