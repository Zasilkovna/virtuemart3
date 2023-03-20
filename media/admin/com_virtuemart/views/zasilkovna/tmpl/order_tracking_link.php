<?php
/**
 * Template variables
 * @var \VirtueMartModelZasilkovna\Order\Order $order
 * @var string $trackingUrl
 */

defined('_JEXEC') or die;
?>
<table>
    <tr>
        <td class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_TRACKING_NUMBER') ?></td>
        <td>
            <a href="<?php echo $trackingUrl; ?>" target="_blank"><?php echo htmlentities($order->getZasilkovnaPacketId()); ?></a>
        </td>
    </tr>
</table>
