<?php
/**
 * Template variables
 * @var \stdClass $shipment
 */

defined('_JEXEC') or die;

$_= static function($key) { return \JText::_($key); };
$lang = JFactory::getLanguage();

$trackingUrl = sprintf('https://tracking.packeta.com/%s?id=%s',
    $lang ? str_replace( '-', '_', $lang->getTag()) .'/' : '',
    $shipment->zasilkovna_packet_id
);


$html = <<< HTML
<table>
    <tr>
        <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_TRACKING_NUMBER')}</td>
        <td>
            <a href="$trackingUrl" target="_blank">$shipment->zasilkovna_packet_id</a>
        </td>
    </tr>
</table>
HTML;

echo $html;
