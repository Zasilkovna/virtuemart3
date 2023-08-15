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
?>
<!-- shipment and payment restrictions part -->
<p>
    <?php if ($this->restrictionInstalled): ?>
        <span><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_INSTALLED'); ?></span>
    <?php else: ?>
        <span style='color:red;font-weight:bold;'><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_NOT_INSTALLED'); ?></span>
    <?php endif; ?>
</p>

<table class="adminlist jgrid table table-striped" cellspacing="0" cellpadding="0">
    <thead>
        <tr>
            <th></th>
            <?php foreach($this->paymentMethods as $paymentMethod): ?>
                <th> <?php echo $paymentMethod->payment_name; ?> </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php for($i=0; $i<count($this->shipmentMethods); $i++ ): ?>
        <tr class="row<?php echo $i % 2; ?>">
            <td>
                <?php echo $this->shipmentMethods[$i]->shipment_name; ?>
            </td>
            <?php foreach($this->paymentMethods as $paymentMethod): ?>
            <td>
                <?php
                    $configRecordName = 'zasilkovna_combination_payment_' . $paymentMethod->virtuemart_paymentmethod_id . '_shipment_' . $this->shipmentMethods[$i]->virtuemart_shipmentmethod_id;
                    echo VmHTML::checkbox($configRecordName, $model->getConfig($configRecordName, '1'));
                ?>
            </td>
            <?php endforeach; ?>
    <?php endfor; ?>
</table>

<p><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_INSTALL');?></p>
<p><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_WHERE');?></p>

<textarea onfocus="this.select();" onclick="this.select();" readonly="" rows="3" cols="80">
foreach ($paymentplugin_payments as $paymentplugin_payment) {
	echo $paymentplugin_payment.&#39;<br />&#39;;
}
</textarea>

<p style="clear:both;"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_REPLACE');?></p>

<textarea onfocus="this.select();" onclick="this.select();" readonly="" rows="13" cols="130">
// ZASILKOVNA - payment-shipment combination restriction.
$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
$db = JFactory::getDBO ();
$db->setQuery($q);
$obj = $db->loadObject ();

$config = unserialize($obj->custom_data);
/** @var VirtueMartModelZasilkovna $model */
$model = VmModel::getModel('zasilkovna');

foreach ($paymentplugin_payments as $paymentplugin_payment)
{
    $selectedShipment = (empty($this->cart->virtuemart_shipmentmethod_id) ? 0 : $this->cart->virtuemart_shipmentmethod_id);

    if ($selectedShipment !=0)
    {
        $isMatch = preg_match('#\s+value\s*=\s*"([^"]*)"#', $paymentplugin_payment, $matches);

        if ($isMatch)
        {
            $paymentId=$matches[1];

            $configRecordName='zasilkovna_combination_payment_'.$paymentId.'_shipment_'.$selectedShipment;

            if ($model->getConfig($configRecordName, '1') == '0')
            {
                continue;
            }
        }
    }

    echo '<div class="vm-payment-plugin-single">'.$paymentplugin_payment.'</div>';
}
</textarea>
<?php
$paymentRestrictionContent = ob_get_clean();

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
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION'),
    'icon' => 'cog',
    'content' => $paymentRestrictionContent,
]);
echo $renderer->renderToString();
