<?php
/**
 *
 * Model for orders with zasilkovna shipping
 *
 * @package	zasilkovna_orders
 * @author Zasilkovna
 * @link http://www.zasilkovna.cz
 * @version 1.0
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmModel'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmmodel.php');


class VirtueMartModelZasilkovna_orders extends VmModel {
    const ALL_ORDERS = -5;
    const ZASILKOVNA_ORDERS = 0;
	var $_table_name="#__virtuemart_shipment_plg_zasilkovna";

	function __construct() {
		parent::__construct();
		$this->zas_model=VmModel::getModel('zasilkovna');
		$this->setMainTable('orders');
		$this->addvalidOrderingFieldName(array('order_name','payment_method','virtuemart_order_id' ) );

	}


	public function printLabels($orders_id_arr,$format='A7 on A4',$offset='0'){

		$db = JFactory::getDBO();
		$gw = new SoapClient("http://www.zasilkovna.cz/api/soap-php-bugfix.wsdl");
		$zas_model=VmModel::getModel('zasilkovna');
		$apiPassword = $zas_model->api_pass;

		$format=str_replace('_', ' ', $format);

		$errors=array();
		if(sizeof($orders_id_arr)==0){
			$errors[]=JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NO_PACKET_TO_PRINT');
			return $errors;
		}
		try{
			$packet = $gw->packetsLabelsPdf($apiPassword, $orders_id_arr, $format, $offset);
			header('Content-type: application/pdf');
			header('Content-Disposition: attachment; filename="labels-' . date("Ymd-His") . '.pdf"');
			echo $packet;
			$this->setPrintLabelFlag($orders_id_arr);

		}catch(SoapFault $e){
			$errors[]=$e->faultstring." ";
			if(is_array($e->detail->PacketIdsFault->ids->packetId) ){
				$wrongPacketIds="";
				foreach ($e->detail->PacketIdsFault->ids->packetId as $wrongPacketId) {
					$wrongPacketIds.=$wrongPacketId." ";
				}
				$errors[]=$wrongPacketIds;
			}else if(is_object($e->detail->PacketIdsFault)){//only one error
				$errors[]=$e->detail->PacketIdsFault->ids->packetId;
			}
			return $errors;
		}
		exit();
	}

	public function submitToZasilkovna($orders_id_arr){

		$db = JFactory::getDBO();
		$gw = new SoapClient("http://www.zasilkovna.cz/api/soap-php-bugfix.wsdl");
		$zas_model=VmModel::getModel('zasilkovna');

		$apiPassword = $zas_model->api_pass;
		$ordersForExport=$this->prepareForExport($orders_id_arr);
		$exportedOrders=array();
		$failedOrders=array();
		$exportedOrdersNumber=array();
		foreach ($ordersForExport as $order) {
			try {
				if(isset($order['zasilkovna_packet_id']) && ($order['zasilkovna_packet_id']!=0)){//some better check?
					throw new Exception("Objednávka již byla podána na Zásilkovně. Nejprve zrušte číslo zásilky v administraci.");
				}
                $attributes = array(
					'number' => $order['order_number'],
					'name' => $order['first_name'],
					'surname' => $order['last_name'],
					'email' => $order['email'],
					'phone' => $order['phone'],
					'addressId' => $order['branch_id'],
					'cod' => $order['cod'],
					'value' => $order['total'],
					'eshop' => $order['domain'],
					'adultContent' => ($order['adult_content']==1 ? true:false)
				);

                if(array_key_exists ($order['branch_id'],VirtueMartModelZasilkovna::$_couriers_to_address)){
                    $attributes['street'] = $order['address'];
                    $attributes['houseNumber'] = $order['houseNumber'];
                    $attributes['city'] = $order['city'];
                    $attributes['zip'] = $order['zip_code'];
                }
				$packet = $gw->createPacket($apiPassword, $attributes);
				$q="UPDATE ".$this->_table_name." SET zasilkovna_packet_id=".$packet->id." WHERE order_number = '".$order['order_number']."'; ";
				$db->setQuery($q);
				$db->loadAssocList();
				$exportedOrders[]=array('order_number'=>$order['order_number'],'zasilkovna_id'=>$packet->id);
				$exportedOrdersNumber[]=$order['order_number'];
			}
			catch(Exception $e) {
				$error_msg="";
				if(get_class($e)=='SoapFault'){
					if(is_array($e->detail->PacketAttributesFault->attributes->fault)){//more errors
						foreach ($e->detail->PacketAttributesFault->attributes->fault as $error) {
							$error_msg.=$error->name.": ".$error->fault." ";
						}
					}else if(is_object($e->detail->PacketAttributesFault->attributes->fault)){//only one error
						$error_msg.=$e->detail->PacketAttributesFault->attributes->fault->name.": ".$e->detail->PacketAttributesFault->attributes->fault->fault." ";
					}else{//structure error (missing parameter etc)
						$error_msg.=$e->faultstring." ";
					}
				}else{
					$error_msg=$e->getMessage();
				}

				$failedOrders[]=array('order_number'=>$order['order_number'],'message'=>$error_msg);
			}
		}

		$this->setExportedFlag($exportedOrdersNumber);

		return array('exported'=>$exportedOrders,'failed'=>$failedOrders);
	}
	public function cancelOrderSubmitToZasilkovna($order_id){
		if(!isset($order_id))return false;
		$db = JFactory::getDBO();
		$q="UPDATE ".$this->_table_name." SET exported=0, zasilkovna_packet_id=0 WHERE virtuemart_order_id = ".$order_id.";";
		$db->setQuery($q);
		$db->loadAssocList();
		return true;
	}
	private function setPrintLabelFlag($printedLabels){
		if(count($printedLabels)){
			$db = JFactory::getDBO();
			$printedLabelsString=implode("','", $printedLabels);
			$q="UPDATE ".$this->_table_name." SET printed_label=1 WHERE zasilkovna_packet_id IN ('".$printedLabelsString."') ";
			$db->setQuery($q);
			$db->loadAssocList();
		}
	}

	private function setExportedFlag($exportedOrders){
		if(count($exportedOrders)){
			$db = JFactory::getDBO();
			$exportedOrdersString=implode("','", $exportedOrders);
			$q="UPDATE ".$this->_table_name." SET exported=1 WHERE order_number IN ('".$exportedOrdersString."') ";
			$db->setQuery($q);
			$db->query();
		}
	}

	public function exportToCSV($orders_id_arr){
		if(sizeof($orders_id_arr) == 0 ){
			JError::raiseWarning(100,JTEXT::_('PLG_VMSHIPMENT_ZASILKOVNA_NO_PACKET_TO_CSV'));
			return;
		}
		header("Content-Type: text/csv");
      	header("Content-Disposition: attachment; filename=\"export-" . date("Ymd-His") . ".csv\"");
		$ordersForExport=$this->prepareForExport($orders_id_arr);
		$exportedOrders=array();
        $zas_model = VmModel::getModel('zasilkovna');
        echo '"verze 2"';
        echo "\r\n";
        echo "\r\n";
		foreach ($ordersForExport as $row) {
			echo ';"'.$this->csv_escape($row['order_number']).'";"'.$this->csv_escape($row['first_name']).'";"'.$this->csv_escape($row['last_name']).'";;"'.$this->csv_escape($row['email']).'";"'.$this->csv_escape($row['phone']).'";"'.$this->csv_escape($row['cod']).'";"'.$this->csv_escape($row['total']).'";"'.$this->csv_escape($row['branch_id']).'";"'.$row['domain'].'";';
            if($row['adult_content']){
                echo "1;";
            }else{
                echo "0;";
            }

            if(array_key_exists ($row['branch_id'],VirtueMartModelZasilkovna::$_couriers_to_address)){
                echo '"'.$row['address'].'";';
                echo '"";';//HOUSE NUMBER
                echo '"'.$row['city'].'";';
                echo '"'.$row['zip_code'].'"';
            }
            echo "\r\n";
			$exportedOrders[]=$row['order_number'];
		}
		$this->setExportedFlag($exportedOrders);
      	exit();
	}


	public function updateOrders($orders){
		$db = JFactory::getDBO();

		foreach ($orders as $key => $order) {

			$queries = array();

			/*
				convert packet price from old branch currency to new branch currency
			*/
			$db->setQuery("SELECT brnch.currency FROM #__virtuemart_zasilkovna_branches brnch JOIN ".$this->_table_name." t ON brnch.id=t.branch_id WHERE order_number='".$order['order_number']."';");
			//$oldBranchCurrency = $db->loadResult();
			$db->setQuery("SELECT currency FROM #__virtuemart_zasilkovna_branches WHERE id=".$order['branch_id'].";");
			//$newBranchCurrency = $db->loadResult();
			if($oldBranchCurrency!=$newBranchCurrency){
				$order['zasilkovna_packet_price'] = $this->convertToBranchCurrency($order['zasilkovna_packet_price'],$oldBranchCurrency,$newBranchCurrency);
			}


			$q="UPDATE ".$this->_table_name." SET ";
            $set_q = array();
			if($order['submitted']=='1'){
				$set_q[] = " zasilkovna_packet_id = ".$order['zasilkovna_packet_id'];
			}else{
				$set_q[] = " is_cod = ".$order['is_cod']." ";
				$set_q[] = " email = '".$order['email']."' ";
				$set_q[] = " phone = '".$order['phone']."' ";
				$set_q[] = " branch_id = '".$order['branch_id']."' ";
				$set_q[] = " zasilkovna_packet_price = ".$order['zasilkovna_packet_price']." ";
				if(isset($order['adult_content']) && $order['adult_content']=='on') {
					$set_q[] = " adult_content = 1";
				}else{
					$set_q[] = " adult_content = 0";
				}
			}
            foreach(array('address','city','zip_code') as $field){
                if(isset($order[$field])){
                    $set_q[] = " ".$field." = '".$order[$field]."' ";
                }
            }
			$q .= implode(' , ',$set_q) . " WHERE order_number='".$order['order_number']."'; ";
			$db->setQuery($q);
			$db->query();
		}

	}

	private function createConvertInstance(){
		$this->_app = JFactory::getApplication();
		if(empty($vendorId)) $vendorId = 1;

		$this->_db = JFactory::getDBO();
		$q = 'SELECT `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`="'.(int)$vendorId.'"';
		$this->_db->setQuery($q);
		$this->_vendorCurrency = $this->_db->loadResult();

		$converterFile  = VmConfig::get('currency_converter_module');

		if (file_exists( JPATH_VM_ADMINISTRATOR.DS.'plugins'.DS.'currency_converter'.DS.$converterFile )) {
			$module_filename=substr($converterFile, 0, -4);
			require_once(JPATH_VM_ADMINISTRATOR.DS.'plugins'.DS.'currency_converter'.DS.$converterFile);
			if( class_exists( $module_filename )) {
				$this->_currencyConverter = new $module_filename();
			}
		} else {
			if(!class_exists('convertECB')) require(JPATH_VM_ADMINISTRATOR.DS.'plugins'.DS.'currency_converter'.DS.'convertECB.php');
			$this->_currencyConverter = new convertECB();

		}
	}

	protected function prepareForExport($orders_arr){
		if(!$orders_arr)return;
		$ordersForINStatement=implode("','", $orders_arr);
		$db = JFactory::getDBO();
		$q="SELECT o.order_number,curr.currency_code_3 order_currency_name,
        plg.zasilkovna_packet_price order_total,oi.first_name,oi.last_name,
        oi.email,oi.phone_1,oi.phone_2,plg.is_cod,plg.branch_id,plg.zasilkovna_packet_id,
        plg.address as address, plg.city, plg.zip_code,
        brnch.currency branch_currency_name FROM #__virtuemart_orders o ";
		$q.= "JOIN #__virtuemart_order_userinfos oi ON o.virtuemart_order_id=oi.virtuemart_order_id ";
		$q.= "JOIN ".$this->_table_name." plg ON plg.order_number=o.order_number ";
		$q.= "LEFT JOIN #__virtuemart_zasilkovna_branches brnch ON brnch.id=plg.branch_id ";
		$q.= "LEFT JOIN #__virtuemart_currencies curr ON curr.virtuemart_currency_id=o.order_currency ";
		$q.= " WHERE o.order_number IN ('".$ordersForINStatement."') ";
		$db->setQuery($q);
		$rows=$db->loadAssocList();

		$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
        $db = JFactory::getDBO ();
        $db->setQuery($q);
        $obj = $db->loadObject ();
		$config = unserialize($obj->custom_data);

		$domain=$config['zasilkovna_eshop_domain'];
		$this->createConvertInstance();
		$ordersForExport=array();
		foreach ($rows as $key => $row) {
			$orderForExport=array();

			$orderForExport['order_number']			= $row['order_number'];
			$orderForExport['first_name']			= $row['first_name'];
			$orderForExport['last_name']			= $row['last_name'];
			$orderForExport['email']				= $row['email'];
			$orderForExport['branch_id']			= $row['branch_id'];
			$orderForExport['domain']				= $domain;
			$orderForExport['zasilkovna_packet_id']	= $row['zasilkovna_packet_id'];

            $orderForExport['address'] = $row['address'];
            $orderForExport['houseNumber'] = '1';
            $orderForExport['city'] = $row['city'];
            $orderForExport['zip_code'] = $row['zip_code'];


        	foreach(array('phone_1', 'phone_2') as $field) {
        	      $phone_n=$this->normalize_phone($row[$field]);
        	      if(preg_match('/^\+42[01][0-9]{9}$|^$/', $phone_n)) {
        	        $orderForExport['phone'] = $phone_n;
        	      }
        	}

        	// $total=$this->convertToBranchCurrency($row['order_total'],$row['order_currency_name'],$row['branch_currency_name']);
        	$total=$row['order_total'];//conversion to branch currency is done after confirming order and also after each change of branch.
        	$orderForExport['total']=$total;

        	if($row['is_cod']){
        		$cod=$total;
        	}else{
        		$cod=0;
        	}
        	$orderForExport['cod']=$cod;
        	$ordersForExport[]=$orderForExport;
		}

		return $ordersForExport;
	}

    public function convertToBranchCurrency($value,$fromCurrency,$toCurrency){
    	$this->createConvertInstance();
    	$branch_currency=strtoupper($toCurrency);
        $total=$this->_currencyConverter->convert($value,strtoupper($fromCurrency),$branch_currency);
        if($branch_currency=='CZK'){
        	$total=round($total);
        }else{
        	$total=round($total,2);
        }
        return $total;
    }


	private function csv_escape($s){
	  return str_replace('"', '\"', $s);
	}

  private function normalize_phone($value)
      {
      $value = str_replace(' ', '', trim($value));

      // remove garbage around phone number - but only accept proper count of digits, else we want an error thrown
      if(preg_match('/(?:\+|00)?(42[01][0-9]{9})([^0-9]|$)/', $value, $m)) { $value = "+$m[1]"; }
      elseif(preg_match('/(^|[^0-9])0?([0-9]{9})([^0-9]|$)/', $value, $m)) { $value = $m[2]; }

      // clear default value (backwards compatibility), autodetect prefix
      if($value == "+420" || $value == "+421") {
          $value = "";
      }
      elseif($value[0] == '6' || $value[0] == '7') {
          $value = "+420$value";
      }
      elseif($value[0] == '9') {
          $value = "+421$value";
      }

      return ($value ? $value : null);
    }






	/**
	 * This function gets the orderId, for anonymous users
	 * @author Max Milbers
	 */
	public function getOrderIdByOrderPass($orderNumber,$orderPass){

		$db = JFactory::getDBO();
		$q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `order_pass`="'.$db->getEscaped($orderPass).'" AND `order_number`="'.$db->getEscaped($orderNumber).'"';
		$db->setQuery($q);
		$orderId = $db->loadResult();

// 		vmdebug('getOrderIdByOrderPass '.$orderId);
		return $orderId;

	}
	/**
	 * This function gets the orderId, for payment response
	 * author Valerie Isaksen
	 */
	public function getOrderIdByOrderNumber($orderNumber){

		$db = JFactory::getDBO();
		$q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `order_number`="'.$db->getEscaped($orderNumber).'"';
		$db->setQuery($q);
		$orderId = $db->loadResult();
		return $orderId;

	}
	/**
	 * This function seems completly broken, JRequests are not allowed in the model, sql not escaped
	 * This function gets the secured order Number, to send with paiement
	 *
	 */
	public function getOrderNumber($virtuemart_order_id){

		$db = JFactory::getDBO();
		$q = 'SELECT `order_number` FROM `#__virtuemart_orders` WHERE virtuemart_order_id="'.(int)$virtuemart_order_id.'"  ';
		$db->setQuery($q);
		$OrderNumber = $db->loadResult();
		return $OrderNumber;

	}

	/**
	 * Was also broken, actually used?
	 *
	 * get next/previous order id
	 *
	 */

	public function getOrderId($direction ='DESC', $order_id) {

		if ($direction == 'ASC') {
			$arrow ='>';
		} else {
			$arrow ='<';
		}

		$db = JFactory::getDBO();
		$q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `virtuemart_order_id`'.$arrow.(int)$order_id;
		$q.= ' ORDER BY `virtuemart_order_id` '.$direction ;
		$db->setQuery($q);

		if ($oderId = $db->loadResult()) {
			return $oderId ;
		}
		return 0 ;
	}


	/**
	 * Load a single order
	 */
	public function getOrder($virtuemart_order_id){

		//sanitize id
		$virtuemart_order_id = (int)$virtuemart_order_id;
		$db = JFactory::getDBO();
		$order = array();

		// Get the order details
		$q = "SELECT  u.*,o.*,
				s.order_status_name
			FROM #__virtuemart_orders o
			LEFT JOIN #__virtuemart_orderstates s
			ON s.order_status_code = o.order_status
			LEFT JOIN #__virtuemart_order_userinfos u
			ON u.virtuemart_order_id = o.virtuemart_order_id
			WHERE o.virtuemart_order_id=".$virtuemart_order_id;
		$db->setQuery($q);
		$order['details'] = $db->loadObjectList('address_type');

		// Get the order history
		$q = "SELECT *
			FROM #__virtuemart_order_histories
			WHERE virtuemart_order_id=".$virtuemart_order_id."
			ORDER BY virtuemart_order_history_id ASC";
		$db->setQuery($q);
		$order['history'] = $db->loadObjectList();

		// Get the order items
$q = 'SELECT virtuemart_order_item_id, product_quantity, order_item_name,
    order_item_sku, i.virtuemart_product_id, product_item_price,
    product_final_price, product_basePriceWithTax, product_subtotal_with_tax, product_subtotal_discount, product_tax, product_attribute, order_status,
    intnotes, virtuemart_category_id
   FROM (#__virtuemart_order_items i
   LEFT JOIN #__virtuemart_products p
   ON p.virtuemart_product_id = i.virtuemart_product_id)
                        LEFT JOIN #__virtuemart_product_categories c
                        ON p.virtuemart_product_id = c.virtuemart_product_id
   WHERE `virtuemart_order_id`="'.$virtuemart_order_id.'" group by `virtuemart_order_item_id`';
//group by `virtuemart_order_id`'; Why ever we added this, it makes trouble, only one order item is shown then.
// without group by we get the product 3 times, when it is in 3 categories and similar, so we need a group by
//lets try group by `virtuemart_order_item_id`
		$db->setQuery($q);
		$order['items'] = $db->loadObjectList();
// Get the order items
		$q = "SELECT  *
			FROM #__virtuemart_order_calc_rules AS z
			WHERE  virtuemart_order_id=".$virtuemart_order_id;
		$db->setQuery($q);
		$order['calc_rules'] = $db->loadObjectList();
// 		vmdebug('getOrder my order',$order);
		return $order;
	}
	public function getZasilkovnaOrdersList(){
		return $this->getOrdersListByShipment(0);
	}



	/*
		Copy _ALL_ orders to zasilkovna table
		(to be able to edit and submit all orders to zasilkovna)
	*/
	private function copyOrdersToZasilkovnaTable(){
		$q = "INSERT INTO #__virtuemart_shipment_plg_zasilkovna (virtuemart_order_id,virtuemart_shipmentmethod_id,order_number,email,phone,branch_id,zasilkovna_packet_price,first_name,last_name,address,city,zip_code,virtuemart_country_id)
        SELECT o.virtuemart_order_id,o.virtuemart_shipmentmethod_id,o.order_number,oi.email,oi.phone_1,-1,o.order_total,oi.first_name,oi.last_name,oi.address_1,oi.city,oi.zip,oi.virtuemart_country_id FROM #__virtuemart_orders as o JOIN #__virtuemart_order_userinfos as oi ON o.virtuemart_order_id = oi.virtuemart_order_id WHERE o.virtuemart_order_id NOT IN (SELECT virtuemart_order_id FROM #__virtuemart_shipment_plg_zasilkovna)";
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$db->query();

	}

	public function getOrdersListByShipment($shipment_id = 0){
		$this->copyOrdersToZasilkovnaTable();
		//check if table already exists
		// -- it gets created when shiping module is run for the first time
		$db = JFactory::getDBO();
		$db->setQuery("SELECT order_number FROM ".$this->zas_model->_db_table_name);
		if(!$db->loadResult()) return array();

// 		vmdebug('getOrdersList');
		$this->_noLimit = $noLimit;
		$select = " o.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS order_name "
		.',pm.payment_name AS payment_method';
		$from = $this->getZasilkovnaOrdersListQuery();
		$select .= ', plg.printed_label,plg.address,plg.city,plg.zip_code,plg.virtuemart_country_id, plg.branch_id, plg.exported AS exported,plg.is_cod AS is_cod,plg.branch_currency, brnch.name_street AS name_street, brnch.country as country, plg.adult_content, plg.email, plg.phone, plg.zasilkovna_packet_id,plg.zasilkovna_packet_price ';
		if($shipment_id==self::ALL_ORDERS){
		  //no where statement => select all
          ;
        }else if($shipment_id==self::ZASILKOVNA_ORDERS){
			$zas_methods =  $this->zas_model->getShipmentMethodIds();
			$where[] = ' o.virtuemart_shipmentmethod_id IN ('. (!empty($zas_methods) ? implode(',',$zas_methods) : 'NULL') .')';
		}else{
            //exact shipping method was selected, filter by its id.
		    $where[] = ' o.virtuemart_shipmentmethod_id = '.$shipment_id;
		}
		/*		$_filter = array();
		 if ($uid > 0) {
		$_filter[] = ('u.virtuemart_user_id = ' . (int)$uid);
		}*/

		/*if(!class_exists('Permissions')) require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'permissions.php');
		if(!Permissions::getInstance()->check('admin')){
			$myuser		=JFactory::getUser();
			$where[]= ' u.virtuemart_user_id = ' . (int)$myuser->id.' AND o.virtuemart_vendor_id = "1" ';
		} else {
			if(empty($uid)){
				$where[]= ' o.virtuemart_vendor_id = "1" ';
			} else {
				$where[]= ' u.virtuemart_user_id = ' . (int)$uid.' AND o.virtuemart_vendor_id = "1" ';
			}
		}*/

		//$where[]=' order_number IN (SELECT order_number FROM #__virtuemart_shipment_plg_zasilkovna)';
		if ($search = JRequest::getString('search', false)){

			$search = '"%' . $this->_db->getEscaped( $search, true ) . '%"' ;

			$where[] = ' ( u.first_name LIKE '.$search.' OR u.middle_name LIKE '.$search.' OR u.last_name LIKE '.$search.' OR `order_number` LIKE '.$search.')';
		}



		if ($order_status_code = JRequest::getString('order_status_code', false)){
			$where[] = ' o.order_status = "'.$order_status_code.'" ';
		}

		if (count ($where) > 0) {
			$whereString = ' WHERE (' . implode (' AND ', $where) . ') ';
		}
		else {
			$whereString = '';
		}

		if ( JRequest::getCmd('view') == 'orders') {
			$ordering = $this->_getOrdering();
		} else {
			$ordering = ' order by o.modified_on DESC';
		}

		$this->_data = $this->exeSortSearchListQuery(0,$select,$from,$whereString,'',$ordering);
		return $this->_data ;
	}
	/**
	 * List of tables to include for the product query
	 * @author Zasilkovna
	 */
	private function getZasilkovnaOrdersListQuery()
	{
		return ' FROM #__virtuemart_orders as o
			LEFT JOIN #__virtuemart_order_userinfos as u
			ON u.virtuemart_order_id = o.virtuemart_order_id AND u.address_type="BT"
			LEFT JOIN #__virtuemart_paymentmethods_'.VMLANG.' as pm
			ON o.virtuemart_paymentmethod_id = pm.virtuemart_paymentmethod_id
			RIGHT JOIN '.$this->zas_model->_db_table_name.' as plg ON plg.order_number=o.order_number
			LEFT JOIN #__virtuemart_zasilkovna_branches as brnch ON brnch.id=plg.branch_id';
	}

	/**
	 * List of tables to include for the product query
	 * @author RolandD
	 */
	private function getOrdersListQuery()
	{
		return ' FROM #__virtuemart_orders as o
			LEFT JOIN #__virtuemart_order_userinfos as u
			ON u.virtuemart_order_id = o.virtuemart_order_id AND u.address_type="BT"
			LEFT JOIN #__virtuemart_paymentmethods_'.VMLANG.' as pm
			ON o.virtuemart_paymentmethod_id = pm.virtuemart_paymentmethod_id ';
	}







	private function getVendorCurrencyId($vendorId){
		$q = 'SELECT `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`="'.$vendorId.'" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$vendorCurrency =  $db->loadResult();
		return $vendorCurrency;
// 		return $this->getCurrencyIsoCode($vendorCurrency);
	}

	private function getCurrencyIsoCode($vmCode){
		$q = 'SELECT `currency_numeric_code` FROM  `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'.$vmCode.'" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		return $db->loadResult();
	}



	function getInvoiceNumber($virtuemart_order_id){

		$db = JFactory::getDBO();
		$q = 'SELECT `invoice_number` FROM `#__virtuemart_invoices` WHERE `virtuemart_order_id`= "'.$virtuemart_order_id.'" ';
		$db->setQuery($q);
		return $db->loadresult();
	}




	/**
	 * Retrieve the details for an order line item.
	 *
	 * @author RickG
	 * @param string $orderId Order id number
	 * @param string $orderLineId Order line item number
	 * @return object Object containing the order item details.
	 */
	function getOrderLineDetails($orderId, $orderLineId) {
		$table = $this->getTable('order_items');
		if ($table->load((int)$orderLineId)) {
			return $table;
		}
		else {
			$table->reset();
			$table->virtuemart_order_id = $orderId;
			return $table;
		}
	}


/**
	* @return zasilkovna orders
	*/
	public function getOrdersList($shipment_id = 0)
	{
		//check if table already exists
		// -- it gets created when shiping module is run for the first time
		$db = JFactory::getDBO();
		$db->setQuery("SELECT order_number FROM ".$this->zas_model->_db_table_name);
		if(!$db->loadResult()) return array();

// 		vmdebug('getOrdersList');
		$this->_noLimit = $noLimit;
		$select = " o.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS order_name "
		.',pm.payment_name AS payment_method, plg.printed_label, plg.branch_id, plg.exported AS exported,plg.is_cod AS is_cod,plg.branch_currency, brnch.name_street AS name_street, brnch.country as country, plg.adult_content, plg.email, plg.phone, plg.zasilkovna_packet_id,plg.zasilkovna_packet_price ';
		$from = $this->getZasilkovnaOrdersListQuery();
		/*		$_filter = array();
		 if ($uid > 0) {
		$_filter[] = ('u.virtuemart_user_id = ' . (int)$uid);
		}*/

		if(!class_exists('Permissions')) require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'permissions.php');
		if(!Permissions::getInstance()->check('admin')){
			$myuser		=JFactory::getUser();
			$where[]= ' u.virtuemart_user_id = ' . (int)$myuser->id.' AND o.virtuemart_vendor_id = "1" ';
		} else {
			if(empty($uid)){
				$where[]= ' o.virtuemart_vendor_id = "1" ';
			} else {
				$where[]= ' u.virtuemart_user_id = ' . (int)$uid.' AND o.virtuemart_vendor_id = "1" ';
			}
		}

		//$where[]=' order_number IN (SELECT order_number FROM #__virtuemart_shipment_plg_zasilkovna)';
		if ($search = JRequest::getString('search', false)){

			$search = '"%' . $this->_db->getEscaped( $search, true ) . '%"' ;

			$where[] = ' ( u.first_name LIKE '.$search.' OR u.middle_name LIKE '.$search.' OR u.last_name LIKE '.$search.' OR `order_number` LIKE '.$search.')';
		}

		if( $shipment_id > 0){

		}

		if ($order_status_code = JRequest::getString('order_status_code', false)){
			$where[] = ' o.order_status = "'.$order_status_code.'" ';
		}

		if (count ($where) > 0) {
			$whereString = ' WHERE (' . implode (' AND ', $where) . ') ';
		}
		else {
			$whereString = '';
		}

		if ( JRequest::getCmd('view') == 'orders') {
			$ordering = $this->_getOrdering();
		} else {
			$ordering = ' order by o.modified_on DESC';
		}

		$this->_data = $this->exeSortSearchListQuery(0,$select,$from,$whereString,'',$ordering);
		return $this->_data ;
	}

}
