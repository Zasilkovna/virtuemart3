<?php

use VirtueMartModelZasilkovna\Box\Renderer;
use VirtueMartModelZasilkovna\Order\Detail;
use Joomla\CMS\Factory;

defined('_JEXEC') || die('Restricted access');

/** @var VirtueMartModelZasilkovna $model */
$model = VmModel::getModel('zasilkovna');

$renderer = new Renderer();
$renderer->setTemplate(Detail::TEMPLATES_DIR . DS . 'card.php');

echo '<div id="zasilkovna-messages"></div>';

$buttonHtml = '<button onclick="validateForm();" class="btn btn-small button-apply btn-success validate"><span class="icon-apply icon-white" aria-hidden="true"></span> ' .
    JText::_('Save') . '</button>';
JToolbar::getInstance('toolbar')->prependButton('Custom', $buttonHtml, 'applyZas');
?>

<div id="rules-tab"></div>

<?php
ob_start();
?>
<!-- base configuration part -->
<table class="admintable">
    <tr>
        <th class="align-top">
            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_API_PASS'); ?>
        </th>
        <td class="pb-10 pl-3">
            <?php echo VmHTML::input('zasilkovna_api_pass', $model->getConfig('zasilkovna_api_pass')); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_FIND_API_PASS_IN_CS', '<a href="https://client.packeta.com/support" target="_blank">','</a>'); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_NO_ACCOUNT_REGISTER_HERE','<a href="https://client.packeta.com/registration" target=\"_blank\">', '</a>'); ?>
        </td>
    </tr>
    <tr>
        <th class="align-top">
            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ESHOP_LABEL')?>
        </th>
        <td class="pb-10 pl-3">
            <?php echo VmHTML::input('zasilkovna_eshop_label', $model->getConfig('zasilkovna_eshop_label')); ?><br>
            <?php echo JText::sprintf(
                'PLG_VMSHIPMENT_PACKETERY_ESHOP_LABEL_DESC',
                '<a href="https://client.packeta.com" target="_blank">',
                '</a>',
                '<a href="https://client.packeta.com/senders" target="_blank">'
            ); ?>
        </td>
    </tr>
    <tr>
        <th>
            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_VERSION')?>
        </th>
        <td class="pl-3">
            <?php echo $this->moduleVersion; ?>
        </td>
    </tr>
</table>
<?php
$baseContent = ob_get_clean();

ob_start();
?>
<table class="admintable">
    <?php foreach ($this->paymentMethods as $paymentMethod) { ?>
        <tr>
            <th>
                <?php echo $paymentMethod->payment_name; ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, $model->getConfig('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, '0') ); ?>
            </td>
        </tr>
    <?php } ?>
</table>
<?php
$codContent = ob_get_clean();

ob_start();
// Default weight and dimensions start
?>
    <table class="admintable">
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_USE_DEFAULT_WEIGHT'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox('zasilkovna_use_default_weight', (bool)$model->getConfig('zasilkovna_use_default_weight')); ?>
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="zasilkovna_default_weight"
                    type="number"
                    min="0"
                    step="0.001"
                    value="<?php echo $model->getConfig('zasilkovna_default_weight', '0'); ?>"
                    placeholder="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ENTER_POSITIVE_VALUE_IN_KG'); ?>"
                />
            </td>
        </tr>
        <tr><th></th><td>&nbsp;</td></tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_USE_DEFAULT_DIMENSIONS'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox('zasilkovna_use_default_dimensions', (bool)$model->getConfig('zasilkovna_use_default_dimensions')); ?>
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_LENGTH'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="zasilkovna_default_length"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $model->getConfig('zasilkovna_default_length', '0'); ?>"
                    placeholder="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ENTER_POSITIVE_VALUE_IN_MM'); ?>"
                />
            </td>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_WIDTH'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="zasilkovna_default_width"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $model->getConfig('zasilkovna_default_width', '0'); ?>"
                    placeholder="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ENTER_POSITIVE_VALUE_IN_MM'); ?>"
                />
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_HEIGHT'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="zasilkovna_default_height"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $model->getConfig('zasilkovna_default_height', '0'); ?>"
                    placeholder="<?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ENTER_POSITIVE_VALUE_IN_MM'); ?>"
                />
            </td>
        </tr>
    </table>

<?php
$weightDimensionsContent = ob_get_clean();
ob_start();

$renderer->setVariables([
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_SETTINGS'),
    'icon' => 'cog',
    'content' => $baseContent,
]);
echo $renderer->renderToString();

$renderer->setVariables([
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_COD'),
    'icon' => 'tag',
    'content' => $codContent,
]);
echo $renderer->renderToString();

$renderer->setVariables([
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT_DIMENSIONS_TITLE'),
    'icon' => 'cog',
    'content' => $weightDimensionsContent,
]);
echo $renderer->renderToString();
