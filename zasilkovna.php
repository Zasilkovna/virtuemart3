<?php

use Joomla\CMS\Toolbar\Button\LinkButton;
use Joomla\CMS\Toolbar\Button\SeparatorButton;
use VirtueMartModelZasilkovna\ShipmentMethod;
use VirtueMartModelZasilkovna\Carrier\VendorGroups;

defined('_JEXEC') or die('Restricted access');
defined('PACKETERY_MEDIA_DIR') || define('PACKETERY_MEDIA_DIR', __DIR__ . '/../../../media/com_zasilkovna/media');

spl_autoload_register(
    function ($className) {
        $className = ltrim($className, '\\');
        $parts = explode('\\', $className);
        $path = JPATH_ADMINISTRATOR . '/components/com_virtuemart/models/zasilkovna_src/' . implode('/', $parts) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
);

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

require_once VMPATH_ADMIN . '/fields/vmzasilkovnacountries.php';
require_once VMPATH_ADMIN . '/fields/vmzasilkovnahdcarriers.php';

class plgVmShipmentZasilkovna extends vmPSPlugin
{
    const DEFAULT_WEIGHT_UNIT = 'KG';
    const TEMPLATES_DIR = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'views' . DS . 'zasilkovna' . DS . 'tmpl';
    const TRACKING_URL = 'https://tracking.app.packeta.com/%s';
    const ZASILKOVNA_OLD_RECREATE_KEY_VALUE = 'zasilkovna_old_recreate_key_value';

    public static $_this = false;

    /** @var VirtueMartModelZasilkovna */
    protected $model;

    /** @var \Joomla\CMS\Session\Session */
    protected $session;

    /** @var \VirtueMartModelZasilkovna\CheckoutModuleDetector */
    protected $checkoutModuleDetector;

    /** @var \VirtueMartModelZasilkovna\ShipmentMethodStorage */
    private $shipmentMethodStorage;

    /** @var \VirtueMartModelZasilkovna\ShipmentMethodValidator */
    protected $shipmentMethodValidator;

    /** @var \VirtueMartModelZasilkovna\Order\Detail */
    protected $orderDetail;

    /** @var \VirtueMartModelZasilkovna\Order\Repository */
    protected $orderRepository;

    /** @var \VirtueMartModelZasilkovna\Box\Renderer */
    protected $renderer;

    /** @var \VirtueMartModelZasilkovna\Carrier\Repository */
    protected $carrierRepository;

    /**
     * plgVmShipmentZasilkovna constructor.
     *
     * @param $subject
     * @param $config
     */
    public function __construct(&$subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        self::addVarsToPushCore($varsToPush,0);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->setConvertable(
            [
                'min_amount',
                'max_amount',
                'shipment_cost' // see convertToVendorCurrency method
            ]
        );
        $this->model = VmModel::getModel('zasilkovna');
        $this->session = JFactory::getSession();
        $this->checkoutModuleDetector = new \VirtueMartModelZasilkovna\CheckoutModuleDetector();
        $this->shipmentMethodStorage = new \VirtueMartModelZasilkovna\ShipmentMethodStorage($this->session);
        $this->shipmentMethodValidator = new \VirtueMartModelZasilkovna\ShipmentMethodValidator();
        $this->orderDetail = new \VirtueMartModelZasilkovna\Order\Detail();
        $this->orderRepository = new \VirtueMartModelZasilkovna\Order\Repository();
        $this->renderer = new \VirtueMartModelZasilkovna\Box\Renderer();
        $this->carrierRepository = new \VirtueMartModelZasilkovna\Carrier\Repository();
    }

    /**
     * @param $type
     * @param $name
     * @param $render
     */
    public function plgVmOnSelfCallFE($type, $name, &$render) {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = JFactory::getApplication();

        // JInput object
        $input = $app->input;
        $task = $input->get('task', 'none', 'string');

        $method = 'handle' . ucfirst($task);
        if (method_exists($this, $method)) {
            $render = call_user_func_array([$this, $method], []);
        }
    }

    /**
     * @return \JResponseJson
     */
    public function handleSaveSelectedPoint() {
        $app = JFactory::getApplication();
        $branchId = $app->input->getInt('branch_id');
        if ($branchId) {
            $methodId = $app->input->getInt('shipment_id', 0);
            $this->shipmentMethodStorage->set($methodId, 'branch_id', $branchId);
            $this->shipmentMethodStorage->set($methodId, 'branch_currency', $app->input->getString('branch_currency', ''));
            $this->shipmentMethodStorage->set($methodId, 'branch_name_street', $app->input->getString('branch_name_street', ''));
            $this->shipmentMethodStorage->set($methodId, 'branch_country', $app->input->getString('branch_country', ''));
            $this->shipmentMethodStorage->set($methodId, 'branch_carrier_id', $app->input->getString('branch_carrier_id', ''));
            $this->shipmentMethodStorage->set($methodId, 'branch_carrier_pickup_point', $app->input->getString('branch_carrier_pickup_point', ''));
        }

        $response = (object)[
            'status' => 'ok',
        ];

        return new JResponseJson($response);
    }

    public function handleProvideCheckoutTailBlockJsFile() {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = JFactory::getApplication();
        $app->setHeader('Content-Type', 'application/javascript', true);
        $app->sendHeaders();

        $activeCheckout = $this->checkoutModuleDetector->getActiveCheckout();
        $jsFile = $activeCheckout->getTailBlockJs();
        if (is_file($jsFile)) {
            echo file_get_contents($jsFile);
        } else {
            http_response_code(404);
        }

        jExit();
    }

    /**
     * Updates carriers.
     */
    public function handleUpdateCarriers() {
        $app = JFactory::getApplication();
        $token = $app->input->getString('token', '');
        $expectedToken = $this->model->getConfig('cron_token');

        if ($token !== $expectedToken) {
            jExit();
        }

        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $model->updateCarriers();

        foreach ($model->errors as $error) {
            echo $error;
            echo '<br>';
        }

        if (empty($model->errors)) {
            echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATED');
            echo '<br>';
        }

        jExit();
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @author Valérie Isaksen
     */
    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('zasilkovna');
    }

    /**
     * Get plugin table fields definition.
     *
     * @return array
     */
    public function getTableSQLFields() {
        $updater = new GenericTableUpdater();
        $tableDefinitions = $updater->getTablesBySql(__DIR__ . '/install.sql');
        $pluginTableDefinition = $tableDefinitions['#__virtuemart_shipment_plg_zasilkovna'];
        return $pluginTableDefinition[0];
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
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name)
    {
        if (!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return null;
        }

        $order = $this->orderRepository->getOrderByVmOrderId($virtuemart_order_id);
        if ($order->isHomeDelivery()) {
            $this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
        } else {
            $shipment_name .= $this->getOrderShipmentHtml($order);
        }
    }

    /**
     * @param string|int $methodId
     */
    public function clearPickedDeliveryPoint($methodId) {
        $this->shipmentMethodStorage->clear($methodId, 'branch_id');
        $this->shipmentMethodStorage->clear($methodId, 'branch_currency');
        $this->shipmentMethodStorage->clear($methodId, 'branch_name_street');
        $this->shipmentMethodStorage->clear($methodId, 'branch_country');
        $this->shipmentMethodStorage->clear($methodId, 'branch_carrier_id');
        $this->shipmentMethodStorage->clear($methodId, 'branch_carrier_pickup_point');
    }

    /**
     * This event is fired after the order has been stored; it gets the shipment method-
     * specific data.
     *
     * @return mixed Null when this method was not selected, otherwise true
     * @author Valerie Isaksen
     */
    function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->shipment_element)) {
            return false;
        }

        if (!$this->OnSelectCheck($cart)) {
            return false;
        }

        $zasMethod = ShipmentMethod::fromRandom($method);
        $currency = $this->model->getCurrencyCode($order['details']['BT']->order_currency);

        // GET PARAMETERS FROM SESSION AND CLEAR
        $branch_id = $this->shipmentMethodStorage->get($cart->virtuemart_shipmentmethod_id, 'branch_id', 0);
        $branch_name_street = $this->shipmentMethodStorage->get($cart->virtuemart_shipmentmethod_id, 'branch_name_street', '');
        $branch_carrier_id = $this->shipmentMethodStorage->get($cart->virtuemart_shipmentmethod_id, 'branch_carrier_id');
        $branch_carrier_pickup_point = $this->shipmentMethodStorage->get($cart->virtuemart_shipmentmethod_id, 'branch_carrier_pickup_point');

        $this->clearPickedDeliveryPoint($cart->virtuemart_shipmentmethod_id);

        $codSettings = $this->model->getConfig('zasilkovna_payment_method_'.$cart->virtuemart_paymentmethod_id, 0);

        $billing=$order['details']['BT'];
        $shipping=$order['details']['ST'];
        //    $shippingDetails = $order['details']['ST'];

        // IF BILLING AND SHIPPING DETAILS ARE DIFFERENT USE SHIPPING DETAILS
        if((int)$billing->STsameAsBT === 0){
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

        // external pickup point support
        if (empty($branch_carrier_id)) {
            $is_carrier = 0;
            $branch_carrier_pickup_point = ''; // VirtueMart is unable to handle null values
        } else {
            $branch_id = $branch_carrier_id;
            $is_carrier = 1;
        }

        if ($zasMethod->isHdCarrier()) {
            $carrier = $this->carrierRepository->getCarrierById($zasMethod->getHdCarrierId()) ;
            if (!$carrier) {
                return false;
            }

            $is_carrier = 1;
            $branch_id = $carrier->id;
            $branch_name_street = $carrier->name ?: $carrier->id;
        }

        $values['virtuemart_order_id'] = $details->virtuemart_order_id;
        $values['virtuemart_shipmentmethod_id'] = $details->virtuemart_shipmentmethod_id;
        $values['order_number'] = $details->order_number;
        $values['zasilkovna_packet_id'] = 0;
        $values['zasilkovna_packet_price'] = $details->order_total;
        $values['branch_id'] = $branch_id;
        $values['branch_currency'] = $currency;
        $values['branch_name_street'] = $branch_name_street;
        $values['is_carrier'] = $is_carrier;
        $values['carrier_pickup_point'] = $branch_carrier_pickup_point;
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
        $values['shipment_cost'] = $this->getCosts($cart, ShipmentMethod::fromRandom($method), "");
        $values['weight'] = $this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT);
        $values['tax_id'] = $method->tax_id;
        $this->orderRepository->insertOrder($values);

        return true;
    }

    /**
     * @param $method
     */
    function convertToVendorCurrency(&$method){
        if(!isset($method->converted) && isset($method->currency_id)){
            $currencyId = $method->currency_id;
            $method->min_amount = $this->convertValueToVendorCurrency($method->min_amount, $currencyId);
            $method->max_amount = $this->convertValueToVendorCurrency($method->max_amount, $currencyId);
            $method->shipment_cost = $this->convertValueToVendorCurrency($method->shipment_cost, $currencyId);
            $method->free_shipment = $this->convertValueToVendorCurrency($method->free_shipment, $currencyId);

            $rulesFE = ($method->globalWeightRules ?: []);
            foreach ($rulesFE as &$globalWeightRule) {
                $globalWeightRule->price = $this->convertValueToVendorCurrency($globalWeightRule->price, $currencyId);
            }

            $rules2FE = ($method->pricingRules ?: []);
            foreach ($rules2FE as &$pricingRule) {
                $pricingRule->shipment_cost = $this->convertValueToVendorCurrency($pricingRule->shipment_cost, $currencyId);
                $pricingRule->free_shipment = $this->convertValueToVendorCurrency($pricingRule->free_shipment, $currencyId);

                $rules3 = ($pricingRule->weightRules ?: []);
                foreach ($rules3 as &$weightRule) {
                    $weightRule->price = $this->convertValueToVendorCurrency($weightRule->price, $currencyId);
                }
            }

            $method->converted = 1;
        }
    }

    /**
     * @param $value
     * @param $currency_id
     * @return mixed
     */
    function convertValueToVendorCurrency($value, $currency_id)
    {
        $calculator = calculationHelper::getInstance ();
        if(!empty($value)){
            return $calculator->_currencyDisplay->convertCurrencyTo($currency_id, $value, true);
        }

        return $value;
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
     * @param int $countryId
     * @param ShipmentMethod $method
     * @param float $weight
     * @return float|null
     */
    protected function resolveCountryPrice($countryId, ShipmentMethod $method, $weight)
    {
        $hasCountryConfig = $method->hasPricingRuleForCountry($countryId);

        if ($hasCountryConfig) {
            $weightRules = $method->getCountryWeightRules($countryId);
            if ($weightRules) {
                $weightRule = $method->resolveWeightRule($weightRules, $weight);
                if ($weightRule) {
                    return (float) $weightRule->price;
                }
            }

            return $method->getCountryDefaultPrice($countryId);
        }

        $weightRules = $method->getGlobalWeightRules();
        if ($weightRules) {
            $weightRule = $method->resolveWeightRule($weightRules, $weight);
            if ($weightRule) {
                return (float) $weightRule->price;
            }
        }

        return null;
    }

    /**
     * Return country code from order billing address.
     * @param VirtueMartCart $cart
     * @return string|null
     */
    protected function getOrderBillingAddressCountryId(VirtueMartCart $cart)
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

        // Return country code.
        return ($cid ? $cid : NULL);
    }

    /**
     * Check if order has available free shipping.
     *
     * @param VirtueMartCart $cart
     * @param $cart_prices
     * @param ShipmentMethod $method
     * @return bool
     */
    protected function isFreeShippingActive(VirtueMartCart $cart, $cart_prices, ShipmentMethod $method)
    {
        // Billing address country code is required to free shipping.
        $countryId = $this->getOrderBillingAddressCountryId($cart);

        if ($countryId === NULL) {
            return FALSE;
        }

        // Load order price.
        if (empty($cart_prices['salesPrice']) || !is_numeric($cart_prices['salesPrice'])) {
            return FALSE;
        }

        $orderPrice = (float)$cart_prices['salesPrice'];

        // 1) Check if country free shipping criteria is met.
        $countryLimit = $method->getCountryFreeShipping($countryId);

        // if country limit then override global free shipment
        if ($countryLimit !== null) {
            if (is_numeric($countryLimit) && $orderPrice >= (float)$countryLimit) {
                return true;
            }

            return false;
        }

        // 3) Check if default free shipping criteria is met.
        $defaultLimit = $method->getGlobalFreeShipping();

        if (is_numeric($defaultLimit) && $orderPrice >= (float)$defaultLimit) {
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
        $this->convertToVendorCurrency($method);
        $method = ShipmentMethod::fromRandom($method);
        $defaultPrice = $method->getGlobalDefaultPrice();
        // Load default price from global config.

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
        $code = $this->getOrderBillingAddressCountryId($cart);

        // If no code (address) is set return global default price or 0.
        if ($code === NULL)
        {
            return $defaultPrice;
        }

        // Calculate total weight of the order package.
        $totalWeight = round($this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT),2);

        // 1) Check if is free shipping criteria meet.
        if ($this->isFreeShippingActive($cart, $cart_prices, $method))
        {
            return 0.0;
        }

        // 2) Try calculate country delivery price for weight.
        $resolvedPrice = $this->resolveCountryPrice($code, $method, $totalWeight);

        if ($resolvedPrice !== null)
        {
            return $resolvedPrice;
        }

        // 4) Return default delivery price.
        return $defaultPrice;
    }


    /**
     * Is delivery available?
     * @param VirtueMartCart $cart
     * @param TableShipmentmethods $method
     * @param array $cart_prices
     * @return bool
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convertToVendorCurrency($method);
        $method = ShipmentMethod::fromRandom($method);
        // Check order max weight (TODO: duplicate with plgVmDisplayListFEShipment).
        $orderMaxWeight = ($method->getGlobalMaxWeight() ?: VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT);
        $orderActualWeight = $this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT);

        if ($orderActualWeight > $orderMaxWeight) {
            return false;
        }

        $deliveryCost = (int)$this->getCosts($cart, $method, $cart_prices);
        $isFreeShippingActive = $this->isFreeShippingActive($cart, $cart_prices, $method);
        if (($deliveryCost === 0 && !$isFreeShippingActive) === true) {
            return false;
        }

        return parent::checkConditions($cart, $method->getParams(), $cart_prices);
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
        $return = $this->onStoreInstallPluginTable($jplugin_id);

        $oldRecreateIndexes = VmConfig::get(self::ZASILKOVNA_OLD_RECREATE_KEY_VALUE, -1);
        if ($oldRecreateIndexes !== -1) {
            VmConfig::set('reCreaKey', $oldRecreateIndexes);
        }

        VmConfig::set(self::ZASILKOVNA_OLD_RECREATE_KEY_VALUE, -1);

        return $return;
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
     * @param VirtueMartCart $cart
     * @return bool|null
     */
    public function plgVmOnCheckoutCheckDataShipment(VirtueMartCart $cart) {
        if(!($method = $this->getVmPluginMethod($cart->virtuemart_shipmentmethod_id))) {
            return null;
        }

        if ($method->shipment_element === VirtueMartModelZasilkovna::PLG_NAME) {
            $zasMethod = ShipmentMethod::fromRandom($method);
            return $zasMethod->isHdCarrier() || $this->hasPointSelected($cart->virtuemart_shipmentmethod_id);
        }

        return null;
    }

    /** Has session branch id selected
     * @param string|int $methodId
     * @return bool
     */
    public function hasPointSelected($methodId)
    {
        $branchId = $this->shipmentMethodStorage->get($methodId, 'branch_id');
        return !empty($branchId);
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
    public function plgVmDisplayListFEShipment(VirtueMartCart $cart, $selected, &$htmlIn) {
        $js_html = '';
        if ($this->getPluginMethods($cart->vendorId) === 0) {
            return FALSE;
        }

        // DO NOT DISPLAY OPTION IF CART WEIGHT OVER GLOBAL LIMIT
        $weight = $this->getOrderWeight($cart, self::DEFAULT_WEIGHT_UNIT);

        $document = JFactory::getDocument();

        $document->addStyleSheet($this->model->_media_url . 'css/packetery.css?v=' . filemtime($this->model->_media_path . 'css/packetery.css'));

        // If user set Shipping address same as Billing we take the billing address
        // (shipping address may contain other data but that is discarded)
        $address = $this->getAddressFromCart($cart);

        // GET CODE OF SELECTED COUNTRY
        $countryCode = '';
        if( isset( $address['virtuemart_country_id'] ) )
        {
            $countryCode = strtolower(ShopFunctions::getCountryByID($address['virtuemart_country_id'], 'country_2_code'));
        }

        // If the country stored in session is different from the one in the address
        // we clear session variables = the pickup point is deselected
        $shipmentIds = $this->model->getShipmentMethodIds();
        if ($shipmentIds) {
            foreach ($shipmentIds as $shipmentId) {
                $countrySession = $this->shipmentMethodStorage->get($shipmentId, 'branch_country', '');
                if ($countrySession !== '' && $countrySession !== $countryCode) {
                    $this->clearPickedDeliveryPoint($shipmentId);
                }
            }
        }

        $lang = JFactory::getLanguage();
        $langCode = substr($lang->getTag(), 0, strpos($lang->getTag(), '-'));

        if (!empty($this->model->errors))
        {
            //api key or smth is wrong - more info shows in administration
            return false;
        }

        $html = array();
        $method_name = $this->_psType . '_name';

        if(!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }

        $activeCheckout = $this->checkoutModuleDetector->getActiveCheckout();
        $shouldIncludeTailBlock = false;

        foreach($this->methods as $key => $method) {

            $zasMethod = ShipmentMethod::fromRandom($method);
            $maxWeight = ($zasMethod->getGlobalMaxWeight() ?: VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT);
            $isHdCarrier = $zasMethod->isHdCarrier();

            if($weight > $maxWeight)
            {
                continue;
            }

            $countries = array();
            if(!empty($method->countries)) {
                if(!is_array($method->countries)) {
                    $countries[0] = $method->countries;
                }
                else {
                    $countries = $method->countries;
                }
            }
            // intentional type unsafe comparison, handles both string (PHP < 8.1) and int (PHP >= 8.1) returned from db
            if (count($countries) && !in_array($address['virtuemart_country_id'], $countries, false)) {
                continue;
            }

            $carrierId = $isHdCarrier ? $zasMethod->getHdCarrierId() : $zasMethod->getPPCarrierId();

            if ($carrierId) {
                $carrier = $this->carrierRepository->getCarrierById($carrierId);
                if (!$carrier || (int)$carrier->deleted === 1 || ($countryCode !== '' && $carrier->country !== $countryCode)) {
                    continue;
                }
            }

            $html[$key] = '';

            if($this->checkConditions($cart, $method, $cart->cartPrices)) {
                $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->cartPrices);
                $method->$method_name = $this->renderPluginName($method);
                $baseHtml = $this->getPluginHtml($method, $selected, $methodSalesPrice);

                $this->renderer->setTemplate($activeCheckout->getTemplate());

                $widgetVendors = (!$isHdCarrier && $carrierId)
                    ? [self::createWidgetVendor($countryCode, null, $carrierId)]
                    : self::createWidgetVendorsParam(
                        $zasMethod,
                        $countryCode,
                        $this->carrierRepository->getAllActiveCarrierIds(true, $countryCode)
                        );

                $this->renderer->setVariables(
                    [
                        'selectPoint' => \JText::_('PLG_VMSHIPMENT_PACKETERY_WIDGET_SELECT_POINT'),
                        'selectedPoint' => \JText::_('PLG_VMSHIPMENT_PACKETERY_WIDGET_SELECTED_POINT'),
                        'enterAddress' => \JText::_('PLG_VMSHIPMENT_PACKETERY_WIDGET_ENTER_ADDRESS'),
                        'widgetVendors' => json_encode($widgetVendors),
                        'baseHtml' => $baseHtml,
                        'isCountrySelected' => !empty($address['virtuemart_country_id']),
                        'savedBranchNameStreet' =>
                            (string)$this->shipmentMethodStorage->get($method->virtuemart_shipmentmethod_id, 'branch_name_street', ''),
                    ]
                );

                $html[$key] = $isHdCarrier ? $baseHtml : $this->renderer->renderToString();

                if (!$isHdCarrier) {
                    $shouldIncludeTailBlock = true;
                }
            }
        }

        if(empty($html)) {
            return FALSE;
        }

        if ($shouldIncludeTailBlock) {
            $tailBlockHtml = $this->getTailBlockHtml($activeCheckout, $countryCode, $langCode, $carrierId);
            $html[$key] .= $tailBlockHtml;
        }

        $htmlIn[] = $html;

        return TRUE;
    }

    /**
     * @param ShipmentMethod $shipmentMethod
     * @param string $countryCode
     * @param array<int, int|string> $carrierIds
     * @return array
     */
    private static function createWidgetVendorsParam(
        ShipmentMethod $shipmentMethod,
        $countryCode,
        array $carrierIds = []
    ) {
        $widgetVendors = [];
        $vendorGroups = $shipmentMethod->getVendorGroups();

        foreach ($vendorGroups as $vendorGroup) {
            if (!VendorGroups::isGroupPresentInCountry($vendorGroup, $countryCode)) {
                continue;
            }

            $widgetVendors[] = self::createWidgetVendor($countryCode, $vendorGroup, null);
        }

        if (empty($widgetVendors)) {
            return $widgetVendors;
        }

        foreach ($carrierIds as $carrierId) {
            $widgetVendors[] = self::createWidgetVendor($countryCode, null, $carrierId);
        }

        return $widgetVendors;
    }

    /**
     * @param string $countryCode
     * @param string|null $group
     * @param string|null $carrierId
     * @return array<string, bool|string>
     */
    private static function createWidgetVendor($countryCode, $group, $carrierId)
    {
        $widgetVendor = [
            'country' => $countryCode,
            'selected' => true,
        ];

        if ($group !== null && $group !== VendorGroups::ZPOINT) {
            $widgetVendor['group'] = $group;
        }

        if ($carrierId !== null) {
            $widgetVendor['carrierId'] = $carrierId;
        }

        return $widgetVendor;
    }

    /**
     * @param string $task
     * @param array $params
     * @return string
     */
    protected function createSignalUrl($task, array $params = []) {
        $params['task'] = $task;
        $params['option'] = 'com_virtuemart';
        $params['view'] = 'plugin';
        $params['type'] = 'vmshipment';
        $params['name'] = VirtueMartModelZasilkovna::PLG_NAME;
        return Juri::base(true) . '/index.php?' . http_build_query($params);
    }

    /**
     * @param VirtueMartCart $cart
     */
    public function plgVmOnUpdateCart(VirtueMartCart $cart) {
        $virtuemartShipmentMethodId = $cart->virtuemart_shipmentmethod_id;
        if (empty($virtuemartShipmentMethodId)) {
            return null; // shipping method not selected by customer
        }

        $method = $this->getVmPluginMethod($virtuemartShipmentMethodId);
        if (empty($method) || $method->shipment_element !== VirtueMartModelZasilkovna::PLG_NAME) {
            $this->clearPickedDeliveryPoint($virtuemartShipmentMethodId);
            return null; // not Packetery method
        }

        $address = $this->getAddressFromCart($cart);
        if (empty($address) || empty($address['virtuemart_country_id'])) {
            $this->clearPickedDeliveryPoint($virtuemartShipmentMethodId);
            return null; // destination country not specified yet
        }

        $countryCode = strtolower(ShopFunctions::getCountryByID($address['virtuemart_country_id'], 'country_2_code'));
        $sessionCountry = $this->shipmentMethodStorage->get($virtuemartShipmentMethodId, 'branch_country');
        if ($sessionCountry && $countryCode !== $sessionCountry) {
            $this->clearPickedDeliveryPoint($virtuemartShipmentMethodId);
            $cart->virtuemart_shipmentmethod_id = null; // makes selected shipping method disappear
        }

        $zasMethod = ShipmentMethod::fromRandom($method);
        if ($zasMethod->isHdCarrier()) {
            $this->clearPickedDeliveryPoint($virtuemartShipmentMethodId);
            $hdCarrier = $this->carrierRepository->getCarrierById($zasMethod->getHdCarrierId());
            if (!$hdCarrier || (int)$hdCarrier->deleted === 1 || $hdCarrier->country !== $countryCode) {
                $cart->virtuemart_shipmentmethod_id = null; // makes selected shipping method disappear
            }
        }
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
        $document = JFactory::getDocument();
        $document->addStyleSheet(
            sprintf('%smedia/com_zasilkovna/media/css/admin.css?v=%s',
                JUri::root(),
                filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/css/admin.css')
            ));

        $order = $this->orderRepository->getOrderByVmOrderId($virtuemart_order_id);

        if ($order) {
            $this->addPacketaToolbarButtons($order);
        }

        return $this->orderDetail->renderToString($order);
    }

    /**
     * @param \VirtueMartModelZasilkovna\Order\Order $order
     * @return string
     * @author zasilkovna
     */
    function getOrderShipmentHtml(\VirtueMartModelZasilkovna\Order\Order $order)
    {
        if (!$order) {
            return '';
        }

        JFactory::getLanguage()->load('plg_vmshipment_zasilkovna');

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlRowBE('PLG_VMSHIPMENT_PACKETERY_SHIPPING_NAME', $order->getShipmentName());
        if (!$order->isHomeDelivery()) {
            $html .= $this->getHtmlRowBE('PLG_VMSHIPMENT_PACKETERY_BRANCH', $order->getBranchNameStreet());
        }
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_CURRENCY', $order->getBranchCurrency());
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param string $cart_prices_name
     * @return mixed
     */
    public function plgVmOnSelectedCalculatePriceShipment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        $cart->automaticSelectedShipment = false;
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

    /**
     * @param $name
     * @param $id
     * @param $data
     * @return mixed
     */
    function plgVmDeclarePluginParamsShipment($name, $id, &$data) {
        return $this->declarePluginParams('shipment', $name, $id, $data);
    }

    /**
     * @param $name
     * @param $id
     * @param $table
     * @return mixed
     */
    function plgVmSetOnTablePluginParamsShipment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * @param $data
     * @param \TableShipmentmethods $table
     * @return void
     */
    function plgVmSetOnTablePluginShipment(&$data, &$table)
    {
        if (empty($data)) {
            return;
        }

        $isBeingCreated = empty($data['virtuemart_shipmentmethod_id']);
        $isZasilkovna = isset($data['shipment_element']) && $data['shipment_element'] === VirtueMartModelZasilkovna::PLG_NAME;
        $wasZasilkovna = null;
        if (!$isBeingCreated) {
            $persistedMethod = $this->getPluginMethod($data['virtuemart_shipmentmethod_id']);
            if ($persistedMethod) {
                if (version_compare(vmVersion::$RELEASE, '4', '>=')) {
                    $table->_varsToPushParam = $persistedMethod->_varsToPushParam;
                    foreach ($table->_varsToPushParam as $paramKey => $paramValue) {
                        if (!isset($table->{$paramKey})) {
                            $table->{$paramKey} = $data['params'][$paramKey];
                        }
                    }
                }

                $wasZasilkovna = $persistedMethod->shipment_element === VirtueMartModelZasilkovna::PLG_NAME;
            }
        }

        // clones have data already set
        if (($isZasilkovna && $isBeingCreated) || ($wasZasilkovna === false && $isZasilkovna)) {
            // do not override values of clones
            if (empty($data['shipment_cost'])) {
                $data['shipment_cost'] = VirtueMartModelZasilkovna::PRICE_DEFAULT;
            }

            if (empty($data['maxWeight'])) {
                $data['maxWeight'] = VirtueMartModelZasilkovna::MAX_WEIGHT_DEFAULT;
            }

            // clones can contain invalid data from previous releases
            $data['published'] = '0'; // user must configure the method
            vmWarn(JText::_('PLG_VMSHIPMENT_PACKETERY_SHIPPING_WARNING'));
            return;
        }

        if (!$isZasilkovna || $isBeingCreated) {
            return; // method must be saved to show plugin specific configuration
        }

        $form = $this->getShippingMethodForm();

        $formData = $data;
        if (!$form->validate($formData)) {
            // Get validation errors
            $errors = $form->getErrors();
            foreach ($errors as $error) {
                JFactory::getApplication()->enqueueMessage($error->getMessage(), 'error');
            }
        }

        $method = ShipmentMethod::fromRandom($data);
        $shippingType = $method->getShippingType();
        if ($shippingType === ShipmentMethod::SHIPPING_TYPE_PICKUPPOINTS) {
            $method->resetHdCarrier();
        } else {
            $method->resetPPCarrier();
            $method->resetVendorGroups();
        }

        $report = $this->shipmentMethodValidator->validate($method);

        if ($report->isValid() === false || $form->getErrors() !== []) {
            foreach ($report->getErrors() as $error) {
                if ($error->params && count($error->params) > 0) {
                    vmError(JText::sprintf($error->translationCode, ...$error->params));
                } else {
                    vmError(JText::_($error->translationCode));
                }
            }

            $app = JFactory::getApplication();
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=shipmentmethod&task=edit&cid[]=' . $data['virtuemart_shipmentmethod_id'], false)); // calls exit
        } else {
            $resortedClone = $method->getResortedClone();
            $data = $resortedClone->toArray();
            // save old value of setting for recreation of indexes
            VmConfig::set(self::ZASILKOVNA_OLD_RECREATE_KEY_VALUE, VmConfig::get('reCreaKey', 1));
            //turn off recreation of indexes
            VmConfig::set('reCreaKey', 0);
        }
    }

    /**
     * @param \TableShipmentmethods $data
     * @return mixed
     */
    function plgVmDeclarePluginParamsShipmentVM3(&$data)
    {
        $document = JFactory::getDocument();
        $document->addStyleSheet(JUri::root() . 'media/com_zasilkovna/media/css/shipping-method.css?v=' . filemtime(PACKETERY_MEDIA_DIR . '/css/shipping-method.css'));
        $document->addScript(JUri::root() . 'media/com_zasilkovna/media/js/shipping-method.js?v=' . filemtime(PACKETERY_MEDIA_DIR . '/js/shipping-method.js'));

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

    /**
     * @param \VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver $activeCheckout
     * @param string $country2code
     * @param string $langCode
     * @param int|null $carrierId
     * @return string
     */
    public function getTailBlockHtml(
        \VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver $activeCheckout,
        $country2code,
        $langCode,
        $carrierId
    ) {
        $this->renderer->setTemplate($activeCheckout->getTailBlock());

        $tailBlockJsPath = null;
        if (is_file($activeCheckout->getTailBlockJs())) {
            $tailBlockJsPath = $this->createSignalUrl(
                'provideCheckoutTailBlockJsFile',
                [
                    'v' => filemtime($activeCheckout->getTailBlockJs())
                ]
            );
        }

        $templateVariables =
            [
                'savePickupPointUrl' => $this->createSignalUrl('saveSelectedPoint'),
                'apiKey' => $this->model->api_key,
                'country' => $country2code,
                'language' => $langCode,
                'version' => $this->getVersionString(),
                'widgetJsUrl' => $this->model->_media_url . 'js/widget.js?v=' . filemtime($this->model->_media_path . 'js/widget.js'),
                'errorPickupPointNotSelected' => \JText::_('PLG_VMSHIPMENT_PACKETERY_SHIPMENT_NOT_SELECTED'),
                'tailBlockJsPath' => $tailBlockJsPath,
            ];
        
        if ($carrierId) {
            $templateVariables['carriers'] = $carrierId;
        }
        $this->renderer->setVariables($templateVariables);

        return $this->renderer->renderToString();
    }

    private function addPacketaToolbarButtons(\VirtueMartModelZasilkovna\Order\Order $order)
    {
        $toolbar = JToolbar::getInstance('toolbar');
        if (version_compare(JVERSION, '4.0.0', '>=')) {
            if ($order->hasPacketId()) {
                $button = new LinkButton(
                    'packetaPrintLabel',
                    JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABEL'),
                    [
                        'icon' => 'icon-printer',
                        'class' => 'btn btn-small',
                        'url' => '#',
                    ]
                );
            } else {
                $submitUrl = 'index.php?option=com_virtuemart&view=zasilkovna&task=submitPacket&virtuemart_order_id=' . $order->getVirtuemartOrderId();
                $button = new LinkButton(
                    'packetaSubmitPacket',
                    JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMIT_TO_PACKETA'),
                    [
                        'icon' => 'icon-envelope',
                        'class' => 'btn btn-small',
                        'url' => $submitUrl,
                    ]
                );
            }

            $button->setParent($toolbar);
            $items = $toolbar->getItems();
            // Last button is Help
            $lastButton = array_pop($items);
            $items[] = $button;
            $items[] = $lastButton;

            $toolbar->setItems($items);
        } elseif ($order->hasPacketId()) {
            $buttonHtml = '<button id="toolbar-packetaPrintLabel" class="btn btn-small">
                              <span class="icon-print" title="' . JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABEL') . '"></span> ' .
                              JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABEL') .
                          '</button>';

            $toolbar->appendButton('Custom', $buttonHtml, 'packetaPrintLabel');
        } else {
            $submitUrl = 'index.php?option=com_virtuemart&view=zasilkovna&task=submitPacket&virtuemart_order_id=' . $order->getVirtuemartOrderId();
            JToolbarHelper::link($submitUrl, JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMIT_TO_PACKETA'), 'envelope');
        }
    }

    /**
     * @param TableOrders &$data
     * @param string $old_order_status
     * @param mixed $inputOrder
     * @return void
     * @throws Exception
     */
    public function plgVmOnUpdateOrderPayment(&$data, $old_order_status, $inputOrder)
    {
        $app = JFactory::getApplication();

        // shipping method isn't Packeta or request is not from backend
        if (!($this->selectedThisByMethodId($data->virtuemart_shipmentmethod_id)) || !$app->isClient('administrator')) {
            return;
        }

        if ($this->shouldAutosubmit($data->virtuemart_paymentmethod_id, $data->order_status)) {
            /** @var VirtueMartModelZasilkovna_orders $zasOrdersModel */
            $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
            $zasOrdersModel->submitToZasilkovna([$data->order_number]);
        }
    }

    /**
     * @param int $vmPaymentMethodId
     * @param string $vmOrderStatusCode
     * @return bool
     */
    private function shouldAutosubmit($vmPaymentMethodId, $vmOrderStatusCode)
    {
        $autosubmitOrderStatuses = $this->model->getConfig('zasilkovna_autosubmission_order_statuses', []);

        return (array_key_exists($vmPaymentMethodId, $autosubmitOrderStatuses) && $autosubmitOrderStatuses[$vmPaymentMethodId] === $vmOrderStatusCode);
    }

    /**
     * @return \Joomla\CMS\Form\Form
     */
    private function getShippingMethodForm()
    {
        $path = JPATH_ROOT . '/plugins/vmshipment/zasilkovna/zasilkovna.xml';
        $xpath = '//vmconfig';
        $xml = simplexml_load_string(file_get_contents($path));
        $formXml = $xml->xpath($xpath);
        $formString = $formXml[0]->asXML();

        return JForm::getInstance('zasilkovnaForm', $formString);
    }

}
