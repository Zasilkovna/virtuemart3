<?php

use VirtueMartModelZasilkovna\Box\Renderer;
use VirtueMartModelZasilkovna\Order\Detail;

defined('_JEXEC') || die('Restricted access');

/** @var VirtueMartModelZasilkovna $model */
$model = VmModel::getModel('zasilkovna');

$renderer = new Renderer();
$renderer->setTemplate(Detail::TEMPLATES_DIR . DS . 'card.php');

echo '<div id="zasilkovna-messages"></div>';

$buttonHtml = '<button onclick="validateForm();" class="btn btn-small button-apply btn-success validate"><span class="icon-apply icon-white" aria-hidden="true"></span> ' .
    JText::_('Save') . '</button>';
JToolbar::getInstance('toolbar')->prependButton('Custom', $buttonHtml, 'applyZas');

$app = JFactory::getApplication();
$session = $app->getSession();
$postData = $session->get(VirtuemartControllerZasilkovna::SESSION_KEY_POST_DATA);
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
            <?php echo VmHTML::input('zasilkovna_api_pass', $model->getFromPostOrConfig('zasilkovna_api_pass', $postData)); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_FIND_API_PASS_IN_CS', '<a href="https://client.packeta.com/support" target="_blank">','</a>'); ?><br>
            <?php echo JText::sprintf('PLG_VMSHIPMENT_PACKETERY_NO_ACCOUNT_REGISTER_HERE','<a href="https://client.packeta.com/registration" target=\"_blank\">', '</a>'); ?>
        </td>
    </tr>
    <tr>
        <th class="align-top">
            <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_ESHOP_LABEL'); ?>
        </th>
        <td class="pb-10 pl-3">
            <?php echo VmHTML::input('zasilkovna_eshop_label', $model->getFromPostOrConfig('zasilkovna_eshop_label', $postData)); ?><br>
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
                <?php echo VmHTML::checkbox('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, $model->getFromPostOrConfig('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, $postData, '0') ); ?>
            </td>
        </tr>
    <?php } ?>
</table>
<?php
$codContent = ob_get_clean();

ob_start();
?>
    <table class="admintable">
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_USE_DEFAULT_WEIGHT'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox(VirtueMartModelZasilkovna::OPTION_USE_DEFAULT_WEIGHT,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_USE_DEFAULT_WEIGHT, $postData)); ?>
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::input(VirtueMartModelZasilkovna::OPTION_DEFAULT_WEIGHT,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_DEFAULT_WEIGHT, $postData)); ?> kg
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_USE_DEFAULT_DIMENSIONS'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::checkbox(VirtueMartModelZasilkovna::OPTION_USE_DEFAULT_DIMENSIONS,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_USE_DEFAULT_DIMENSIONS, $postData)); ?>
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_LENGTH'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::input(VirtueMartModelZasilkovna::OPTION_DEFAULT_LENGTH,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_DEFAULT_LENGTH, $postData)); ?> mm
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WIDTH'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::input(VirtueMartModelZasilkovna::OPTION_DEFAULT_WIDTH,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_DEFAULT_WIDTH, $postData)); ?> mm
            </td>
        </tr>
        <tr>
            <th>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_HEIGHT'); ?>
            </th>
            <td class="pl-3">
                <?php echo VmHTML::input(VirtueMartModelZasilkovna::OPTION_DEFAULT_HEIGHT,
                    $model->getFromPostOrConfig(VirtueMartModelZasilkovna::OPTION_DEFAULT_HEIGHT, $postData)); ?> mm
            </td>
        </tr>
    </table>
<?php
$dimensionsContent = ob_get_clean();

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
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS'),
    'icon' => 'cog',
    'content' => $dimensionsContent,
]);
echo $renderer->renderToString();

$session->clear(VirtuemartControllerZasilkovna::SESSION_KEY_POST_DATA);
