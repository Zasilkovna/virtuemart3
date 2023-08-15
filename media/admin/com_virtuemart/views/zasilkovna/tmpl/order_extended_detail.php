<?php
/**
 * Template variables
 * @var \VirtueMartModelZasilkovna\Order\Order $order
 */

defined('_JEXEC') or die;
$adultYesNo = $order->getAdultContent() ? 'PLG_VMSHIPMENT_PACKETERY_YES' : 'PLG_VMSHIPMENT_PACKETERY_NO';
$exportedYesNo = $order->isExported() ? 'PLG_VMSHIPMENT_PACKETERY_YES' : 'PLG_VMSHIPMENT_PACKETERY_NO';

?>
    <table>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_SHIPPING_NAME'); ?></th>
            <td><?php echo htmlentities($order->getShipmentName()); ?></td>
        </tr>
        <?php
        if (!$order->isHomeDelivery()) {
            ?>
            <tr>
                <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH'); ?></th>
                <td><?php echo htmlentities($order->getBranchNameStreet()); ?></td>
            </tr>
            <?php
        }
        ?>
        <tr>
            <th class="key va-middle"><?php echo JText::_('COM_VIRTUEMART_CURRENCY'); ?></th>
            <td><?php echo htmlentities($order->getBranchCurrency()); ?></td>
        </tr>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_WEIGHT'); ?></th>
            <td><?php echo htmlentities($order->getWeight()); ?> kg</td>
        </tr>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_COD'); ?></th>
            <td><?php echo htmlentities($order->getPacketCod()); ?></td>
        </tr>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE'); ?></th>
            <td><?php echo htmlentities($order->getZasilkovnaPacketPrice()); ?></td>
        </tr>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT'); ?></th>
            <td><?php echo JText::_($adultYesNo); ?></td>
        </tr>
        <tr>
            <th class="key va-middle"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_EXPORTED'); ?></th>
            <td><?php echo JText::_($exportedYesNo); ?></td>
        </tr>
    </table>
<?php
