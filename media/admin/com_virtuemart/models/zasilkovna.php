<?php
/**
 * @package Zasilkovna
 * @author Zasilkovna
 * @link http://www.zasilkovna.cz
 */

defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmModel')) require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmmodel.php');

/**
 * Class VirtueMartModelZasilkovna
 */
class VirtueMartModelZasilkovna extends VmModel
{
    const VERSION = '1.1.9';
    const PLG_NAME = 'zasilkovna';

    const MAX_WEIGHT_DEFAULT = 5;
    const PRICE_DEFAULT = 100;

    public $warnings = array();
    public $api_key;

    protected $config;

    public $_zas_url = "http://www.zasilkovna.cz/";

    public $_media_url = "";
    public $_media_path = "";

    private $_db_table_name = "#__virtuemart_shipment_plg_zasilkovna";
    public $checked_configuration = false;
    public $config_ok = false;

    public $errors = array();


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
        $db =& JFactory::getDBO();
        $q = "UPDATE #__extensions SET custom_data='" . serialize($data) . "' WHERE element='zasilkovna'";
        $db->setQuery($q);
        $db->query();
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
        return $this->_db_table_name;
    }

    /**
     * @return array
     */
    public function getShipmentMethodIds()
    {
        $q = "SELECT virtuemart_shipmentmethod_id FROM #__virtuemart_shipmentmethods WHERE shipment_element = '" . self::PLG_NAME . "'";
        $db = JFactory::getDBO();
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
        $value = (int) $value;
        $imploded = implode(',', $ids);
        $q = "UPDATE #__virtuemart_shipmentmethods SET published = $value WHERE virtuemart_shipmentmethod_id IN ($imploded)";
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $db->execute();
    }

    /**
     * @return mixed
     */
    public function getBranches() {
        $db = JFactory::getDBO();
        $q = "SELECT * from #__virtuemart_zasilkovna_branches";
        $db->setQuery($q);

        return $db->loadObjectList();
    }

    /**
     * @param $currency_id
     * @return mixed
     */
    public function getCurrencyCode($currency_id)
    {
        $vendorId = VirtueMartModelVendor::getLoggedVendor();
        $db = JFactory::getDBO();
        $q = 'SELECT   `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`=' . $currency_id;
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
     * @return string
     */
    public function checkModuleVersion()
    {
        $checkUrl = $this->_zas_url . "api/" . $this->api_key . "/version-check-virtuemart2?my=" . self::VERSION;
        $data = json_decode($this->fetch($checkUrl));
        if($data->version > self::VERSION) {
            $lg =& JFactory::getLanguage();
            $lang = substr($lg->getTag(), 0, 2);

            return JText::_('PLG_VMSHIPMENT_PACKETERY_NEW_VERSION') . ": " . $data->message->$lang;
        }
        else {
            return JText::_('PLG_VMSHIPMENT_PACKETERY_VERSION_IS_NEWEST') . " - " . self::VERSION;
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
     * @param $url
     * @return false|string
     */
    private function fetch($url)
    {
        if (ini_get('allow_url_fopen')) {
            if (function_exists('stream_context_create')) {
                $ctx = stream_context_create(
                    array(
                        'http' => array(
                            'timeout' => 20
                        )
                    )
                );

                return file_get_contents($url, 0, $ctx);
            } else {
                return file_get_contents($url);
            }
        } else {
            return false;
        }
    }

    /*
     * Return js api url and if it is needed, updates it
     */
    /**
     * @return false|string
     */
    public function updateJSApi() {
        $js_path = $this->_media_path . 'branch.js';
        if(!$this->is_writable($js_path)) return false;
        if(!$this->isFileUpToDate($js_path)) {
            if(!$this->updateFile($js_path, 'js')) {
                //updating file failed
                if(!$this->isFileUsable($js_path)) {
                    // if file is older than 5 days
                    $this->errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH_UPDATE_ERROR');

                    return false;
                }
            }
        }
        if(!$this->updateBranchesInfo()) {
            return false;
        }

        return $this->_media_url . "branch.js";
    }

    /**
     * @return bool
     */
    public function updateBranchesInfo() {
        $localFilePath = $this->_media_path . 'branch.xml';
        if(!$this->is_writable($localFilePath)) return false;
        if(!$this->isFileUpToDate($localFilePath)) {
            // file is older than one days
            if(!$this->updateFile($localFilePath, "xml")) {
                //failed updating
                if(!$this->isFileUsable($localFilePath)) {
                    //file is older than 5 days and thus not usable
                    $this->errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH_UPDATE_ERROR');

					return false;
                }
            }
            else {
                //updating succeeded, update mysql db
                if(!$this->saveBranchesXmlToDb($localFilePath)) {
                    $this->errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH_XML_ERROR');

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Shows errors in module administration
     */
    public function raiseErrors()
    {
        if(is_array($this->errors)) {
            foreach($this->errors as $error) {
                JError::raiseWarning(600, $error);
            }
        }
    }

    /**
     * @param $path
     * @return bool
     */
    private function saveBranchesXmlToDb($path) {
        $xml = simplexml_load_file($path);
        if($xml) {
            $db = JFactory::getDBO();
            $query = 'TRUNCATE TABLE #__virtuemart_zasilkovna_branches';
            $db->setQuery($query);
            $db->query();
            $q = "INSERT INTO #__virtuemart_zasilkovna_branches (
	              `id` ,
	              `name_street` ,
	              `currency` ,
	              `country`
	              ) VALUES ";
            $first = true;
            foreach($xml->branches->branch as $key => $branch) {
                if($first) {
                    $q .= " (";
                    $first = false;
                }
                else {
                    $q .= ", (";
                }
                $streetEscaped = $db->escape($branch->nameStreet);
                $q .= "'$branch->id', '$streetEscaped','$branch->currency','$branch->country')";

            }
            $db->setQuery($q);
            $db->query();
        }
        else {
            return false;
        }

        return true;
    }

    /**
     * @param $path
     * @return bool
     */
    private function isFileUpToDate($path) {
        if(!file_exists($path)) return false;
        if(filemtime($path) < time() - (60 * 60 * 24)) return false;
        if(filesize($path) <= 1024) return false;

        return true;
    }

    /**
     * @param $path
     * @return bool
     */
    private function isFileUsable($path)//true if not older than 5 days
    {
        if(!file_exists($path)) return false;
        if(filemtime($path) < time() - (60 * 60 * 24 * 5)) return false;
        if(filesize($path) <= 1024) return false;

        return true;
    }

    /**
     * @param $path
     * @param $type
     * @return bool
     */
    private function updateFile($path, $type) {
        $remote = $this->_zas_url . "api/v3/" . $this->api_key . "/branch." . $type;
        if($type == 'js') {
            $lib_path = substr($this->_media_url, 0, -1);
            $remote .= "?callback=window.addHooks";
            $remote .= "&lib_path=$lib_path&sync_load=1";
        }
        $data = $this->fetch($remote);
        file_put_contents($path, $data);
        clearstatcache();
        if(filesize($path) < 1024) {
            return false;
        }

        return true;
    }

    /**
     * @param $filepath
     * @return bool
     */
    private function is_writable($filepath) {
        if(!file_exists($filepath)) {
            @touch($filepath);
        }
        if(is_writable($filepath)) {
            return true;
        }
        $this->errors[] = $filepath . " " . JText::_('PLG_VMSHIPMENT_PACKETERY_BRANCH_NOT_WRITABLE');

        return false;
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

}
