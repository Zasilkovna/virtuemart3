<?php
/**
 *
 * Description
 *
 * @package	VirtueMart
 * @subpackage
 * @author
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: orders.php 6046 2012-05-24 12:43:43Z alatak $
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
$db = JFactory::getDBO ();
$db->setQuery($q);
$obj = $db->loadObject ();

$zasConfig = unserialize($obj->custom_data);


?>

<style>
	.zasilkovna{
		font-size: 81%;
	}
	.zasilkovna td {
		vertical-align: inherit;
	}
	.zasilkovna input{
		width: inherit;
		height: inherit;
		font-size: inherit;
	}
</style>
<form action="index.php?option=com_virtuemart&view=zasilkovna&task=export" method="post" name="adminForm" id="adminForm">
    <div id="header">
	<div id="filterbox" >
	    <table>
		<tr>
		    <td align="left" width="100%"><?php 
				echo JText::_('COM_VIRTUEMART_ORDERSTATUS').':'. $this->lists['state_list']; ?><br>
				Typ dopravy: <?php
				echo $this->shipmentSelect;?>
		    </td>
		</tr>
	    </table>
	</div>
	<div id="resultscounter"></div>
    </div>
    <table class="adminlist jgrid table table-striped zasilkovna" cellspacing="0" cellpadding="0">
	<thead>
	    <tr>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SUBMIT'); ?><br><input type="checkbox" name="cbExport" id="cbExport" value="" onclick="zasilkovnaCheckAll(this);" /></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDER_NUMBER'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_EXPORTED'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PRINT_LABELS'); ?><br><input type="checkbox" name="cbPrint" id="cbPrint" value="" onclick="zasilkovnaCheckAll(this);" /></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_COD'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_EMAIL'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PHONE'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PACKET_PRICE'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PACKET_ID'); ?></th>
		<th></th>
		<th>18+</th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_RECEIVER_NAME'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_BRANCH'); ?></th>
        <th>Adresa</th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_METHOD'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CREATED_ON'); ?></th>		
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDER_STATUS'); ?></th>
		<th><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDER_TOTAL'); ?></th>

	    </tr>
	</thead>
	<tbody>
<?php
if (count($this->orderslist) > 0) {

    $keyword = JRequest::getWord('keyword');    
    foreach ($this->orderslist as $key => $order) {    	
    	$disabled="";
    	$submitted=false;
    	if(isset($order->zasilkovna_packet_id)&&$order->zasilkovna_packet_id!=0){
    		$disabled=" disabled ";
    		$submitted=true;
    	}
		$checkBox = '<input type="checkbox" id="cbExport" name="exportOrders[]" value="'.$order->order_number.'" onclick="Joomla.isChecked(this.checked);" title="Checkbox for row '.($key+1).'" '.($order->exported?'':'checked').' '.$disabled.'>';
		?>
		    <tr class="row<?php echo $key%2 ; ?>">
		<!-- Checkbox -->
			<td><?php echo $checkBox; 
				echo '<input type="hidden" name="orders['.$key.'][order_number]" value="'.$order->order_number.'"'.$disabled.'>';
				$submittedVal=($submitted==true)?'1':'0';
				echo '<input type="hidden" name="orders['.$key.'][submitted]" value="'.$submittedVal.'"'.$disabled.'>';
				?>
			</td>

		<!-- Order id -->
			<td><?php $link = 'index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=' . $order->virtuemart_order_id;
				echo JHTML::_('link', JRoute::_($link), $order->order_number, array('target'=>'_blank','title' => JText::_('COM_VIRTUEMART_ORDER_EDIT_ORDER_NUMBER') . ' ' . $order->order_number)); ?>
			</td>
		<!-- exported -->

			<td><?php echo  ($order->exported?(JText::_('PLG_VMSHIPMENT_ZASILKOVNA_YES')):JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NO'));?></td>
		
		<!-- tisk stitku checkbox -->

		<?php 

		$checked="";
		if($order->zasilkovna_packet_id && $order->printed_label==0){
			$checked=" checked ";
		}
		$disabledForNotSubmitted=(!$submitted)?'disabled':' ';
		$checkBox = '<input type="checkbox" id="cbPrint" name="printLabels[]" value="'.$order->zasilkovna_packet_id.'" onclick="Joomla.isChecked(this.checked);" title="Checkbox for row '.($key+1).'" '.$checked.' '.$disabledForNotSubmitted.'>';
		?>
		<td><?php echo $checkBox;?></td>

		<!-- is cod -->
			<td><?php			
			if($order->is_cod==-1){
				$is_cod=$zasConfig['zasilkovna_payment_method_'.$order->virtuemart_paymentmethod_id];
			}else{				
				$is_cod=$order->is_cod;
			}
			echo '
			<select style="width:70px" class="zasilkovna-cod-export" name="orders['.$key.'][is_cod]" class="inputbox" size="1"'.$disabled.'>
				<option value="1" '.($is_cod?'selected="selected"':'').'>'.JText::_('PLG_VMSHIPMENT_ZASILKOVNA_YES').'</option>
				<option value="0" '.($is_cod?'':'selected="selected"').'>'.JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NO').'</option>
			</select>';			 

			 ?>
			</td>
		<!-- email -->
			<td><?php echo '<input type="input" name="orders['.$key.'][email]" value="'.$order->email.'"'.$disabled.'>';?></td>

		<!-- phone -->
			<td><?php echo '<input type="input" size="13" name="orders['.$key.'][phone]" value="'.$order->phone.'"'.$disabled.'>';?></td>
		<!-- packet price -->
			<td><?php echo '<input size="8" type="input" name="orders['.$key.'][zasilkovna_packet_price]" value="'.$order->zasilkovna_packet_price.'"'.$disabled.'> '.$order->branch_currency;?></td>
		<!-- packet id -->
			<td><?php echo '<input size="10" type="input" name="orders['.$key.'][zasilkovna_packet_id]" value="'.$order->zasilkovna_packet_id.'"'.$disabled.'>';?></td>

		<!--  cancel packet id button -->	
		<?php
			$link = 'index.php?option=com_virtuemart&view=zasilkovna&task=cancelOrderSubmitToZasilkovna&cancel_order_id=' . $order->virtuemart_order_id;			
		?>		
			<td><?php if($disabled){?><a href="<?php echo JRoute::_($link);?>" title="<?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CANCEL_ORDER_SUBMIT_BUTTON');?>" ><img width="16" height="16" src="<?php echo $this->media_url;?>/cancel-icon.png"></a><?php }?></td>
		<!-- 18+ -->
			<td><input type="checkbox" name="orders[<?php echo $key;?>][adult_content]" <?php echo ($order->adult_content==1)?'checked':'';?> <?php echo $disabled;?> ></td>
		<!-- order name -->
			<td><?php echo $order->order_name; ?></td>
		<!-- branch id -->
			<td>
				<?php
				echo '<select style="width:210px" name="orders['.$key.'][branch_id]" class="inputbox" size="1"'.$disabled.'>';
				echo $this->generateBranchOptions($this->branches,$order->branch_id);
				?>
				</select>
			</td>
            
        <!-- adresa prijemce -->
            <td>
                <?php echo '<input type="input" title="Adresa" name="orders['.$key.'][address]" value="'.$order->address.'"'.$disabled.'>';?><br />
                <?php echo '<input type="input" title="Město" name="orders['.$key.'][city]" value="'.$order->city.'"'.$disabled.'>';?><br />
                <?php echo '<input type="input" title="Smerovací číslo" name="orders['.$key.'][zip_code]" value="'.$order->zip_code.'"'.$disabled.'>';?><br />
            </td>

		<!-- Payment method -->
			<td><?php echo $order->payment_method; ?></td>			
		<!-- Order date -->
			<td><?php echo vmJsApi::date($order->created_on, 'LC4', true); ?></td>					
		<!-- Status -->
			<td style="position:relative;">	
				<?php echo $order->order_status;?>	
			</td>			
			
		<!-- Total -->
			<td><?php echo $order->order_total; ?></td>			

		    </tr>
	<?php

    }
}

?>
	</tbody>
	<tfoot>
	<tr>
		<td colspan="11">
			<?php echo $this->pagination->getListFooter(); ?>
		</td>
	</tr>
	</tfoot>
    </table>
  	  	

    <h3><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_LABELS_PRINT') ?></h3>
    <p>
    <label for="print_type"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_LABELS_PRINT_TYPE') ?>: </label> 
    <select name="print_type" id="print_type" style="font-size: 10px; " onchange="document.cookie = 'print_type_sel=' + this.value + '; expires=' + (new Date(2014, 2, 3)).toUTCString() + '; path=/';">        
        <option value="A9_on_A4">štítky, 1/32 A4, tisk na A4, tj. 32ks/stránka</option>
        <option value="A7_on_A4" selected>štítky, 1/8 A4, tisk na A4, tj. 8ks/stránka</option>
        <option value="A6_on_A4">štítky, 1/4 A4, tisk na A4, tj. 4ks/stránka</option>
        <option value="A7_on_A7">štítky, 1/8 A4, přímý tisk, tj. 1ks/stránka</option>
      </select>
      <br>
    <label for="label_first_page_skip"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_LABELS_PRINT_OFFSET') ?>: </label> <input type="text" id="label_first_page_skip" style="width: 30px; font-size: 9px; " name="label_first_page_skip" value="0">polí (<a href="http://www.zasilkovna.cz/print-help/" target="_blank"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_HELP') ?></a>)
	</p>

    <!-- Hidden Fields -->
	<?php echo $this->addStandardHiddenToForm(); ?>
	<!-- <a href="#" onClick="request_export();">Export</a> -->
</form>

<script type="text/javascript">
	
	//$=jQuery;	
	function zasilkovnaCheckAll(mainCb){
		var id=jQuery(mainCb).attr('id');
		jQuery( 'input#'+id ).each(function( index ) {
			if(this == mainCb)return;
			console.log(this);
			console.log(mainCb);
			if(jQuery(this).attr('disabled')) return;
  			if(jQuery(mainCb).attr('checked')){
  				jQuery(this).attr('checked',true);
  			}else{
  				jQuery(this).attr('checked',false);
  			}
		});
		
	}
    <!--

    jQuery('.show_comment').click(function() {
	jQuery(this).prev('.element-hidden').show();
	return false
    });

    jQuery('.element-hidden').mouseleave(function() {
	jQuery(this).hide();
    });
    jQuery('.element-hidden').mouseout(function() {
	jQuery(this).hide();
    });
    -->
</script>
