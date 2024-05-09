<?php

use VirtueMartModelZasilkovna\Box\Renderer;
use VirtueMartModelZasilkovna\Order\Detail;
use VirtueMartModelZasilkovna\ConfigurationValidator as ConfigValidator;

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
            <?php echo VmHTML::input(ConfigValidator::KEY_API_PASS, $this->getFormValue(ConfigValidator::KEY_API_PASS)); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_FIND_API_PASS_IN_CS', '<a href="https://client.packeta.com/support" target="_blank">','</a>'); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_NO_ACCOUNT_REGISTER_HERE','<a href="https://client.packeta.com/registration" target=\"_blank\">', '</a>'); ?>
        </td>
    </tr>
    <tr>
        <th class="align-top">
            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ESHOP_LABEL')?>
        </th>
        <td class="pb-10 pl-3">
            <?php echo VmHTML::input(ConfigValidator::KEY_ESHOP_LABEL, $this->getFormValue(ConfigValidator::KEY_ESHOP_LABEL)); ?><br>
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
    <?php foreach ($this->paymentMethods as $paymentMethod) {
        $field = ConfigValidator::KEY_PAYMENT_METHOD_PREFIX . $paymentMethod->virtuemart_paymentmethod_id;
    ?>
        <tr>
            <th>
                <?php echo $paymentMethod->payment_name; ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox($field, $this->getFormValue($field, '0') ); ?>
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
                <?php echo VmHTML::checkbox(ConfigValidator::KEY_USE_DEFAULT_WEIGHT, (bool)$this->getFormValue(ConfigValidator::KEY_USE_DEFAULT_WEIGHT)); ?>
            </td>
        </tr>
        <tr>
            <th class="pb-28">
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT'); ?>
            </th>
            <td class="pl-3 pb-28">
                <input
                    name="<?php echo ConfigValidator::KEY_DEFAULT_WEIGHT; ?>"
                    type="number"
                    min="0"
                    step="0.001"
                    value="<?php echo $this->getFormValue(ConfigValidator::KEY_DEFAULT_WEIGHT); ?>"
                />
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_USE_DEFAULT_DIMENSIONS'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox(ConfigValidator::KEY_USE_DEFAULT_DIMENSIONS, (bool)$this->getFormValue(ConfigValidator::KEY_USE_DEFAULT_DIMENSIONS)); ?>
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_LENGTH'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="<?php echo ConfigValidator::KEY_DEFAULT_LENGTH; ?>"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $this->getFormValue(ConfigValidator::KEY_DEFAULT_LENGTH); ?>"
                />
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_WIDTH'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="<?php echo ConfigValidator::KEY_DEFAULT_WIDTH; ?>"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $this->getFormValue(ConfigValidator::KEY_DEFAULT_WIDTH); ?>"
                />
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_HEIGHT'); ?>
            </th>
            <td class="pl-3">
                <input
                    name="<?php echo ConfigValidator::KEY_DEFAULT_HEIGHT; ?>"
                    type="number"
                    min="0"
                    step="1"
                    value="<?php echo $this->getFormValue(ConfigValidator::KEY_DEFAULT_HEIGHT); ?>"
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

$this->configStorage->clear(VirtuemartControllerZasilkovna::FROM_POST, VirtuemartControllerZasilkovna::FORM_VALUES);
