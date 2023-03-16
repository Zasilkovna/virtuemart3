<?php defined('_JEXEC') or die('Restricted access'); ?>

<style>
    .zasilkovna {
        font-size: 81%;
    }

    .zasilkovna td {
        vertical-align: inherit;
    }

    .zasilkovna input {
        width: inherit;
        height: inherit;
        font-size: inherit;
    }

	.error {
		background-color: #ebccd1;
	}
</style>

<form action="index.php?option=com_virtuemart&view=zasilkovna&task=export" method="post" name="adminForm" id="adminForm">
    <div id="header">
        <div id="filterbox">
            <table>
                <tr>
                    <td align="left" width="100%"><?php
                        echo JText::_('COM_VIRTUEMART_ORDERSTATUS') . ':' . $this->lists['state_list']; ?><br>
                        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EXPORT_STATUS') . ':';
                        echo $this->shipmentSelect; ?>
                    </td>
                </tr>
            </table>
        </div>
        <div id="resultscounter"></div>
    </div>
    <table class="adminlist jgrid table table-striped zasilkovna" cellspacing="0" cellpadding="0">
        <thead>
        <tr>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMIT'); ?><br><input type="checkbox" name="cbExport" id="cbExport" value="" onclick="zasilkovnaCheckAll(this);" /></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_NUMBER'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EXPORTED'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABELS'); ?><br><input type="checkbox" name="cbPrint" id="cbPrint" value="" onclick="zasilkovnaCheckAll(this);" /></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_COD'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EMAIL'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PHONE'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_WEIGHT'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_ID'); ?></th>
            <th></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_RECEIVER_NAME'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ADDRESS'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_METHOD'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CREATED_ON'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_STATUS'); ?></th>
            <th><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_TOTAL'); ?></th>

        </tr>
        </thead>
        <tbody>
        <?php
        if(count($this->orderslist) > 0) {

            $keyword = JRequest::getWord('keyword');
            foreach($this->orderslist as $key => $order) {
				$existBranchOrCarrier = !empty($order->branch_id);
				$disabled = "";
				$submitted = false;
				if(isset($order->zasilkovna_packet_id) && $order->zasilkovna_packet_id != 0) {
					$disabled = " disabled ";
					$submitted = true;
				}
				$disabledForNotSubmitted = (!$submitted) ? 'disabled' : ' ';
				$disabledExport = (!$existBranchOrCarrier ? 'disabled' : '');
				$checkBox = '<input type="checkbox" id="cbExport" name="exportOrders[]" value="' . htmlentities($order->order_number) . '" onclick="Joomla.isChecked(this.checked);" title="Checkbox for row ' . ($key + 1) . '" ' . ($order->exported || !$existBranchOrCarrier ? '' : 'checked') . ' ' .$disabledExport . ' >';
			 	$class = ($existBranchOrCarrier ? "row" . $key % 2 : "error");
				if (!$existBranchOrCarrier) {
					$warningMesage = JText::_('PLG_VMSHIPMENT_PACKETERY_MISSING_BRANCH_ORDER');
					\Joomla\CMS\Factory::getApplication()->enqueueMessage($warningMesage, 'warning');
				}
                ?>
                <tr class="<?php echo $class; ?>">
                    <!-- Checkbox -->
                    <td><?php echo $checkBox;
                        echo '<input type="hidden" name="orders[' . $key . '][order_number]" value="' . htmlentities($order->order_number) . '">';
                        $submittedVal = ($submitted == true) ? '1' : '0';
                        echo '<input type="hidden" name="orders[' . $key . '][submitted]" value="' . $submittedVal . '">';
                        ?>
                    </td>

                    <!-- Order id -->
                    <td><?php $link = 'index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=' . htmlentities($order->virtuemart_order_id);
                        echo JHTML::_('link', JRoute::_($link), htmlentities($order->order_number), array('target' => '_blank', 'title' => JText::_('COM_VIRTUEMART_ORDER_EDIT_ORDER_NUMBER') . ' ' . htmlentities($order->order_number))); ?>
                    </td>
                    <!-- exported -->

                    <td><?php echo($order->exported ? (JText::_('PLG_VMSHIPMENT_PACKETERY_YES')) : JText::_('PLG_VMSHIPMENT_PACKETERY_NO')); ?></td>

                    <!-- tisk stitku checkbox -->

                    <?php

                    $checked = "";
                    if($order->zasilkovna_packet_id && $order->printed_label == 0) {
                        $checked = " checked ";
                    }
                    $checkBox = '<input type="checkbox" id="cbPrint" name="printLabels[]" value="' . htmlentities($order->zasilkovna_packet_id) . '" onclick="Joomla.isChecked(this.checked);" title="Checkbox for row ' . ($key + 1) . '" ' . $checked . ' ' . $disabledForNotSubmitted . '>';
                    ?>
                    <td><?php echo $checkBox; ?></td>

                    <!-- is cod -->
                    <td><?php
						$cod = $order->packet_cod;
						echo '<input size="8" type="input" name="orders[' . $key . '][packet_cod]" value="' . htmlentities($cod) . '"' . $disabled . '> ';
                        ?>
                    </td>
                    <!-- email -->
                    <td><?php 
                        if ($order->email){
                            $email = $order->email;
                        }
                        else{
                            $email = $order->billing_email;
                        }
					    echo htmlentities($email);
                        ?>
                    </td>
                    <!-- phone -->
                    <td><?php echo htmlentities($order->phone); ?></td>
                    <!-- packet price -->
                    <td><?php echo '<input size="8" type="input" name="orders[' . $key . '][zasilkovna_packet_price]" value="' . htmlentities($order->zasilkovna_packet_price) . '"' . $disabled . '> ' . htmlentities($order->branch_currency); ?></td>
                    <!-- weight-->
                    <td><?php echo '<input class="packetery-editable-input" size="8" type="number" step="0.0001" name="orders[' . $key . '][weight]" value="' . (is_numeric($order->weight) ? (float)$order->weight : '') . '"' . $disabled . '> kg'; ?></td>
                    <!-- packet id -->
                    <td>
					<?php
					$packetId = "";
					if($order->zasilkovna_packet_id !== "0") {
					?>
						<a href="<?php echo(plgVmShipmentZasilkovna::TRACKING_URL . htmlentities($order->zasilkovna_packet_id)); ?>" target="_blank"><?php echo htmlentities($order->zasilkovna_packet_id); ?></a>
					<?php } ?>
					</td>
                    <!--  cancel packet id button -->
                    <?php
                    $link = 'index.php?option=com_virtuemart&view=zasilkovna&task=cancelOrderSubmitToZasilkovna&cancel_order_id=' . htmlentities($order->virtuemart_order_id);
                    ?>
                    <td><?php if($disabled) { ?><a href="<?php echo JRoute::_($link); ?>" title="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CANCEL_ORDER_SUBMIT_BUTTON'); ?>" ><img width="16" height="16" src="<?php echo htmlentities($this->media_url); ?>/img/cancel-icon.png"></a><?php } ?></td>
                    <!-- 18+ -->
                    <td><input type="checkbox" name="orders[<?php echo $key; ?>][adult_content]" <?php echo ($order->adult_content == 1) ? 'checked' : ''; ?>  ></td>
                    <!-- order name -->
                    <td><?php echo htmlentities($order->order_name); ?></td>
                    <!-- branch id -->
                    <td>
                        <?php echo htmlentities($order->name_street); ?>
                    </td>

                    <!-- adresa prijemce -->
                    <td>
                        <?php echo htmlentities($order->address); ?><br />
                        <?php echo htmlentities($order->city); ?><br />
                        <?php echo htmlentities($order->zip_code); ?><br />
                    </td>

                    <!-- Payment method -->
                    <td><?php echo htmlentities($order->payment_method); ?></td>
                    <!-- Order date -->
                    <td><?php echo vmJsApi::date(htmlentities($order->created_on), 'LC4', true); ?></td>
                    <!-- Status -->
                    <td style="position:relative;">
                        <?php echo htmlentities($order->order_status); ?>
                    </td>

                    <!-- Total -->
                    <td><?php echo htmlentities($order->order_total); ?></td>

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

    <h3><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT') ?></h3>
    <p>
        <label for="print_type"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_TYPE') ?>: </label>
        <select name="print_type" id="print_type" style="font-size: 10px; " onchange="document.cookie = 'print_type_sel=' + this.value + '; expires=' + (new Date(2014, 2, 3)).toUTCString() + '; path=/';">
            <option value="A7_on_A4" selected><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A7_ON_A4'); ?></option>
            <option value="A6_on_A4"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A6_ON_A4'); ?></option>
			<option value="A6_on_A6"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A6_ON_A6'); ?></option>
            <option value="A7_on_A7"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A7_ON_A7'); ?></option>
            <option value="A8_on_A8"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A8_ON_A8'); ?></option>
            <option value="105x35mm_on_A4"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_105X35MM_ON_A4'); ?></option>
        </select>
        <br>
        <label for="label_first_page_skip"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_OFFSET') ?>: </label>
        <input type="text" id="label_first_page_skip" style="width: 30px; font-size: 9px; " name="label_first_page_skip" value="0">
        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_FIELDS') ?>
        (<a href="http://www.zasilkovna.cz/print-help/" target="_blank"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_HELP') ?></a>)
    </p>

    <!-- Hidden Fields -->
    <?php echo $this->addStandardHiddenToForm(); ?>
</form>

<script type="text/javascript">

    function zasilkovnaCheckAll(mainCb) {
        var id = jQuery(mainCb).attr('id');
        jQuery('input#' + id).each(function(index) {
            if (this == mainCb)return;
            console.log(this);
            console.log(mainCb);
            if (jQuery(this).attr('disabled')) return;
            if (jQuery(mainCb).attr('checked')) {
                jQuery(this).attr('checked', true);
            } else {
                jQuery(this).attr('checked', false);
            }
        });

    }

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
</script>
