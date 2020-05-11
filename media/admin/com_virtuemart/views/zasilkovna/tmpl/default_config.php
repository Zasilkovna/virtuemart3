<?php defined('_JEXEC') or die('Restricted access');
    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');

    echo '<div id="zasilkovna-messages"></div>';

    $buttonHtml = '<button onclick="validateForm();" class="btn btn-small button-apply btn-success validate"><span class="icon-apply icon-white" aria-hidden="true"></span>'. JText::_('Save') . '</button>';
    JToolbar::getInstance('toolbar')->prependButton('Custom', $buttonHtml, 'applyZas');

    // LOAD TRANSLATION STRINGS
    // TODO: TRANSLATIONS WITH PARAMETERS?
    JText::script('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_EMPTY');
    JText::script('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_ERROR');
    JText::script('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_INVALID_RANGE');

    foreach( $model->getCountries(true) as $key => $value ){
        JText::script('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_'.strtoupper($key).'_EMPTY');
        JText::script('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_'.strtoupper($key).'_OVERLAP');
    };
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

<!-- weight rules part -->
<fieldset class="global-default-rules">
    <legend><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_RULES_GLOBAL'); ?></legend>
    <div>
        <label style="color: red">
            (*) : <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_REQIRED'); ?>.
        </label>
    </div>
    <div>
        <label for="input_maximum_weight" class="col-lg-2 control-label"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_MAX_WEIGHT'); ?>: <span style="color: red">*</span></label>
        <?php echo VmHTML::input('global[values][maximum_weight]', $model->getConfig('global/values/maximum_weight', VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT), 'data-name="maximum_weight" class="required"'); ?>
        <label style="margin-bottom: 10px">
            <small style="color: #6c757d; margin-left: 5px;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_MAX_WEIGHT_LABEL'); ?></small>
        </label>
    </div>
    <div>
        <label for="input_default_price" class="col-lg-2 control-label"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_DEFAULT_PRICE'); ?>: <span style="color: red">*</span></label>
        <?php echo VmHTML::input('global[values][default_price]', $model->getConfig('global/values/default_price', VirtueMartModelZasilkovna::PRICE_DEFAULT), 'data-name="default_price" class="required"'); ?>
        <label style="margin-bottom: 10px">
            <small style="color: #6c757d; margin-left: 5px;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_DEFAULT_PRICE_LABEL'); ?></small>
        </label>
    </div>
    <div>
        <label for="input_free_shipping" class="col-lg-2 control-label"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_FREE_SHIPPING'); ?>:</label>
        <?php echo VmHTML::input('global[values][free_shipping]', $model->getConfig('global/values/free_shipping', VirtueMartModelZasilkovna::FREE_SHIPPING_DEFAULT), 'data-name="free_shipping"'); ?>
        <label style="margin-bottom: 10px">
            <small style="color: #6c757d; margin-left: 5px;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_FREE_SHIPPING_LABEL'); ?></small>
        </label>
    </div>
</fieldset>

<?php foreach($model->getCountries(true) as $countryCode => $options): ?>
    <fieldset class="validate-<?=$countryCode?>-ranges">
        <legend><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_RULES_FOR_'.strtoupper($countryCode)); ?></legend>
        <div>
            <label for="input_default_price" class="col-lg-2 control-label"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_DEFAULT_PRICE'); ?>:</label>
            <?php echo VmHTML::input($countryCode.'[values][default_price]', $model->getConfig($countryCode.'/values/default_price'), 'data-name="default_price"'); ?>
            <label style="margin-bottom: 10px">
                <small style="color: #6c757d; margin-left: 5px;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_DEFAULT_PRICE_LABEL'); ?></small>
            </label>
        </div>

        <div>
            <label for="input_free_shipping" class="col-lg-2 control-label"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_FREE_SHIPPING'); ?>:</label>
            <?php echo VmHTML::input($countryCode.'[values][free_shipping]', $model->getConfig($countryCode.'/values/free_shipping'), 'data-name="free_shipping"'); ?>
            <label style="margin-bottom: 10px">
                <small style="color: #6c757d; margin-left: 5px;"><?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_FREE_SHIPPING_LABEL'); ?></small>
            </label>
        </div>

        <div class="<?php echo($countryCode); ?>_repeater">
            <div class="repeater-heading">
                <button type="button" class="btn btn-primary pt-5 pull-right repeater-add-btn">
                    <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_ADD'); ?>
                </button>
            </div>
            <div class="clearfix"></div>

            <?php

            $rows = array();
            if ( count( $model->getConfig($countryCode) ) > 1 )
                foreach ( $model->getConfig($countryCode) as $key => $value ){
                    // skip the 'free_shipping' key
                    if (!is_numeric($key)) continue;
                    $rows[] = array(
                        'input_weight_from' => VmHTML::input($countryCode.'['.$key.'][weight_from]',  $value['weight_from'], 'data-name="weight_from" class="required"'),
                        'input_weight_to' => VmHTML::input($countryCode.'['.$key.'][weight_to]', $value['weight_to'], 'data-name="weight_to" class="required"'),
                        'input_price' => VmHTML::input($countryCode.'['.$key.'][price]', $value['price'], 'data-name="price" class="required"'),
                        'input_empty' => ''
                    );
                }
            else
                $rows[] = array(
                    'input_weight_from' => VmHTML::input('weight_from',  '', 'data-name="weight_from" class="required"'),
                    'input_weight_to' => VmHTML::input('weight_to', '', 'data-name="weight_to" class="required"'),
                    'input_price' => VmHTML::input('price', '', 'data-name="price" class="required"'),
                    'input_empty' => '<div class="item-empty"></div>'
                );
            foreach ($rows as $row): ?>
                <div class="items" data-group="<?php echo($countryCode); ?>">
                    <div class="item-content">
                        <?php echo $row['input_empty']; ?>
                        <div class="row" style="margin-left:0">
                            <div class="span3 weightFromSpan">
                                <label for="input_weight_from" class="col-lg-2 control-label">
                                    <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_WEIGHT_FROM_INCL'); ?>
                                </label>
                                <?php echo $row['input_weight_from']; ?>
                            </div>
                            <div class="span3 weightToSpan">
                                <label for="input_weight_to" class="col-lg-2 control-label">
                                    <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_WEIGHT_TO'); ?>
                                </label>
                                <?php echo $row['input_weight_to']; ?>
                            </div>
                            <div class="span3 priceSpan">
                                <label for="input_price" class="col-lg-2 control-label">
                                    <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_PRICE'); ?>
                                </label>
                                <?php echo $row['input_price']; ?>
                            </div>
                            <div class="span3">
                                <button style="margin-top:23px" id="remove-btn" class="btn btn-danger" onclick="jQuery(this).parents('.items').remove()">
                                    <?php echo JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_REMOVE'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

<?php endforeach; ?>

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