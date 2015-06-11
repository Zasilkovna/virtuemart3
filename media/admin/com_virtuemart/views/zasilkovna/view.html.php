<?php
/**
*
* Description
*
* @package	VirtueMart
* @subpackage Config
* @author RickG
* @link http://www.virtuemart.net
* @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* @version $Id: view.html.php 6299 2012-07-25 22:53:11Z Milbo $
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the view framework
if(!class_exists('VmViewAdmin'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmviewadmin.php');
//jimport('joomla.html.pane');
jimport('joomla.version');
$zas_model = VmModel::getModel('zasilkovna');
$zas_model->loadLanguage();
$zas_model->checkConfiguration();

/**
 * HTML View class for the configuration maintenance
 *
 * @package		VirtueMart
 * @subpackage 	Config
 * @author 		RickG
 */
class VirtuemartViewZasilkovna extends VmViewAdmin {

	function display($tpl = null) {

		// Load the helper(s)

		if (!class_exists('VmHTML'))
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'html.php');
		if (!class_exists('VmImage'))
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'image.php');
		if (!class_exists('CurrencyDisplay'))
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		//Load helpers

		//$this->loadHelper('currencydisplay');

		//$this->loadHelper('html');
		$configModel = VmModel::getModel('config');

		$model=VmModel::getModel();

		$shipModel=VmModel::getModel('shipmentmethod');
		$shipments=$shipModel->getShipments();
		function cmpShipments($a, $b)
		{
		    return strcmp($a->virtuemart_shipmentmethod_id, $b->virtuemart_shipmentmethod_id);
		}
		usort($shipments, "cmpShipments");//sort, coz it comes in random order
		$this->assignRef('shipmentMethods', $shipments);


		$this->assignRef('js_path',$model->updateJSApi());
		$this->assignRef('moduleVersion',$model->checkModuleVersion());
		$this->assignRef('errors',$model->errors);
		$this->assignRef('warnings',$model->warnings);


		$paymentModel=VmModel::getModel('paymentmethod');	
		$payments=$paymentModel->getPayments();		
		function cmpPayments($a, $b)
		{
		    return strcmp($a->virtuemart_paymentmethod_id, $b->virtuemart_paymentmethod_id);
		}
		usort($payments, "cmpPayments");//sort, coz it comes in random order
		$this->assignRef('paymentMethods',$payments);

		$usermodel = VmModel::getModel('user');

		$ordersModel = VmModel::getModel('zasilkovna_orders');

		$task = JRequest::getWord('task');


		if(!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS.DS.'vmpsplugin.php');

		$orderStatusModel=VmModel::getModel('orderstatus');
		$orderStates = $orderStatusModel->getOrderStatusList();


		$this->SetViewTitle( 'ORDER');
		


		JToolBarHelper::title( JText::_('COM_VIRTUEMART_CONFIG') , 'head vm_config_48');

		

		$config = VmConfig::loadConfig();
		unset ($config->_params['pdf_invoice']); // parameter remove and replaced by inv_os
		$this->assignRef('config', $config);

		$mainframe = JFactory::getApplication();
		$this->assignRef('joomlaconfig', $mainframe);

		$userparams = JComponentHelper::getParams('com_users');
		$this->assignRef('userparams', $userparams);

		$templateList = ShopFunctions::renderTemplateList(JText::_('COM_VIRTUEMART_ADMIN_CFG_JOOMLA_TEMPLATE_DEFAULT'));

		$this->assignRef('jTemplateList', $templateList);

		$vmLayoutList = $configModel->getLayoutList('virtuemart');
		$this->assignRef('vmLayoutList', $vmLayoutList);


		$categoryLayoutList = $configModel->getLayoutList('category');
		$this->assignRef('categoryLayoutList', $categoryLayoutList);

		$productLayoutList = $configModel->getLayoutList('productdetails');
		$this->assignRef('productLayoutList', $productLayoutList);

		$noimagelist = $configModel->getNoImageList();
		$this->assignRef('noimagelist', $noimagelist);

		$orderStatusModel=VmModel::getModel('orderstatus');


		$this->assignRef('orderStatusModel', $orderStatusModel);


		$currConverterList = $configModel->getCurrencyConverterList();
		$this->assignRef('currConverterList', $currConverterList);
		
		$activeLanguages = $configModel->getActiveLanguages( VmConfig::get('active_languages') );
		$this->assignRef('activeLanguages', $activeLanguages);

		$orderByFields = $configModel->getProductFilterFields('browse_orderby_fields');
		$this->assignRef('orderByFields', $orderByFields);

		$searchFields = $configModel->getProductFilterFields( 'browse_search_fields');
		$this->assignRef('searchFields', $searchFields);

		$aclGroups = $usermodel->getAclGroupIndentedTree();
		$this->assignRef('aclGroups', $aclGroups);
	

		if(is_Dir(VmConfig::get('vmtemplate').DS.'images'.DS.'availability'.DS)){
			$imagePath = VmConfig::get('vmtemplate').'/images/availability/';
		} else {
			$imagePath = '/components/com_virtuemart/assets/images/availability/';
		}
		$this->assignRef('imagePath', $imagePath);
		

		$this->setLayout('orders');


        $this->addStandardDefaultViewLists($ordersModel,'created_on');
        $this->lists['state_list'] = $this->renderOrderstatesList();
            
        $shipping_method_selectec_id = JRequest::getInt('order_shipment_code');
        $orderslist = $ordersModel->getOrdersListByShipment($shipping_method_selectec_id);

        
            
			$this->assignRef('orderstatuses', $orderStates);

			if(!class_exists('CurrencyDisplay'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'currencydisplay.php');

			/* Apply currency This must be done per order since it's vendor specific */
			$_currencies = array(); // Save the currency data during this loop for performance reasons
			if ($orderslist) {
			    foreach ($orderslist as $virtuemart_order_id => $order) {

				    //This is really interesting for multi-X, but I avoid to support it now already, lets stay it in the code
				    if (!array_key_exists('v'.$order->virtuemart_vendor_id, $_currencies)) {
					    $_currencies['v'.$order->virtuemart_vendor_id] = CurrencyDisplay::getInstance('',$order->virtuemart_vendor_id);
				    }
				    $order->order_total = $_currencies['v'.$order->virtuemart_vendor_id]->priceDisplay($order->order_total);
				    $order->invoiceNumber = $ordersModel->getInvoiceNumber($order->virtuemart_order_id);

			    }
			}

			/*
			 * UpdateStatus removed from the toolbar; don't understand how this was intented to work but
			 * the order ID's aren't properly passed. Might be readded later; the controller needs to handle
			 * the arguments.
			 */

		/* Toolbar */
		JToolBarHelper::apply();
		JToolBarHelper::save('updateAndExportZasilkovnaOrders', 'CSV');
		$zas_model=VmModel::getModel('zasilkovna');
		$zas_model->updateJSApi();//to correcly show dest. branches
		$this->assignRef('media_url',$zas_model->_media_url);
		$this->assignRef('restrictionInstalled', $zas_model->isShipmentPaymentRestrictionInstalled());
		
		$this->assignRef('branches',$zas_model->getBranches());
		JToolBarHelper::save('submitToZasilkovna', JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SUBMIT_ORDERS_TO_ZASILKOVNA'));
		JToolBarHelper::custom('printLabels','copy','', JText::_('PLG_VMSHIPMENT_ZASILKOVNA_DO_PRINT_LABELS'),false,false);
		
		/* Assign the data */
		$this->assignRef('orderslist', $orderslist);
		$pagination = $ordersModel->getPagination();
		$this->assignRef('pagination', $pagination);
		
		$this->assignRef('shipmentSelect',$this->renderShipmentsList());
		$model->raiseErrors();
		parent::display($tpl);
	}

	public function renderOrderstatesList() {
		$orderstates = JRequest::getWord('order_status_code','');

		$query = 'SELECT `order_status_code` as value, `order_status_name` as text
			FROM `#__virtuemart_orderstates`
			WHERE published=1 ' ;
			$db = JFactory::getDBO();
		$db->setQuery($query);
		$list = $db->loadObjectList();
		return VmHTML::select( 'order_status_code', $list,  $orderstates,'class="inputbox" onchange="this.form.submit();"');
    }

    public function renderShipmentsList(){
        $zas_orders_model = VmModel::getModel('zasilkovna_orders');
        
		$selected_shipment = JRequest::getInt( 'order_shipment_code','');
		$query = 'SELECT virtuemart_shipmentmethod_id as value,shipment_name as text
			FROM `#__virtuemart_shipmentmethods_'.VMLANG.'`' ;
		$db = JFactory::getDBO();
		$db->setQuery($query);
		$list = $db->loadObjectList();
		$db->setQuery($query);
		$objList = $db->loadObjectList();
            $allObj = new stdClass;
            $allObj->value = $zas_orders_model->ALL_ORDERS;
            $allObj->text = "Všechny objednávky";
        $objList[] = $allObj;
            $zasObj = new stdClass;
            $zasObj->value = $zas_orders_model->ZASILKOVNA_ORDERS;
            $zasObj->text = "Všechny objednávky zásilkovny";
        $objList[] = $zasObj;
		return VmHTML::select( 'order_shipment_code', $objList,  $selected_shipment,'class="inputbox" onchange="this.form.submit();"');
    }

    function generateBranchOptions($branches, $selected_branch_id=0){	
		$ret = "";		
        
        $zas_model = VmModel::getModel('zasilkovna');        
        $ret .= "<option value='-1' ".((-1==$selected_branch_id?'selected':''))."  >Není vybrána doprava.</option>";
        
		foreach (VirtueMartModelZasilkovna::$_couriers_to_address as $id => $courier_name) {			
			$ret .= "<option value=".$id." ".($selected_branch_id==$id?" selected ":" ")." >".$courier_name."</option>";	
		}
        
		foreach ($branches as $branch) {
			$selected="";
			if($selected_branch_id == $branch->id){
				$selected=" selected ";
			}
			$ret .= "<option value=".$branch->id."$selected >".$branch->name_street."</option>";
		}


	return $ret;
}


}
// pure php no closing tag
