<?php
/**
 * @package Zasilkovna
 * @author Zasilkovna
 * @link http://www.zasilkovna.cz
 */

defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmModel')) require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmmodel.php');

if(!class_exists('plgVmShipmentZasilkovna')) require_once VMPATH_ROOT . '/plugins/vmshipment/zasilkovna/zasilkovna.php';

/**
 * Class VirtueMartModelZasilkovna
 */
class VirtueMartModelZasilkovna extends VmModel
{
    const VERSION = '1.4.0';
    const PLG_NAME = 'zasilkovna';

    const MAX_WEIGHT_DEFAULT = 5;
    const PRICE_DEFAULT = 100;

    CONST PACKETA_WSDL = 'http://www.zasilkovna.cz/api/soap-php-bugfix.wsdl';

    public $warnings = array();
    public $api_key;

    protected $config;

    public $_zas_url = "http://www.zasilkovna.cz/";

    public $_media_url = "";
    public $_media_path = "";

    private $_db_table_name = VirtueMartModelZasilkovna\Order\Repository::PACKETERY_ORDER_TABLE_NAME;
    public $checked_configuration = false;
    public $config_ok = false;

    public $errors = array();

    /** @var \VirtueMartModelZasilkovna\Carrier\Repository */
    private $carrierRepository;

    /** @var \VirtueMartModelZasilkovna\Carrier\Downloader */
    private $carrierDownloader;

    /**
     * VirtueMartModelZasilkovna constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $language = JFactory::getLanguage();
        $language->load('plg_vmshipment_zasilkovna', JPATH_ADMINISTRATOR, NULL, true);
        $language->load('plg_vmshipment_zasilkovna', JPATH_SITE, NULL, true);

        $this->config = $this->loadConfig();

        $this->api_pass = $this->config['zasilkovna_api_pass'];
        $this->api_key = substr($this->config['zasilkovna_api_pass'], 0, 16);
        $this->_media_url = JURI::root(true) . "/media/com_zasilkovna/media/";
        $this->_media_path = JPATH_SITE . DS . "media" . DS . "com_zasilkovna" . DS . "media" . DS;

        $this->carrierRepository = new \VirtueMartModelZasilkovna\Carrier\Repository();
        $this->carrierDownloader = new \VirtueMartModelZasilkovna\Carrier\Downloader($this->api_key);

        parent::__construct();
    }


    /**
     * Retrieve database configuration value by forward slash separated path, which corresponds to the config fields
     * e.g. config field with name='section[fieldset][value]' can be accessed with path 'section/fieldset/value'
     * returns default value if config value is not found
     *
     * @param string $path Path to config
     * @param mixed|null $default Default value
     * @return mixed|null for shipments that aren't active, text (HTML) otherwise
     */
    public function getConfig($path, $default = NULL){
        $path = explode('/', $path);
        if( count($path) < 1 ) return NULL;
        $conf = $this->config;
        foreach ($path as $s){
            if( isset( $conf[$s] ) )$conf = $conf[$s];
            else return $default;
        }
        return $conf;
    }


    /**
     * Loads configuration from the database and returns it as an array
     *
     * @return array|null configuration
     */
    public function loadConfig()
    {
        $q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $obj = $db->loadObject();
        return unserialize($obj->custom_data);
    }

    /**
     * @param array $data
     */
    public function updateConfig($data)
    {
        $db = JFactory::getDBO();
        $q = "UPDATE #__extensions SET custom_data='" . $db->escape(serialize($data)) . "' WHERE element='zasilkovna'";
        $db->setQuery($q);
        $db->execute();
    }

    /**
     * @return null|int
     */
    public function getExtensionId()
    {
        $q = "SELECT MAX(extension_id) as extension_id FROM #__extensions WHERE element='zasilkovna'";
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $obj = $db->loadObject();
        if (empty($obj)) {
            return null;
        }

        return $obj->extension_id;
    }

    /**
     * Returns list of supported countries, parameters 'country' and 'lang' are used in the widget
     * support for new country can be easily added by expanding the array
     *
     * @param bool $unsupported In some cases option for unsupported countries is needed
     * @return array List of supported countries
     */
    public function getCountries($unsupported = false)
    {
        $countries = array(
            "cz" => array(
                "country" => "cz"
            ),
            "sk" => array(
                "country" => "sk"
            ),
            "pl" => array(
                "country" => "pl"
            ),
            "hu" => array(
                "country" => "hu"
            ),
            "ro" => array(
                "country" => "ro"
            )
        );
        if($unsupported){
            $countries = array_merge(array(
                "other" => array(
                    "country" => "null"
                )
            ), $countries);
        }

        return $countries;
    }


    /**
     * Returns model's table name
     * @return string
     */
    public function getDbTableName()
    {
        $db = JFactory::getDBO();
        return $db->escape($this->_db_table_name);
    }

    /**
     * @return array
     */
    public function getShipmentMethodIds()
    {
        $db = JFactory::getDBO();
        $q = "SELECT virtuemart_shipmentmethod_id FROM #__virtuemart_shipmentmethods WHERE shipment_element = '" . $db->escape(self::PLG_NAME) . "'";
        $db->setQuery($q);
        $objList = $db->loadObjectList();
        $list = array();
        foreach($objList as $obj) {
            $list[] = $obj->virtuemart_shipmentmethod_id;
        }

        return $list;
    }

    /**
     * @param $ids
     * @param int $value
     */
    public function publishShipmentMethods($ids, $value = 1)
    {
        if (empty($ids)) {
            return;
        }

        $shipmentMethodIds = [];
        foreach ($ids as $shipmentMethodId) {
            $shipmentMethodIds[] = (int)$shipmentMethodId;
        }

        $imploded = implode(',', $shipmentMethodIds);
        $q = "UPDATE #__virtuemart_shipmentmethods SET published = ".(int)$value." WHERE virtuemart_shipmentmethod_id IN ($imploded)";
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $db->execute();
    }

    /**
     * @param $currency_id
     * @return mixed
     */
    public function getCurrencyCode($currency_id)
    {
        $db = JFactory::getDBO();
        $q = 'SELECT   `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`=' . (int)$currency_id;
        $db->setQuery($q);

        return $db->loadResult();
    }

    /**
     * @return bool
     */
    public function isShipmentPaymentRestrictionInstalled()
    {
        $file = JPATH_SITE . '/components/com_virtuemart/views/cart/tmpl/select_payment.php';
        if(strpos(file_get_contents($file), 'ZASILKOVNA') !== false) {
            return true;
        }
        else {
            return false;
        }
    }


    /**
     * @return bool
     */
    public function checkConfiguration()
    {
        if($this->checked_configuration) return $this->config_ok;
        $this->checked_configuration = true;
        $key = $this->api_key;

        if(!$key) {
            $this->errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_API_KEY_NOT_SET');
            $this->config_ok = false;

            return false;
        }
        if(!$this->httpAccessMethod()) {
            $this->errors[] = 'cannot load curl or url_fopen';
            $this->config_ok = false;

            return false;
        }
        $this->config_ok = true;

        return true;
    }

    /**
     * @return bool
     */
    private function httpAccessMethod()
    {
        if(extension_loaded('curl')) return true;
        if(ini_get('allow_url_fopen')) return true;

        return false;

    }

    /**
     * @return int
     */
    public function getTotalUsableCarriersCount() {
        return $this->carrierRepository->getTotalUsableCarriersCount();
    }

    /**
     * @return string
     */
    public function getLastCarriersUpdateTimeFormatted() {
        $config = $this->loadConfig();
        $time = null;
        if (isset($config['carriers_updated_at'])) {
            $time = $config['carriers_updated_at'];
        }

        if (!$time) {
            return JText::_('PLG_VMSHIPMENT_PACKETERY_NEVER');
        }

        $timeInstance = \DateTime::createFromFormat(\DateTime::ATOM, $time);
        return $timeInstance->format(JText::_('PLG_VMSHIPMENT_PACKETERY_DATETIME_FORMAT'));
    }

    /**
     * @return void
     */
    public function updateCarriers()
    {
        try {
            $carriers = $this->carrierDownloader->run($this->getLang2Code());

        } catch (\VirtueMartModelZasilkovna\Carrier\DownloadException $e) {
            $this->errors[] = $e->getMessage();

            return;
        }

        $this->saveCarriers($carriers);
    }

    /**
     * @param array $carriers
     * @return void
     */
    private function saveCarriers(array $carriers) {

        $carrierIdsToDelete = $this->carrierRepository->getAllActiveCarrierIds();
        foreach ($carriers as $carrier) {
            unset($carrierIdsToDelete[$carrier['id']]);

            $data = [
                'id' => $carrier['id'],
                'name' => $carrier['name'],
                'is_pickup_points' => $this->transformStringBool($carrier['pickupPoints']),
                'has_carrier_direct_label' => $this->transformStringBool($carrier['apiAllowed']),
                'separate_house_number' => $this->transformStringBool($carrier['separateHouseNumber']),
                'customs_declarations' => $this->transformStringBool($carrier['customsDeclarations']),
                'requires_email' => $this->transformStringBool($carrier['requiresEmail']),
                'requires_phone' => $this->transformStringBool($carrier['requiresPhone']),
                'requires_size' => $this->transformStringBool($carrier['requiresSize']),
                'disallows_cod' => $this->transformStringBool($carrier['disallowsCod']),
                'country' => $carrier['country'],
                'currency' => $carrier['currency'],
                'max_weight' => $carrier['maxWeight'],
                'deleted' => 0,
            ];

            $this->carrierRepository->insertUpdateCarrier($data);
        }

        $this->carrierRepository->setCarriersDeleted($carrierIdsToDelete);

        $config = $this->loadConfig();
        $config['carriers_updated_at'] = (new \DateTime())->format(\DateTime::ATOM);
        $this->updateConfig($config);
    }

    /**
     * @param string $value
     * @return bool
     */
    private function transformStringBool($value) {
        return !((string)$value === 'false');
    }

    /**
     * Shows errors in module administration
     * @param \Joomla\CMS\Application\AdministratorApplication $app
     * @return void
     */
    public function raiseErrors(\Joomla\CMS\Application\AdministratorApplication $app)
    {
        if(is_array($this->errors)) {
            foreach($this->errors as $error) {
                $app->enqueueMessage($error, 'warning');
            }
        }
    }

    /**
     *
     */
    public function loadLanguage()
    {
        $language = JFactory::getLanguage();
        $language->load('plg_vmshipment_zasilkovna', JPATH_ADMINISTRATOR, NULL, true);
        $language->load('plg_vmshipment_zasilkovna', JPATH_SITE, NULL, true);
    }

    /**
     * @param int $shipmentMethodId
     * @return \VirtueMartModelZasilkovna\ShipmentMethod
     */
    public function getPacketeryShipmentMethod($shipmentMethodId)
    {
        $model = \VmModel::getModel('shipmentmethod');
        $shipment = $model->getShipment($shipmentMethodId);

        return \VirtueMartModelZasilkovna\ShipmentMethod::fromRandom($shipment);
    }

    /**
     * @param int|null $shipmentMethodId
     * @return array
     */
    public function getAvailableHdCarriersByShipmentId($shipmentMethodId)
    {
        if (!$shipmentMethodId) {
            return [];
        }

        $method = $this->getPacketeryShipmentMethod($shipmentMethodId);
        $hdCarriers = $this->carrierRepository->getActiveHdCarriersForPublishedCountries();
        $countries = $method->getAllowedCountries();
        $blockingCountries = $method->getBlockingCountries();

        // Up to PHP 8.0 ints are returned from db as strings, from PHP 8.1 as ints, therefore in_array must not be strict.
        if (empty($countries)) {
            $hdCarriers = array_filter($hdCarriers,
                static function ($hdCarrier) use ($blockingCountries) {
                    return !in_array($hdCarrier['vm_country'], $blockingCountries);
                });
        } else {
            $allowedCountries = array_diff($countries, $blockingCountries);
            $hdCarriers = array_filter($hdCarriers,
                static function ($hdCarrier) use ($allowedCountries) {
                    return in_array($hdCarrier['vm_country'], $allowedCountries);
                });
        }

        return $hdCarriers;
    }

    /**
     * @return string
     */
    public function getLang2Code()
    {
        $language = JFactory::getLanguage();

        return $language ? substr($language->getTag(), 0, 2) : 'en';
    }
}
