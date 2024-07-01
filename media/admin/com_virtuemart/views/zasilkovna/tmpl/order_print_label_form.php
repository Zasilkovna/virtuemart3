<?php
/**
 * Template variables
 *
 * @var \VirtueMartModelZasilkovna\Order\Order $order
 * @var string $defaultLabelFormat
 * @var string $labelFormatType
 */

defined('_JEXEC') or die;

$token = \JHtml::_('form.token');
?>
<div id="packeteryPrintLabelModal">
    <form action="index.php?option=com_virtuemart&view=zasilkovna&task=printLabels" method="post" id="packeteryPrintLabelForm">
        <div class="form-container">
            <input type="hidden" name="virtuemart_order_id" value="<?php echo htmlentities($order->getVirtuemartOrderId()); ?>">
            <input type="hidden" name="printLabels[]" value="<?php echo htmlentities($order->getZasilkovnaPacketId()); ?>">
            <?php echo $token; ?>
            <fieldset>
                <div class="uk-card uk-card-small uk-card-vm">
                    <div class="uk-card-header">
                        <div class="uk-card-title">
                            <span class="md-color-cyan-600 uk-margin-small-right uk-icon" uk-icon="icon: printer; ratio: 1.2"></span>
                            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABEL'); ?>
                        </div>
                    </div>
                    <div class="uk-card-body">
                        <table class="uk-table uk-table-small">
                            <tbody>
                                <tr>
                                    <td class="key va-middle">
                                        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABEL_FORMAT'); ?>
                                    </td>
                                    <td>
                                        <?php echo \VirtueMartModelZasilkovna\Label\Format::getLabelFormatSelectHtml($defaultLabelFormat, $labelFormatType);?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="key va-middle">
                                        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_LABEL_OFFSET'); ?>
                                    </td>
                                    <td>
                                        <input type="text" id="label_first_page_skip" name="label_first_page_skip" value="0"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="uk-card-footer uk-text-center va-middle">
                        <div class="uk-inline">
                            <a href="#" title="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT'); ?>"
                               onclick="submitPrintLabel(event);"
                               class="uk-button uk-button-small uk-button-primary md-bg-white">
                                <span class="uk-icon" uk-icon="icon: printer; ratio: 1"></span>
                                &nbsp;<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT'); ?>
                            </a>
                            <a href="#" title="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CANCEL'); ?>"
                               onclick="cancelPacketeryPrintLabel(event);"
                               class="uk-button uk-button-small uk-button-default md-bg-white">
                                <span class="icon-nofloat vmicon vmicon-16-remove"></span>
                                &nbsp;<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CANCEL'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
    </form>
</div>
