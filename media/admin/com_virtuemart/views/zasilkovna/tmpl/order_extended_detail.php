<?php
/**
 * Template variables
 * @var \VirtueMartModelZasilkovna\Order\ShipmentInfo $shipment
 */

defined('_JEXEC') or die;
$yesNo = $shipment->getAdultContent() ? 'PLG_VMSHIPMENT_PACKETERY_YES' : 'PLG_VMSHIPMENT_PACKETERY_NO';

?>
<table>
<tr>
    <td class="key va-middle"><?php echo(JText::_('PLG_VMSHIPMENT_PACKETERY_WEIGHT')); ?></td>
    <td><?php echo($shipment->getWeight()); ?> kg</td>
</tr>
<tr>
    <td class="key va-middle"><?php echo(JText::_('PLG_VMSHIPMENT_PACKETERY_COD')); ?></td>
    <td><?php echo($shipment->getPacketCod()); ?></td>
</tr>
<tr>
    <td class="key va-middle"><?php echo(JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE')); ?></td>
    <td><?php echo($shipment->getZasilkovnaPacketPrice()); ?></td>
</tr>
<tr>
    <td class="key va-middle"><?php echo(JText::_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT')); ?></td>
    <td><?php echo(JText::_($yesNo)); ?></td>
</tr>
</table><br>

