<?php
/**
 * Template variables
 *
 * @var \VirtueMartModelZasilkovna\Order\Order $order
 */

defined('_JEXEC') or die;

$adultContentChecked = $order->getAdultContent() ? 'checked' : '';
$token = \JHtml::_('form.token');

?>
<div class="w-100 mt-2">
    <a id="showPacketeryUpdateOrderDetail" class="btn btn-small uk-button uk-button-small uk-button-default md-bg-white" href="#">
        <span class="vmicon vmicon-16-editadd"></span>
        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EDIT_PACKET_DETAIL'); ?>
    </a>
</div>
<div id="packeteryUpdateOrderDetail" class="vm-absolute">
    <form action="index.php?option=com_virtuemart&view=zasilkovna&task=updatePacketeryOrderDetail" method="post" id="packeteryUpdateOrderDetailForm">
        <div>
            <input type="hidden" name="virtuemart_order_id" value="<?php echo htmlentities($order->getVirtuemartOrderId()); ?>">
            <input type="hidden" name="order_number" value="<?php echo htmlentities($order->getOrderNumber()); ?>">
            <?php echo $token; ?>
            <fieldset>
                <table class="admintable table">
                    <thead>
                        <tr>
                            <th colspan="2">
                                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EDIT_DETAIL'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tr>
                        <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_WEIGHT'); ?></td>
                        <td ><input type="number" step="0.1" name="weight" value="<?php echo htmlentities($order->getWeight()); ?>"> kg</td>
                    </tr>
                    <tr>
                        <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_COD'); ?></td>
                        <td>
                            <input type="number" step="0.01" name="packet_cod" value="<?php echo htmlentities($order->getPacketCod()); ?>">
                            <?php echo htmlentities($order->getBranchCurrency()); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE'); ?></td>
                        <td>
                            <input type="number" step="0.01" name="zasilkovna_packet_price" value="<?php echo htmlentities($order->getZasilkovnaPacketPrice()); ?>">
                            <?php echo htmlentities($order->getBranchCurrency()); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT'); ?></td>
                        <td><input type="checkbox" name="adult_content" <?php echo $adultContentChecked; ?>></td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <a href="#" title="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_SAVE'); ?>"
                               onclick="savePacketeryUpdateOrderDetail(event);"
                               class="btn btn-small uk-button uk-button-small uk-button-default md-bg-white">
                                <span class="icon-nofloat vmicon vmicon-16-save"></span>
                                &nbsp;<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_SAVE'); ?>
                            </a>
                            <a href="#" title="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CANCEL'); ?>"
                               onclick="cancelPacketeryUpdateOrderDetail(event);"
                               class="btn btn-small uk-button uk-button-small uk-button-default md-bg-white">
                                <span class="icon-nofloat vmicon vmicon-16-remove"></span>
                                &nbsp;<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CANCEL'); ?>
                            </a>
                        </td>
                    <tr>
                </table>
            </fieldset>
        </div>
    </form>
</div>
