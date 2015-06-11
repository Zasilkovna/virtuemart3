<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
if (!class_exists ('calculationHelper')) {
  require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'calculationh.php');
}
if (!class_exists ('CurrencyDisplay')) {
  require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
}
if (!class_exists ('VirtueMartModelVendor')) {
  require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php');
}


class plgVmShipmentZasilkovna extends vmPSPlugin
{
    // instance of class
    public static $_this = false;

    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);


    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @author Valérie Isaksen
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('zasilkovna');
    }

    function getTableSQLFields()
    {
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
     * @param integer $order_number The order Number
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author Valérie Isaksen
     * @author Max Milbers
     */
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
    }

    /**
     * This event is fired after the order has been stored; it gets the shipment method-
     * specific data.
     *
     * @param int $order_id The order_id being processed
     * @param object $cart  the cart
     * @param array $priceData Price information for this order
     * @return mixed Null when this method was not selected, otherwise true
     * @author Valerie Isaksen
     */

    function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->shipment_element)) {
            return false;
        }
        if (!$this->OnSelectCheck($cart)) {
            return false;
        }

        $zas_model=VmModel::getModel('zasilkovna');
        $zas_orders=VmModel::getModel('zasilkovna_orders');
        $fromCurrency=$zas_model->getCurrencyCode($order['details']['BT']->order_currency);

        //convert from payment currency to branch currency
        $price_in_branch_currency=$zas_orders->convertToBranchCurrency($order['details']['BT']->order_total,$fromCurrency,$_SESSION['branch_currency']);

        $values['virtuemart_order_id']          = $order['details']['BT']->virtuemart_order_id;
        $values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
        $values['order_number']                 = $order['details']['BT']->order_number;
        $values['zasilkovna_packet_id']         = 0;
        $values['zasilkovna_packet_price']      = $price_in_branch_currency;
        $values['branch_id']                    = $_SESSION['branch_id'];
        $values['branch_currency']              = $_SESSION['branch_currency'];
        $values['branch_name_street']           = $_SESSION['branch_name_street'];
        $values['email']                        = $cart->BT['email'];
        $values['phone']                        = $cart->BT['phone_1'] ? $cart->BT['phone_1'] : $cart->BT['phone_2'];
        $values['first_name']                   = $cart->BT['first_name'];
        $values['last_name']                    = $cart->BT['last_name'];
        $values['address']                      = $cart->BT['address_1'];
        $values['city']                         = $cart->BT['city'];
        $values['zip_code']                     = $cart->BT['zip'];
        $values['adult_content']                = 0;
        $values['is_cod']                       = -1; //depends on actual settings of COD payments until its set manually in administration
        $values['exported     ']                = 0;
        $values['shipment_name']                = $method->shipment_name;
        $values['shipment_cost']                = $this->getCosts ($cart, $method, "");
        $values['tax_id']                       = $method->tax_id;
        $this->storePSPluginInternalData($values);
        return true;
    }


  /**
   * calculateSalesPrice
   * overrides default function to remove currency conversion
   * @author Zasilkovna
   */

  function calculateSalesPrice ($cart, $method, $cart_prices) {
      $value = $this->getCosts ($cart, $method, $cart_prices);

      $tax_id = @$method->tax_id;


      $vendor_id = 1;
      $vendor_currency = VirtueMartModelVendor::getVendorCurrency ($vendor_id);

      $db = JFactory::getDBO ();
      $calculator = calculationHelper::getInstance ();
      $currency = CurrencyDisplay::getInstance ();

      $taxrules = array();
      if (!empty($tax_id)) {
        $q = 'SELECT * FROM #__virtuemart_calcs WHERE `virtuemart_calc_id`="' . $tax_id . '" ';
        $db->setQuery ($q);
        $taxrules = $db->loadAssocList ();
      }

      if (count ($taxrules) > 0) {
        $salesPrice = $calculator->roundInternal ($calculator->executeCalculation ($taxrules, $value));
      } else {
        $salesPrice = $value;
      }
      return $salesPrice;
	}

    /**
     * @return delivery cost for the shipping method instance
     * @author Zasilkovna
     */

    function getCosts(VirtueMartCart $cart, $method, $cart_prices){
		$freeShippingTreshold = $method->{'free_shipping_treshold_czk'};
		$shippingPrice = $method->{'packet_price_czk'};
				
		if($freeShippingTreshold &&
				$cart_prices['salesPrice'] >= $freeShippingTreshold &&
				$freeShippingTreshold >= 0) {
			return 0;
		}else{
			return $shippingPrice;
		}
    }

    /** TODO
    * Here can add check if user has filled in valid phone number or mail so he is reachable by zasilkovna
    */
    protected function checkConditions($cart, $method, $cart_prices)
    {
      $weightTreshold = $method->weight_treshold;
      $orderWeight = $this->getOrderWeight ($cart, $method->weight_unit);
      if(empty($weightTreshold) || $weightTreshold == -1 || $orderWeight < $weightTreshold) return true;
      return false;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallShipmentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the shipment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    // public function plgVmOnSelectCheck($psType, VirtueMartCart $cart) {
    // return $this->OnSelectCheck($psType, $cart);
    // }
    public function plgVmOnSelectCheckShipment(VirtueMartCart &$cart)
    {
        if ($this->OnSelectCheck($cart)) {
            session_start();
            $_SESSION['branch_id']          = JRequest::getVar('branch_id', '', 'post', 'STRING', JREQUEST_ALLOWHTML);
            $_SESSION['branch_currency']    = JRequest::getVar('branch_currency', '', 'post', 'STRING', JREQUEST_ALLOWHTML);
            $_SESSION['branch_name_street'] = JRequest::getVar('branch_name_street', '', 'post', 'STRING', JREQUEST_ALLOWHTML);
        }else{
            $_SESSION['branch_id'] = -1;
        }
        $cart->virtuemart_paymentmethod_id = 0;//reset selected payment. Payment options are shown depending on selected shipment
        return $this->OnSelectCheck($cart);
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
    public function plgVmDisplayListFEShipment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $js_html = '';
        if ($this->getPluginMethods($cart->vendorId) === 0) {
                return FALSE;
        }
		
		$q = "SELECT custom_data FROM #__extensions WHERE element='zasilkovna'";
        $db = JFactory::getDBO ();
        $db->setQuery($q);
        $obj = $db->loadObject ();
		
		$zasConfig = unserialize($obj->custom_data);
		
        $zas_model = VmModel::getModel('zasilkovna');
        $js_url    = $zas_model->updateJSApi();


        if ($js_url === false) return false;
        if (isset($zas_model->errors)) return false; //api key or smth is wrong - more info shows in administration

        $html        = array();
        $method_name = $this->_psType . '_name';
        $prevSelectedBranch=$_SESSION['branch_id'];
        $js_html.= '<script src="' . $js_url . '"></script>';
        $js_html.='<script language="javascript" type="text/javascript">
      var zasilkovnaDefaultSelect = '.$zasConfig['zasilkovna_default_select'].' ;
			
      (function($) {
      window.addHooks=function(){
        function setRequiredOpt(){
          var setOnce = false;
          $("div.packetery-branch-list").each(
            function() {
              var div = $(this).closest(\'div[name="helper_div"]\');
              var radioButt = $(div).find(\'input[name="virtuemart_shipmentmethod_id"]:radio\');
              if($(radioButt).is(\':checked\')){
                this.packetery.option("required", true);
              }else{
                this.packetery.option("required", false);
              }
            }
          );
        }

        function uncheckAll(){
          $(\'input[name="virtuemart_shipmentmethod_id"]:radio\').each(
            function(){
              $(this).prop("checked",false);
            }
          );
        }
        function selectFirstOption(){
          if($(\'input[name="branch_id"]\').val()>0)return;
          var firstZasilkovnaDiv = $(\'div[name="helper_div"]\')[0];
          var firstZasilkovnaRadio = $(firstZasilkovnaDiv).find(\'input[name="virtuemart_shipmentmethod_id"]:radio\');
          $(firstZasilkovnaRadio).attr("checked",true);
        }

        //set each radio button to call setRequiredOpt if clicked
        $(\'input[name="virtuemart_shipmentmethod_id"]:radio\').each(
          function(){
            $(this).click(setRequiredOpt);
          }
        );
        $("div.packetery-branch-list").each(
          function() {
            var fn = function(){
              var branches = this.packetery.option("branches");
              var selected_id = this.packetery.option("selected-id");
              var box = $(this).closest(\'div.zasilkovna_box\');
              var newVal="";
              if(selected_id){//if this branch was already selected
                box.find(\'input[name="branch_id"]\').val(branches[selected_id].id);
                box.find(\'input[name="branch_currency"]\').val(branches[selected_id].currency);
                box.find(\'input[name="branch_name_street"]\').val(branches[selected_id].name_street);
                var div = $(this).closest(\'div[name="helper_div"]\');
                var radioButt = $(div).find(\'input[name="virtuemart_shipmentmethod_id"]:radio\');
                $(radioButt).prop("checked",true);
              }

              setTimeout(setRequiredOpt, 1);
            };
            this.packetery.on("branch-change", fn);
            fn.call(this);
            if(zasilkovnaDefaultSelect == 1){
              selectFirstOption();
            }
          }
        );
        $(\'input[name="shipping_rate_id"]:radio\').on("change", function() {setTimeout(setRequiredOpt, 1); });

      }
      })(window.packetery.jQuery);
    </script>';
        $js_html .= "<div class='zasilkovna_box'>";
        $js_html .= '<input type="hidden" name="branch_id">';
        $js_html .= '<input type="hidden" name="branch_currency">';
        $js_html .= '<input type="hidden" name="branch_name_street">';
        $jsHtmlIsSet = false;
        foreach ($this->methods as $key => $method) {
            $html[$key] = '';
            /*this part adds javascript api and controls
            ONLY TO ONE of the zasilkovna shipment methods that ARE allowed to show
            */
            $selectedPayment = (empty($cart->virtuemart_paymentmethod_id) ? 0 : $cart->virtuemart_paymentmethod_id);
            if($jsHtmlIsSet==false){
                $shipmentID=$method->virtuemart_shipmentmethod_id;     
                $configRecordName='zasilkovna_combination_payment_'.$selectedPayment.'_shipment_'.$shipmentID;
                if(((isset($zasConfig[$configRecordName]) ? $zasConfig[$configRecordName] : '1')=='1')||($selectedPayment==0)){
                    $html[$key] .= $js_html;
                    $jsHtmlIsSet=true;
                }
            }

            $country = $method->country;
			
            if ($this->checkConditions($cart, $method, $cart->pricesUnformatted)) {
                $html[$key] .= '<div name="helper_div">';//this div packs the select box with radio input - helps js easily find the radio
                $methodSalesPrice     = $this->calculateSalesPrice($cart, $method, $cart->pricesUnformatted);
                $method->$method_name = $this->renderPluginName($method);
                $html[$key] .= $this->getPluginHtml($method, $selected, $methodSalesPrice);
                $selected_id_attr = 'selected-id='.$_SESSION['branch_id'];
                $html[$key] .= '<p name="select-branch-message" style="float: none; color: red; font-weight: bold; display: none; ">vyberte pobočku</p><div id="zasilkovna_select" class="packetery-branch-list list-type=3 country=' . $country . ' '.$selected_id_attr.' style="border: 1px dotted black;">Načítání: seznam poboček osobního odběru</div>';
                $html[$key] .= '</select></div>';
            }
        }


        if (empty($html)) {
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
   * @param object  $_shipInfo Object with the properties 'shipment' and 'name'
   * @return mixed Null for shipments that aren't active, text (HTML) otherwise
   * @author Valerie Isaksen
   */
  public function plgVmOnShowOrderBEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id) {

    if (!($this->selectedThisByMethodId ($virtuemart_shipmentmethod_id))) {
      return NULL;
    }
    $html = $this->getOrderShipmentHtml ($virtuemart_order_id);
    return $html;
  }

  /**
   * @param $virtuemart_order_id
   * @return string
   * @author zasilkovna
   */
  function getOrderShipmentHtml ($virtuemart_order_id) {

    $db = JFactory::getDBO ();
    $q = 'SELECT * FROM `' . $this->_tablename . '` '
      . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
    $db->setQuery ($q);
    if (!($shipinfo = $db->loadObject ())) {
      vmWarn (500, $q . " " . $db->getErrorMsg ());
      return '';
    }

    if (!class_exists ('CurrencyDisplay')) {
      require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
    }

    $currency = CurrencyDisplay::getInstance ();
    $tax = ShopFunctions::getTaxByID ($shipinfo->tax_id);
    $taxDisplay = is_array ($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
    $taxDisplay = ($taxDisplay == -1) ? JText::_ ('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

    $html = '<table class="adminlist">' . "\n";
    $html .= $this->getHtmlHeaderBE ();
    $html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_SHIPPING_NAME', $shipinfo->shipment_name);
    $html .= $this->getHtmlRowBE ('BRANCH', $shipinfo->branch_name_street);
    $html .= $this->getHtmlRowBE ('CURRENCY', $shipinfo->branch_currency);

    $html .= '</table>' . "\n";

    return $html;
  }

    public function plgVmonSelectedCalculatePriceShipment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelected
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedShipment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

     public function plgVmOnCheckoutCheckData($psType, VirtueMartCart $cart) {
     return null;
     }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrint($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

     public function plgVmOnUpdateOrder($psType, $_formData) {
     return null;
     }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

     public function plgVmOnUpdateOrderLine($psType, $_formData) {
     return null;
     }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

     public function plgVmOnEditOrderLineBE($psType, $_orderId, $_lineId) {
     return null;
     }
     */
    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

     public function plgVmOnShowOrderLineFE($psType, $_orderId, $_lineId) {
     return null;
     }
     */

    /**
     * plgVmOnResponseReceived
     * This event is fired when the  method returns to the shop after the transaction
     *
     *  the method itself should send in the URL the parameters needed
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param int $virtuemart_order_id : should return the virtuemart_order_id
     * @param text $html: the html to display
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *

     function plgVmOnResponseReceived($psType, &$virtuemart_order_id, &$html) {
     return null;
     }
     */
    function plgVmDeclarePluginParamsShipment($name, $id, &$data)
    {
        return $this->declarePluginParams('shipment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsShipment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

	function plgVmDeclarePluginParamsShipmentVM3 (&$data) {
		return $this->declarePluginParams ('shipment', $data);
	}
}

// No closing tag
