<?php defined('_JEXEC') or die('Restricted access');
    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');

    echo '<div id="zasilkovna-messages"></div>';

    $buttonHtml = '<button onclick="validateForm();" class="btn btn-small button-apply btn-success validate"><span class="icon-apply icon-white" aria-hidden="true"></span>'. JText::_('Save') . '</button>';
    JToolbar::getInstance('toolbar')->prependButton('Custom', $buttonHtml, 'applyZas');
?>

<div id="rules-tab"></div>

<!-- base configuration part -->
<fieldset>
    <legend>
        <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SETTINGS') ?>
    </legend>
    <table class="admintable">
        <tr>
            <?php echo VmHTML::row('input', 'PLG_VMSHIPMENT_ZASILKOVNA_API_PASS', 'zasilkovna_api_pass', $model->getConfig('zasilkovna_api_pass')); ?>
        </tr>
        <tr>
            <?php echo VmHTML::row('input', 'PLG_VMSHIPMENT_ZASILKOVNA_ESHOP_LABEL', 'zasilkovna_eshop_label', $model->getConfig('zasilkovna_eshop_label')); ?>
        </tr>
        <tr>
            <?php echo VmHTML::row('value', 'PLG_VMSHIPMENT_ZASILKOVNA_VERSION', $this->moduleVersion); ?>
        </tr>
    </table>
</fieldset>

<fieldset>
    <legend>
        <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_COD') ?>
    </legend>
    <table class="admintable">
        <?php foreach($this->paymentMethods as $paymentMethod) : ?>
            <tr>
                <td>
                    <?php echo $paymentMethod->payment_name; ?>
                </td>
                <td>
                    <?php echo VmHTML::checkbox('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, $model->getConfig('zasilkovna_payment_method_' . $paymentMethod->virtuemart_paymentmethod_id, '0') ); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</fieldset>

<!-- shipment and payment restrictions part -->
<fieldset>
    <legend>
        <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION'); ?>
    </legend>
        <?php if($this->restrictionInstalled): ?>
            <span><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_INSTALLED');?></span>
        <?php else: ?>
            <span style='color:red;font-weight:bold;'><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_NOT_INSTALLED');?></span>
        <?php endif; ?>
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


    <p><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_INSTALL');?></p>
    <p><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_WHERE');?></p>

    <textarea onfocus="this.select();" onclick="this.select();" readonly="" rows="3" cols="80">
foreach ($paymentplugin_payments as $paymentplugin_payment) {
	echo $paymentplugin_payment.&#39;<br />&#39;;
}
    </textarea>

    <p style="clear:both;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_PAYMENT_SHIPMENT_RESTRICTION_REPLACE');?></p>

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

</fieldset>
