<?php
/**
*
* Config controller
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
* @version $Id: config.php 6188 2012-06-29 09:38:30Z Milbo $
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmController'))require(VMPATH_ADMIN.DS.'helpers'.DS.'vmcontroller.php');
$zas_model=VmModel::getModel('zasilkovna');
$zas_model->loadLanguage();

class VirtuemartControllerZasilkovna extends VmController {
	
	
	// $this->zas_model=VmModel::getModel('zasilkovna');
	function __construct() {
		parent::__construct();


	}


	/**
	 * Handle the save task
	 *
	 * @author Zasilkovna
	 */
	function save($data = 0){
		
		vRequest::vmCheckToken();
		$data = vRequest::getPost();
		
		$db =& JFactory::getDBO();
		$q="UPDATE #__extensions SET custom_data='" . serialize($data) . "' WHERE element='zasilkovna'";
		$db->setQuery($q);
		$db->query();
		
		$redir = 'index.php?option=com_virtuemart';
		if(JRequest::getCmd('task') == 'apply'){
			$redir = $this->redirectPath;			
		}		
		$this->updateZasilkovnaOrders();
		$this->setRedirect($redir, $msg);
	}

	/**
	* Save change packets info to zasilkovna plugin db
	*
	* @author Zasilkovna
	*/
	public function updateZasilkovnaOrders(){
		$zasOrdersModel=VmModel::getModel('zasilkovna_orders'); 		
		$zasOrdersModel->updateOrders($_POST['orders']);			
	}


	public function exportZasilkovnaOrders(){
		$zasOrdersModel=VmModel::getModel('zasilkovna_orders');      		      	
      	$zasOrdersModel->exportToCSV($_POST['exportOrders']);				
	}

	public function updateAndExportZasilkovnaOrders(){		
		$this->updateZasilkovnaOrders();
		$this->exportZasilkovnaOrders();	
		$msg="";
		$this->setRedirect($this->redirectPath);		
	}
	
	public function printLabels(){
		$zasOrdersModel=VmModel::getModel('zasilkovna_orders');
		$result=$zasOrdersModel->printLabels($_POST['printLabels'],$_POST['print_type'],$_POST['label_first_page_skip']);
		foreach ($result as $error) {
			JError::raiseWarning(100,$error);
		}
		$this->setRedirect($this->redirectPath, $msg,$type);		
	}
	public function cancelOrderSubmitToZasilkovna(){
		$zasOrdersModel=VmModel::getModel('zasilkovna_orders');
		$zasOrdersModel->cancelOrderSubmitToZasilkovna($_GET['cancel_order_id']);		
		if($this->setRedirect($this->redirectPath, $msg,$type)){
			$msg=JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDER_SUBMIT_CANCELED');//"Všechny objednávky byly přidány do systému Zásilkovny.";
		}
		$this->setRedirect($this->redirectPath, $msg,'message');		
	}


	public function submitToZasilkovna(){
		$this->updateZasilkovnaOrders();
		$zasOrdersModel=VmModel::getModel('zasilkovna_orders');      		      	
		$result=$zasOrdersModel->submitToZasilkovna($_POST['exportOrders']);
		$exportedOrders=$result['exported'];
		$failedOrders=$result['failed'];
		if(count($_POST['exportOrders'])==0){
			$msg=JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NO_ORDERS_SELECTED');//"Žádné objednávky nebyly vybrány k odeslání.";
			$type='error';
		}
		else if(count($_POST['exportOrders'])==count($exportedOrders)){
			$msg=JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ALL_ORDERS_SUBMITTED');//"Všechny objednávky byly přidány do systému Zásilkovny.";
			$type='message';
		}else{
			JError::raiseWarning(100,JText::_('PLG_VMSHIPMENT_ZASILKOVNA_SUBMITTED_ORDERS').": ".count($exportedOrders).". ".JText::_('PLG_VMSHIPMENT_ZASILKOVNA_NOT_SUBMITTED_ORDERS').": (".count($failedOrders)."):");
			foreach ($failedOrders as $failedOrder) {
				JError::raiseWarning(100,$failedOrder['order_number'].": ".$failedOrder['message']);				
			}
			$type='error';
		}
		$this->setRedirect($this->redirectPath, $msg,$type);
	}

	/**
	 * Overwrite the remove task
	 * Removing config is forbidden.
	 * @author Max Milbers
	 */
	function remove(){

		$msg = JText::_('COM_VIRTUEMART_ERROR_CONFIGS_COULD_NOT_BE_DELETED');

		$this->setRedirect( $this->redirectPath , $msg);
	}

}

