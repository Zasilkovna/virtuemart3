<?php
/**
 * Template variables
 * @var \VirtueMartModelZasilkovna\Order\Order $order
 */

defined('_JEXEC') or die;
$yesNo = $order->getAdultContent() ? 'PLG_VMSHIPMENT_PACKETERY_YES' : 'PLG_VMSHIPMENT_PACKETERY_NO';

?>
<table>
<tr>
    <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_WEIGHT'); ?></td>
    <td><?php echo htmlentities($order->getWeight()); ?> kg</td>
</tr>
<tr>
    <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_COD'); ?></td>
    <td><?php echo htmlentities($order->getPacketCod()); ?></td>
</tr>
<tr>
    <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE'); ?></td>
    <td><?php echo htmlentities($order->getZasilkovnaPacketPrice()); ?></td>
</tr>
<tr>
    <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT'); ?></td>
    <td><?php echo JText::_($yesNo); ?></td>
</tr>
</table><br>

