<?php
/**
 * Template variables
 * @var \stdClass $shipment
 */

defined('_JEXEC') or die;

$_= static function($key) { return \JText::_($key); };
$adultContentChecked = $shipment->adult_content ? 'checked' : '';

$html = <<< HTML
<table>
<tr>
    <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_WEIGHT')}</td>
    <td >$shipment->weight kg</td>
</tr>
<tr>
    <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_COD')}</td>
    <td>$shipment->packet_cod</td>
</tr>
<tr>
    <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE')}</td>
    <td>
        $shipment->zasilkovna_packet_price
        $shipment->branch_currency
    </td>
</tr>
<tr>
    <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT')}</td>
    <td><input type="checkbox" name="adult_content" $adultContentChecked readonly></td>
</tr>
</table><br>
HTML;

echo $html;
