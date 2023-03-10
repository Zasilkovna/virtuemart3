<?php
/**
 * Template variables
 *
 * @return mixed
 * @var \stdClass $shipment
 */

defined('_JEXEC') or die;

$_= static function($key) { return \JText::_($key); };
$adultContentChecked = $shipment->adult_content ? 'checked' : '';
$token = \JHtml::_('form.token');

$html = <<< HTML
<a id="showPacketeryUpdateOrderDetail" class="btn btn-small" href="#">{$_('PLG_VMSHIPMENT_PACKETERY_EDIT_PACKET_DETAIL')}
    <span class="vmicon vmicon-16-editadd"></span>
</a>
<div id="packeteryUpdateOrderDetail" class="vm-absolute">
        <form action="index.php?option=com_virtuemart&view=zasilkovna&task=updatePacketeryOrderDetail" method="post" id="packeteryUpdateOrderDetailForm">
            <div>
                <input type="hidden" name="virtuemart_order_id" value="$shipment->virtuemart_order_id">
                <input type="hidden" name="order_number" value="$shipment->order_number">
                $token
                <fieldset>
                    <table class="admintable table">
                        <thead>
                            <tr>
                                <td colspan="2">
                                    <h1>{$_('PLG_VMSHIPMENT_PACKETERY_EDIT_DETAIL')}</h1>
                                </td>
                            </tr>
                        </thead>
                        <tr>
                            <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_WEIGHT')}</td>
                            <td ><input type="number" step="0.1" name="weight" value="$shipment->weight"> kg</td>
                        </tr>
                        <tr>
                            <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_COD')}</td>
                            <td>
                                <input type="number" step="0.01" name="packet_cod" value="$shipment->packet_cod">
                                $shipment->branch_currency
                            </td>
                        </tr>
                        <tr>
                            <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE')}</td>
                            <td>
                                <input type="number" step="0.01" name="zasilkovna_packet_price" value="$shipment->zasilkovna_packet_price">
                                $shipment->branch_currency
                            </td>
                        </tr>
                        <tr>
                            <td class="key va-middle">{$_('PLG_VMSHIPMENT_PACKETERY_ADULT_CONTENT')}</td>
                            <td><input type="checkbox" name="adult_content" $adultContentChecked></td>
                        </tr>
                        <tr>
                        <td colspan="2">
                            <a href="#" title="{$_('PLG_VMSHIPMENT_PACKETERY_SAVE')}" onclick="javascript:savePacketeryUpdateOrderDetail(event)">
                                <span class="icon-nofloat vmicon vmicon-16-save"></span>
                                &nbsp;{$_('PLG_VMSHIPMENT_PACKETERY_SAVE')}
                            </a>
                            <a href="#" title="{$_('PLG_VMSHIPMENT_PACKETERY_CANCEL')}" onclick="javascript:cancelPacketeryUpdateOrderDetail(event);">
                                <span class="icon-nofloat vmicon vmicon-16-remove"></span>
                                &nbsp;{$_('PLG_VMSHIPMENT_PACKETERY_CANCEL')}
                            </a>
                            </td>
                        <tr>
                    </table>
                </fieldset>
            </div>
        </form>
</div>
HTML;

echo $html;
