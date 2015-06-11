<?php
/**
 * @package	Zasilkovna
 * @author Zasilkovna
 * @link http://www.zasilkovna.cz
 */
defined('_JEXEC') or die('Restricted access');
/*if (!class_exists('JModel'))
	require JPATH_VM_LIBRARIES . DS . 'joomla' . DS . 'application' . DS . 'component' . DS . 'model.php';*/
if(!class_exists('VmModel'))require(VMPATH_ADMIN.DS.'helpers'.DS.'vmmodel.php');

/**
 * @author Zasilkovna
 */

class VirtueMartModelZasilkovna extends VmModel
{
	const VERSION = '1.0';
    const PLG_NAME = 'zasilkovna';
	public $warnings = array();
	public $api_key;

	static $_couriers_to_address = array(13 => 'Česká pošta',106 => 'Doručení na adresu ČR',16 => 'Slovenská pošta');

	var $_zas_url = "http://www.zasilkovna.cz/";

	var $_media_url = "";
	var $_media_path = "";

	var $_db_table_name="#__virtuemart_shipment_plg_zasilkovna";
	var $checked_configuration = false;
	var $config_ok = false;



	public function __construct()
	{
		$language = JFactory::getLanguage();
		$language->load('plg_vmshipment_zasilkovna', JPATH_ADMINISTRATOR, null, true);
		$language->load('plg_vmshipment_zasilkovna', JPATH_SITE, null, true);

		$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
        $db = JFactory::getDBO ();
        $db->setQuery($q);
        $obj = $db->loadObject ();

		$config = unserialize($obj->custom_data);

		$this->api_pass =$config['zasilkovna_api_pass'];
		$this->api_key = substr($config['zasilkovna_api_pass'],0,16);
		$this->_media_url=JURI::root( true )."/media/com_zasilkovna/media/";
		$this->_media_path=JPATH_SITE.DS."media".DS."com_zasilkovna".DS."media".DS;
		parent::__construct();
	}

	public function getDbTableName(){
		return $this->_db_table_name;
	}

    public function getShipmentMethodIds(){
        $q = "SELECT virtuemart_shipmentmethod_id FROM #__virtuemart_shipmentmethods WHERE shipment_element = '".self::PLG_NAME."'";
        $db = JFactory::getDBO ();
        $db->setQuery($q);
        $objList = $db->loadObjectList();
        $list = array();
        foreach($objList as $obj){
            $list[] = $obj->virtuemart_shipmentmethod_id;
        }
        return $list;
    }



	public function getBranches(){
		$db = JFactory::getDBO ();
		$q = "SELECT * from #__virtuemart_zasilkovna_branches";
		$db->setQuery($q);
		return $db->loadObjectList();
	}

	public function getCurrencyCode($currency_id){
			$vendorId = VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO ();
			$q = 'SELECT   `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`=' . $currency_id;
			$db->setQuery ($q);
			return $db->loadResult ();
	}

//TODO: write check for restriction code in file /components/com_virtuemart/views/cart/tmpl/select_payment.php
	public function isShipmentPaymentRestrictionInstalled(){
		$file = JPATH_SITE.'/components/com_virtuemart/views/cart/tmpl/select_payment.php';
		if(strpos(file_get_contents($file),'ZASILKOVNA') !== false) {
        	return true;
    	}else{
    		return false;
    	}
	}

  	public function checkModuleVersion(){
	    $checkUrl=$this->_zas_url."api/".$this->api_key."/version-check-virtuemart2?my=" . self::VERSION;
	    $data = json_decode($this->fetch($checkUrl));
	    if($data->version > self::VERSION) {
	        $lg=&JFactory::getLanguage();
	        $lang=substr($lg->getTag(), 0, 2);
	        return JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NEW_VERSION').": ".$data->message->$lang;
	    }else{
	        return JText::_('PLG_VMSHIPMENT_ZASILKOVNA_VERSION_IS_NEWEST')." - ".self::VERSION;
	    }
  	}

	public function checkConfiguration()
	{
		if ($this->checked_configuration) return $this->config_ok;
		$this->checked_configuration = true;
		$key                         = $this->api_key;
		$testUrl                     = $this->_zas_url . "api/$key/test";

		if (!$key) {
			$this->errors[] = JText::_('PLG_VMSHIPMENT_ZASILKOVNA_API_KEY_NOT_SET');
			$this->config_ok  = false;
			return false;
		}
		if (!$this->httpAccessMethod()) {
			$this->errors[] = 'cannot load curl or url_fopen';
			$this->config_ok  = false;
			return false;
		}
		if ($this->fetch($testUrl) != 1) {
			$this->errors[] = JText::_('PLG_VMSHIPMENT_ZASILKOVNA_API_KEY_NOT_VERIFIED');
		}
		$this->config_ok = true;
		return true;
	}
	private function httpAccessMethod(){
	   if(extension_loaded('curl')) return true;
       if(ini_get('allow_url_fopen')) return true;
       return false;

	}
	private function fetch($url)
	{
		if (extension_loaded('curl')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3);
			$body = curl_exec($ch);
			if (curl_errno($ch) > 0) {
				return false;
			}
			return $body;
		} elseif (ini_get('allow_url_fopen')) {
			if (function_exists('stream_context_create')) {
				$ctx = stream_context_create(array(
					'http' => array(
						'timeout' => 3
					)
				));
				return file_get_contents($url, 0, $ctx);
			} else {
				return file_get_contents($url);
			}
		} else
			return false;
	}

	/*
	* Return js api url and if it is needed, updates it
	*/
	public function updateJSApi(){
		$js_path = $this->_media_path . 'branch.js';
		if (!$this->is_writable($js_path)) return false;
		if (!$this->isFileUpToDate($js_path)) {
			if (!$this->updateFile($js_path, 'js')) {
				//updating file failed
				if (!$this->isFileUsable($js_path)) {
					// if file is older than 5 days
					$this->errors[] = "Cannot update javascript file and it is older than 5 days.";
					return false;
				}
			}
		}
		if(!$this->updateBranchesInfo()){
			return false;
		}
		return $this->_media_url . "branch.js";
	}

	public function updateBranchesInfo(){
	  $localFilePath = $this->_media_path.'branch.xml';
	  if(!$this->is_writable($localFilePath))return false;
	  if(!$this->isFileUpToDate($localFilePath)){
	    // file is older than one days
	    if(!$this->updateFile($localFilePath,"xml")){
	      //failed updating
	      if(!$this->isFileUsable($localFilePath)){
	        //file is older than 5 days and thus not usable
	        $this->errors[]='Cannot update branches xml file and it is older than 5 days.';
	        return false;
	      }
	    }else{
	      //updating succeeded, update mysql db
	      if(!$this->saveBranchesXmlToDb($localFilePath)){
	        $this->errors[]='cannot update branches database records from xml file';
	        return false;
	      }
	    }
	  }
	  return true;
	}

	/**
	*	shows errors in module administration
	*/
	public function raiseErrors(){
		if(is_array($this->errors)){
			foreach ($this->errors as $error) {
				JError::raiseWarning(600, $error);
			}
		}
	}


	private function saveBranchesXmlToDb($path){
	    $xml = simplexml_load_file($path);
	    if($xml){
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
	      	$first=true;
	      	foreach($xml->branches->branch as $key => $branch){
	      	  if($first){
	      	    $q.=" (";
	      	    $first=false;
	      	  }else{
	      	    $q.=", (";
	      	  }
	      	  $q .= "'$branch->id', '$branch->name_street','$branch->currency','$branch->country')";

	      	}
	      	$db->setQuery($q);
	      	$db->query();
	    }else{
	      return false;
	    }
	    return true;
	}



	private function isFileUpToDate($path){
		if (!file_exists($path))return false;
		if (filemtime($path) < time() - (60 * 60 * 24))return false;
		if (filesize($path) <= 1024)return false;
		return true;
	}

	private function isFileUsable($path)//true if not older than 5 days
	{
		if (!file_exists($path))return false;
		if (filemtime($path) < time() - (60 * 60 * 24 * 5))return false;
		if (filesize($path) <= 1024)return false;
		return true;
	}


	private function updateFile($path, $type)
	{
		$remote = $this->_zas_url . "api/" . $this->api_key . "/branch." . $type;
		if ($type == 'js') {
			$lib_path = substr($this->_media_url, 0, -1);
			$remote .= "?callback=addHooks";
			$remote .= "&lib_path=$lib_path&sync_load=1";
		}
		$data = $this->fetch($remote);
		file_put_contents($path, $data);
		clearstatcache();
		if (filesize($path) < 1024) {
			return false;
		}
		return true;
	}

	private function is_writable($filepath){
	  if(!file_exists($filepath)){
	    @touch($filepath);
	  }
	  if(is_writable($filepath)){
	    return true;
	  }
	  $this->errors[]=$filepath." must be writable.";
	return false;
	}


	public function loadLanguage(){
		$language = JFactory::getLanguage();
		$language->load('plg_vmshipment_zasilkovna', JPATH_ADMINISTRATOR, 'en-GB', true);
		$language->load('plg_vmshipment_zasilkovna', JPATH_SITE, 'en-GB', true);
	}


}

//pure php no closing tag