<?php

use Joomla\CMS\Version;

defined('_JEXEC') or die('Restricted access');

if(!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
if(!class_exists('calculationHelper')) {
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'calculationh.php');
}
if(!class_exists('CurrencyDisplay')) {
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
}
if(!class_exists('VirtueMartModelVendor')) {
    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php');
}


class plgVmShipmentZasilkovna extends vmPSPlugin
{
    const DEFAULT_WEIGHT_UNIT = 'KG';
    const OTHER_CONFIG_CODE = 'other';

    public static $_this = false;
    /** @var VirtueMartModelZasilkovna */
    protected $model;

    function __construct(&$subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->model = VmModel::getModel('zasilkovna');
        $this->handleSessionOnShipmentSubmit();
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @author Valérie Isaksen
     */
    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('zasilkovna');
    }

    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
            'order_number' => 'char(32)',
            'zasilkovna_packet_id' => 'decimal(10,0)',
            'zasilkovna_packet_price' => 'decimal(15,2)',
            'branch_id' => 'decimal(10,0)',
            'branch_currency' => 'char(5)',
            'branch_name_street' => 'varchar(500)',
            'email' => 'varchar(255)',
            'phone' => 'varchar(255)',
            'first_name' => 'varchar(255)',
            'last_name' => 'varchar(255)',
            'address' => 'varchar(255)',
            'city' => 'varchar(255)',
            'zip_code' => 'varchar(255)',
            'virtuemart_country_id' => 'varchar(255)',
            'adult_content' => 'smallint(1) DEFAULT \'0\'',
            'is_cod' => 'smallint(1)',
            'packet_cod' => 'decimal(15,2)',
            'exported' => 'smallint(1)',
            'printed_label' => 'smallint(1) DEFAULT \'0\'',
            'shipment_name' => 'varchar(5000)',
            'shipment_cost' => 'decimal(10,2)',
            'shipment_package_fee' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the shipment-specific data.
     *
     * @param integer $virtuemart_order_id The order Number
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author Valérie Isaksen
     * @author Max Milbers
     */
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {
        if(!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return NULL;
        }

        $shipment_name .= $this->getOrderShipmentHtml($virtuemart_order_id);
    }

    /**
     * Refresh session branch data ()
     */
    function handleSessionOnShipmentSubmit(){
        if(JRequest::getInt('branch_id', 0)){
            $session = JFactory::getSession();
            $session->set('branch_id', JRequest::getInt('branch_id', 0));
            $session->set('branch_currency', JRequest::getVar('branch_currency', ''));
            $session->set('branch_name_street', JRequest::getVar('branch_name_street', ''));
            $session->set('branch_country', JRequest::getVar('branch_country', ''));
        }
    }

    /**
     * This event is fired after the order has been stored; it gets the shipment method-
     * specific data.
     *
     * @return mixed Null when this method was not selected, otherwise true
     * @author Valerie Isaksen
     */
    function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {
        if(!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id)))
            return NULL; // Another method was selected, do nothing
        if(!$this->selectedThisElement($method->shipment_element))
            return false;
        if(!$this->OnSelectCheck($cart))
            return false;

        $currency = $this->model->getCurrencyCode($order['details']['BT']->order_currency);

        // GET PARAMETERS FROM SESSION AND CLEAR
        $session = JFactory::getSession();
        $branch_id = $session->get('branch_id', 0);
        $branch_name_street = $session->get('branch_name_street', '');

        $session->clear('branch_id');
        $session->clear('branch_name_street');

        $codSettings = $this->model->getConfig('zasilkovna_payment_method_'.$cart->virtuemart_paymentmethod_id, 0);

        $billing=$order['details']['BT'];
        $shipping=$order['details']['ST'];
        //    $shippingDetails = $order['details']['ST'];

        // IF BILLING AND SHIPPING DETAILS ARE DIFFERENT USE SHIPPING DETAILS
        if($billing->STsameAsBT == "0" ){
            // FALLBACK TO BILLING CONTACT IF NECESSARY, BECAUSE OF THE POSSIBILITY TO HAVE SHIPPING ADDRESS ENTIRELY WITHOUT CONTACT
            $noShippingContact = (
                empty($shipping->email) &&
                empty($shipping->phone_1) &&
                empty($shipping->phone_2)
            );

            if($noShippingContact){
                $email = $billing->email;
                $phone_1 = $billing->phone_1;
                $phone_2 = $billing->phone_2;
            }else{
                $email = $shipping->email;
                $phone_1 = $shipping->phone_1;
                $phone_2 = $shipping->phone_2;
            }
            $details = $shipping;
        }
        else{
            $email = $billing->email;
            $phone_1 = $billing->phone_1;
            $phone_2 = $billing->phone_2;

            $details = $billing;
        }

        $values['virtuemart_order_id'] = $details->virtuemart_order_id;
        $values['virtuemart_shipmentmethod_id'] = $details->virtuemart_shipmentmethod_id;
        $values['order_number'] = $details->order_number;
        $values['zasilkovna_packet_id'] = 0;
        $values['zasilkovna_packet_price'] = $details->order_total;
        $values['branch_id'] = $branch_id;
        $values['branch_currency'] = $currency;
        $values['branch_name_street'] = $branch_name_street;
        $values['email'] = $email;
        $values['phone'] = $phone_1 ? $phone_1 : $phone_2;
        $values['first_name'] = $details->first_name;
        $values['last_name'] = $details->last_name;
        $values['address'] = $details->address_1;
        $values['city'] = $details->city;
        $values['zip_code'] = $details->zip;
        $values['adult_content'] = 0;
        $values['packet_cod'] = ($codSettings ? $details->order_total : 0);
        $values['is_cod'] = $codSettings; //depends on actual settings of COD payments until its set manually in administration
        $values['exported'] = 0;
        $values['shipment_name'] = $method->shipment_name;
        $values['shipment_cost'] = $this->getCosts($cart, $method, "");
        $values['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($values);

        return true;
    }


    /**
     * calculateSalesPrice
     * overrides default function to remove currency conversion
     *
     * @author Zasilkovna
     */
    function calculateSalesPrice($cart, $method, $cart_prices) {
        $value = $this->getCosts($cart, $method, $cart_prices);

        $tax_id = @$method->tax_id;


        $vendor_id = 1;
        $vendor_currency = VirtueMartModelVendor::getVendorCurrency($vendor_id);

        $db = JFactory::getDBO();
        $calculator = calculationHelper::getInstance();
        $currency = CurrencyDisplay::getInstance();

        $taxrules = array();
        if(!empty($tax_id)) {
            $q = 'SELECT * FROM #__virtuemart_calcs WHERE `virtuemart_calc_id`="' . $tax_id . '" ';
            $db->setQuery($q);
            $taxrules = $db->loadAssocList();
        }

        if(count($taxrules) > 0) {
            $salesPrice = $calculator->roundInternal($calculator->executeCalculation($taxrules, $value));
        }
        else {
            $salesPrice = $value;
        }

        return $salesPrice;
    }

    /**
     * return total cart weight
     *
     * @param $cart
     * @return float|int
     */
    public function getCartWeight($cart){
        $conversionArray = array(
            'KG'   => 1,
            'G'    => 1000,
            'MG'   => 1000000,
            'LB'   => 2.20462262,
            'OZ'   => 35.2739619
        );

        $totalWeight = 0;

        foreach ($cart->products as $product){
            if( isset($conversionArray[$product->product_weight_uom]) ){
                $totalWeight += (($product->product_weight/$conversionArray[$product->product_weight_uom])*$product->quantity);
            }else{
                $totalWeight += ($product->product_weight*$product->quantity);
            }
        }
        return $totalWeight;
    }

    /**
     * @return string versionString
     */
    function getVersionString(){
        $versionStrings = array(
            'joomla'        => "Joomla-".Joomla\CMS\Version::MAJOR_VERSION.".".Joomla\CMS\Version::MINOR_VERSION,
            'virtuemart'    => "VirtueMart-".vmVersion::$RELEASE,
            'module'        => "Packeta-".VirtueMartModelZasilkovna::VERSION,
        );

        return implode('-', $versionStrings);
    }


    /**
     * Return delivery price for weight, NULL if none found.
     * @param array $config
     * @param $weight
     * @return float|null
     */
    protected function getRatePriceFromConfig(array $config, $weight)
    {
        // Load default price from configuration.
        $defaultPrice = (isset($config['values']) && isset($config['values']['default_price']) && is_numeric($config['values']['default_price']))
            ? (float) $config['values']['default_price']
            : NULL;

        // Remove default price settings from configuration.
        unset($config['values']);

        // Search for satisfying weight range.
        foreach ($config as $weightRate)
        {
            if ($weight >= round($weightRate['weight_from'],2) && $weight < round($weightRate['weight_to'],2) && is_numeric($weightRate['price']))
            {
                return (float) $weightRate['price'];
            }
        }

        // Return default delivery price value or NULL if no definition found.
        return $defaultPrice;
    }


    /**
     * Return country code from order billing address.
     * @param VirtueMartCart $cart
     * @return string|null
     */
    protected function getOrderBillingAddressCountryCode(VirtueMartCart $cart)
    {
        // If user set Shipping address same as Billing we take the billing address
        // (shipping address may contain other data but that is discarded)
        /** @var array $address */
        $address = $this->getAddressFromCart($cart);

        // No country id is found in billing address.
        if (!is_array($address) || !isset($address['virtuemart_country_id']))
        {
            return NULL;
        }

        // Get country code.
        $cid  = $address['virtuemart_country_id'];
        $code = strtolower(ShopFunctions::getCountryByID($cid, 'country_2_code'));

        // Return country code.
        return $code ? $code : NULL;
    }


    /**
     * Check if order has available free shipping.
     * @param VirtueMartCart $cart
     * @param $cart_prices
     * @return bool
     */
    protected function isFreeShippingActive(VirtueMartCart $cart, $cart_prices)
    {
        // Billing address country code is required to free shipping.
        $countryCode = $this->getOrderBillingAddressCountryCode($cart);

        if ($countryCode === NULL)
        {
            return FALSE;
        }

        // Load order price.
        if (empty($cart_prices['salesPrice']) || !is_numeric($cart_prices['salesPrice']))
        {
            return FALSE;
        }

        $orderPrice = (float) $cart_prices['salesPrice'];

        // 1) Check if country free shipping criteria is met.
        $countryLimit = $this->model->getConfig($countryCode . '/values/free_shipping');

        if (is_numeric($countryLimit) && $orderPrice >= (float) $countryLimit)
        {
            return TRUE;
        }

        // 2) Check if "other country" free shipping criteria is met.
        $otherCountryLimit = $this->model->getConfig(self::OTHER_CONFIG_CODE . '/values/free_shipping');

        if (is_numeric($otherCountryLimit) && $orderPrice >= (float) $otherCountryLimit)
        {
            return TRUE;
        }

        // 3) Check if default free shipping criteria is met.
        $defaultLimit = $this->model->getConfig('global/values/free_shipping');

        if (is_numeric($defaultLimit) && $orderPrice >= (float) $defaultLimit)
        {
            return TRUE;
        }

        // 4) Free shipping is not available.
        return FALSE;
    }


    /**
     * Get Zasilkovna delivery price.
     * @param VirtueMartCart $cart
     * @param $method
     * @param $cart_prices
     * @return float delivery cost for the shipping method instance
     */
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        // Load default price from global config.
        $defaultPrice = $this->model->getConfig('global/values/default_price');

        if (!$defaultPrice || !is_numeric($defaultPrice))
        {
            // Is safe set default price to 0, because if price = 0 and
            // free shipping is not active this delivery method is disabled!
            $defaultPrice = 0.0;
        }
        else
        {
            // Default price must be float.
            $defaultPrice = (float) $defaultPrice;
        }

        // Load order billing address country code.
        $code = $this->getOrderBillingAddressCountryCode($cart);

        // If no code (address) is set return global default price or 0.
        if ($code === NULL)
        {
            return $defaultPrice;
        }

        // Calculate total weight of the order package.
        $totalWeight = round($this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT),2);

        // 1) Check if is free shipping criteria meet.
        if ($this->isFreeShippingActive($cart, $cart_prices))
        {
            return 0.0;
        }

        // 2) Try calculate country delivery price for weight.
        $langConfig = $this->model->getConfig($code, array());
        $langPrice  = $this->getRatePriceFromConfig($langConfig, $totalWeight);

        if ($langPrice !== NULL)
        {
            return $langPrice;
        }

        // 3) Try calculate delivery price for weight from "other country" definition.
        $otherConfig = $this->model->getConfig(self::OTHER_CONFIG_CODE, array());
        $otherPrice  = $this->getRatePriceFromConfig($otherConfig, $totalWeight);

        if ($otherPrice !== NULL)
        {
            return $otherPrice;
        }

        // 4) Return default delivery price.
        return $defaultPrice;
    }


    /**
     * Is delivery available?
     * @param VirtueMartCart $cart
     * @param int $method
     * @param array $cart_prices
     * @return bool
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        // Check order max weight (TODO: duplicate with plgVmDisplayListFEShipment).
        $orderMaxWeight = $this->model->getConfig('global/values/max_weight', VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT);
        $orderActualWeight = $this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT);

        if($orderActualWeight > $orderMaxWeight)
        {
            return FALSE;
        }

        // intentionally ==
        return !($this->getCosts($cart, $method, $cart_prices) == 0 && !$this->isFreeShippingActive($cart, $cart_prices));
    }


    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallShipmentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the shipment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart : the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckShipment(VirtueMartCart &$cart) {

        if(!$this->selectedThisByMethodId($cart->virtuemart_shipmentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }
        if(!($method = $this->getVmPluginMethod($cart->virtuemart_shipmentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }

        if($this->OnSelectCheck($cart)) {
            return true;
        }

        return false;
    }

    /**
     * plgVmDisplayListFE
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEShipment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        $js_html = '';
        if($this->getPluginMethods($cart->vendorId) === 0)return FALSE;

        // DO NOT DISPLAY OPTION IF CART WEIGHT OVER GLOBAL LIMIT
        $weight = $this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT);
        $maxWeight = $this->model->getConfig('global/values/max_weight', VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT);

        if($weight > $maxWeight)
        {
            return FALSE;
        }

        $document = JFactory::getDocument();

        $document->addStyleSheet('media/com_zasilkovna/media/css/packetery.css?v=' . filemtime(__DIR__ . '/../../../media/com_zasilkovna/media/css/packetery.css'));

        // If user set Shipping address same as Billing we take the billing address
        // (shipping address may contain other data but that is discarded)
        $address = $this->getAddressFromCart($cart);

        // GET CODE OF SELECTED COUNTRY
        $code = '';
        if( isset( $address['virtuemart_country_id'] ) )
        {
            $code = strtolower(ShopFunctions::getCountryByID($address['virtuemart_country_id'], 'country_2_code'));
        }

        // If the country stored in session is different from the one in the address
        // we clear session variables = the pickup point is deselected
        $session = JFactory::getSession();
        $countrySession = $session->get('branch_country', '');
        if ($countrySession !== $code)
        {
            $session->clear('branch_id');
            $session->clear('branch_name_street');
            $session->clear('branch_country');
        }

        // COUNTRY/LANG ARRAY
        $opt = $this->model->getCountries();

        // DECLARE JAVASCRIPT VARIABLES
        if (isset($opt[$code]))
        {
            // Set the country code
            $options = $opt[$code];
        }
        else
        {
            // If country code not among allowed codes, Zasilkovna will not be displayed as a shipping option
            return FALSE;
        }

        if( isset( $address['address_1'] ) && isset( $address['city'] )  )
        {
            $options['address'] = $address['address_1'] . '' . $address['city'];
        }

        $countrySelected = isset( $address['virtuemart_country_id'] );

        $lang = JFactory::getLanguage();
        $langCode = substr($lang->getTag(), 0, strpos($lang->getTag(), '-'));

        if (!empty($this->model->errors))
        {
            //api key or smth is wrong - more info shows in administration
            return false;
        }

        $html = array();
        $method_name = $this->_psType . '_name';

        // ADD WIDGET JAVASCRIPT AND HIDDEN FIELDS
        $js_html .= "<script type=\"text/javascript\">
            var packetaApiKey = '{$this->model->api_key}';
            var country = '{$options['country']}';
            var language = '{$langCode}';
            var address = '{$options['address']}';
            var version = '{$this->getVersionString()}';
            var countrySelected = '{$countrySelected}';
        </script>";
        $js_html .= '<script src="https://widget.packeta.com/www/js/library.js"></script>';
        $js_html .= '<script src="media/com_zasilkovna/media/js/widget.js?v=' . filemtime(__DIR__ . '/../../../media/com_zasilkovna/media/js/widget.js') . '"></script>';

        $js_html .= "<div class='zasilkovna_box'>";
        $js_html .= ('<input type="hidden" name="branch_id" id="branch_id" value="'. $session->get('branch_id', 0) .'" >');
        $js_html .= ('<input type="hidden" name="branch_name_street" id="branch_name_street" value="'. $session->get('branch_name_street', '') .'" >');
        $js_html .= ('<input type="hidden" name="branch_country" id="branch_country" value="'. $session->get('branch_country', '') .'" >');
        $jsHtmlIsSet = false;


        if(!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }


        foreach($this->methods as $key => $method) {
            $countries = array();
            if(!empty($method->countries)) {
                if(!is_array($method->countries)) {
                    $countries[0] = $method->countries;
                }
                else {
                    $countries = $method->countries;
                }
            }
            if(count($countries) && !in_array($address['virtuemart_country_id'], $countries)) {
                continue;
            }

            $html[$key] = '';


            $selectedPayment = (empty($cart->virtuemart_paymentmethod_id) ? 0 : $cart->virtuemart_paymentmethod_id);
            if($jsHtmlIsSet == false) {

                //$shipmentID = $method->virtuemart_shipmentmethod_id;
                //$configRecordName = 'zasilkovna_combination_payment_' . $selectedPayment . '_shipment_' . $shipmentID;

                // @TODO Temp workaround with default value 1, shipment and payment restrictions need to be processed
                //if( $this->model->getConfig($configRecordName, 1) || $selectedPayment == 0) {
                    $html[$key] .= $js_html;
                    $jsHtmlIsSet = true;
                //}
            }

            $country = $method->country;

            if($this->checkConditions($cart, $method, $cart->pricesUnformatted)) {
                $html[$key] .= '<div id="zasilkovna_div" name="helper_div">';//this div packs the select box with radio input - helps js easily find the radio
                $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->pricesUnformatted);
                $method->$method_name = $this->renderPluginName($method);
                $method->$method_name .= ' - ' . JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SHIPPING_TO_' . strtoupper($options['country']));
                $html[$key] .= $this->getPluginHtml($method, $selected, $methodSalesPrice);
                $selected_id_attr = 'selected-id=' . $_SESSION['branch_id'];

                $html[$key] .= '<div class="zas-box"> ';
                if( isset( $address['virtuemart_country_id'] ) ){
                    $html[$key] .= ('
                        <div class="zasilkovna-logo"></div>
                        <a href="javascript:void(0)" id="open-packeta-widget">'. JText::_('PLG_VMSHIPMENT_ZASILKOVNA_WIDGET_SELECT_POINT') .'</a>
                        <iframe sandbox="allow-scripts allow-same-origin" allow="geolocation" id="packeta-widget"></iframe>
                        <br>
                        <ul><li>'. JText::_('PLG_VMSHIPMENT_ZASILKOVNA_WIDGET_SELECTED_POINT') .': <span id="picked-delivery-place">'.$session->get('branch_name_street', '').'</span></li></ul>');
                }else{
                    $html[$key] .= '<ul><li>'. JText::_('PLG_VMSHIPMENT_ZASILKOVNA_WIDGET_ENTER_ADDRESS') .'</li></ul>';
                }
                $html[$key] .= '</div>';

                $html[$key] .= '</select></div>';
            }
        }

        if(empty($html)) {
            return FALSE;
        }

        $htmlIn[] = $html;

        return TRUE;
    }

    /**
     * This method is fired when showing the order details in the backend.
     * It displays the shipment-specific data.
     * NOTE, this plugin should NOT be used to display form fields, since it's called outside
     * a form! Use plgVmOnUpdateOrderBE() instead!
     *
     * @param integer $virtuemart_order_id The order ID
     * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderBEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id) {

        if(!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return NULL;
        }
        $html = $this->getOrderShipmentHtml($virtuemart_order_id);

        return $html;
    }

    /**
     * @param $virtuemart_order_id
     * @return string
     * @author zasilkovna
     */
    function getOrderShipmentHtml($virtuemart_order_id) {

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
            . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if(!($shipinfo = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());

            return '';
        }

        if(!class_exists('CurrencyDisplay')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
        }

        $currency = CurrencyDisplay::getInstance();
        $tax = ShopFunctions::getTaxByID($shipinfo->tax_id);
        $taxDisplay = is_array($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
        $taxDisplay = ($taxDisplay == -1) ? JText::_('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

        $html = '<table class="adminlist">' . "\n";

        JFactory::getLanguage()->load('plg_vmshipment_zasilkovna');
        $html .= $this->getHtmlRowBE('PLG_VMSHIPMENT_ZASILKOVNA_SHIPPING_NAME', $shipinfo->shipment_name);
        $html .= $this->getHtmlRowBE('PLG_VMSHIPMENT_ZASILKOVNA_BRANCH', $shipinfo->branch_name_street);
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_CURRENCY', $shipinfo->branch_currency);

        $html .= '</table>' . "\n";

        return $html;
    }

    public function plgVmonSelectedCalculatePriceShipment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelected
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,    virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedShipment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $order_number The order ID
     * @param integer $virtuemart_shipmentmethod_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrint($order_number, $virtuemart_shipmentmethod_id) {
        if(!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return NULL;
        }

        $html = $this->onShowOrderPrint($order_number, $virtuemart_shipmentmethod_id);

        return $html;
    }

    function plgVmDeclarePluginParamsShipment($name, $id, &$data) {
        return $this->declarePluginParams('shipment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsShipment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function plgVmDeclarePluginParamsShipmentVM3(&$data) {
        return $this->declarePluginParams('shipment', $data);
    }


    /**
     * If user set Shipping address same as Billing we take the billing address
     * (shipping address may contain other data but that is discarded)
     * @param VirtueMartCart $cart
     * @return array|int|mixed|string
     */
    private function getAddressFromCart(VirtueMartCart $cart)
    {
        return 1 === (int) $cart->STsameAsBT ? $cart->BT : $cart->getST();
    }

}
