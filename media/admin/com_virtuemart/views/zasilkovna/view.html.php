<?php
/**
 *
 * Description
 *
 * @package    VirtueMart
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
if(!class_exists('VmViewAdmin')) require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmviewadmin.php');
//jimport('joomla.html.pane');
jimport('joomla.version');
/** @var VirtueMartModelZasilkovna $zas_model */
$zas_model = VmModel::getModel('zasilkovna');
$zas_model->loadLanguage();
$zas_model->checkConfiguration();

/**
 * HTML View class for the configuration maintenance
 *
 * @package        VirtueMart
 * @subpackage    Config
 * @author        RickG
 */
class VirtuemartViewZasilkovna extends VmViewAdmin {

    function display($tpl = NULL) {


        if(!class_exists('VmHTML'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'html.php');
        if(!class_exists('VmImage'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'image.php');
        if(!class_exists('CurrencyDisplay'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');

        $configModel = VmModel::getModel('config');

        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel();

        $this->showDeprecationWarning();

        $shipModel = VmModel::getModel('shipmentmethod');
        $shipments = $shipModel->getShipments();
        function cmpShipments($a, $b) {
            return strcmp($a->virtuemart_shipmentmethod_id, $b->virtuemart_shipmentmethod_id);
        }

        usort($shipments, "cmpShipments"); //sort, coz it comes in random order
        $this->assignRef('shipmentMethods', $shipments);

        $moduleVersion = VirtueMartModelZasilkovna::VERSION;
        $this->assignRef('moduleVersion', $moduleVersion);
        $this->assignRef('errors', $model->errors);
        $this->assignRef('warnings', $model->warnings);


        $paymentModel = VmModel::getModel('paymentmethod');
        $payments = $paymentModel->getPayments();
        function cmpPayments($a, $b) {
            return strcmp($a->virtuemart_paymentmethod_id, $b->virtuemart_paymentmethod_id);
        }

        usort($payments, "cmpPayments"); //sort, coz it comes in random order
        $this->assignRef('paymentMethods', $payments);

        $usermodel = VmModel::getModel('user');

        /** @var VirtueMartModelZasilkovna_orders $ordersModel */
        $ordersModel = VmModel::getModel('zasilkovna_orders');

        $task = JRequest::getWord('task');


        if(!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

        $orderStatusModel = VmModel::getModel('orderstatus');
        $orderStates = $orderStatusModel->getOrderStatusList();


        $this->SetViewTitle('ORDER');


        JToolBarHelper::title(JText::_('COM_VIRTUEMART_CONFIG'), 'head vm_config_48');


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

        $orderStatusModel = VmModel::getModel('orderstatus');


        $this->assignRef('orderStatusModel', $orderStatusModel);


        $currConverterList = $configModel->getCurrencyConverterList();
        $this->assignRef('currConverterList', $currConverterList);

        $activeLanguages = $configModel->getActiveLanguages(VmConfig::get('active_languages'));
        $this->assignRef('activeLanguages', $activeLanguages);

        $orderByFields = $configModel->getProductFilterFields('browse_orderby_fields');
        $this->assignRef('orderByFields', $orderByFields);

        $searchFields = $configModel->getProductFilterFields('browse_search_fields');
        $this->assignRef('searchFields', $searchFields);

        $aclGroups = $usermodel->getAclGroupIndentedTree();
        $this->assignRef('aclGroups', $aclGroups);


        if(is_Dir(VmConfig::get('vmtemplate') . DS . 'images' . DS . 'availability' . DS)) {
            $imagePath = VmConfig::get('vmtemplate') . '/images/availability/';
        }
        else {
            $imagePath = '/components/com_virtuemart/assets/images/availability/';
        }
        $this->assignRef('imagePath', $imagePath);


        $this->setLayout('orders');


        $this->addStandardDefaultViewLists($ordersModel, 'created_on');
        $this->lists['state_list'] = $this->renderOrderstatesList();

        $shipping_method_selectec_id = JRequest::getInt('order_exported');
        $orderslist = $ordersModel->getOrdersListByShipment($shipping_method_selectec_id);


        $this->assignRef('orderstatuses', $orderStates);

        if(!class_exists('CurrencyDisplay')) require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');

        /* Apply currency This must be done per order since it's vendor specific */
        $_currencies = array(); // Save the currency data during this loop for performance reasons
        if($orderslist) {
            foreach($orderslist as $virtuemart_order_id => $order) {

                //This is really interesting for multi-X, but I avoid to support it now already, lets stay it in the code
                if(!array_key_exists('v' . $order->virtuemart_vendor_id, $_currencies)) {
                    $_currencies['v' . $order->virtuemart_vendor_id] = CurrencyDisplay::getInstance('', $order->virtuemart_vendor_id);
                }
                $order->order_total = $_currencies['v' . $order->virtuemart_vendor_id]->priceDisplay($order->order_total);
                $order->invoiceNumber = $ordersModel->getInvoiceNumber($order->virtuemart_order_id);

            }
        }

        /*
         * UpdateStatus removed from the toolbar; don't understand how this was intented to work but
         * the order ID's aren't properly passed. Might be readded later; the controller needs to handle
         * the arguments.
         */

        /* Toolbar */
        $bar = JToolbar::getInstance('toolbar');

        $bar->appendButton(
            'Custom', '<button onclick="validateForm();" '
            . 'class="btn btn-small button-apply btn-success validate"><span class="icon-apply icon-white" aria-hidden="true"></span>'
            . JText::_('Save') . '</button>', 'apply'
        );

        JToolBarHelper::save('updateAndExportZasilkovnaOrders', 'CSV');
        /** @var VirtueMartModelZasilkovna $zas_model */
        $zas_model = VmModel::getModel('zasilkovna');

        $this->assignRef('media_url', $zas_model->_media_url);
        $restrictionInstalled = $zas_model->isShipmentPaymentRestrictionInstalled();
        $this->assignRef('restrictionInstalled', $restrictionInstalled);

        JToolBarHelper::save('submitToZasilkovna', JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMIT_ORDERS_TO_ZASILKOVNA'));
        JToolBarHelper::custom('printLabels', 'copy', '', JText::_('PLG_VMSHIPMENT_PACKETERY_DO_PRINT_LABELS'), false, false);

        /* Assign the data */
        $this->assignRef('orderslist', $orderslist);
        $pagination = $ordersModel->getPagination();
        $this->assignRef('pagination', $pagination);

        $shipmentSelect = $this->renderShipmentsList();
        $this->assignRef('shipmentSelect', $shipmentSelect);
        $model->raiseErrors();
        parent::display($tpl);
    }

    public function renderOrderstatesList() {
        $orderstates = JRequest::getWord('order_status_code', '');

        $query = 'SELECT `order_status_code` as value, `order_status_name` as text
			FROM `#__virtuemart_orderstates`
			WHERE published=1 ';
        $db = JFactory::getDBO();
        $db->setQuery($query);
        $list = $db->loadObjectList();

        return VmHTML::select('order_status_code', $list, $orderstates, 'class="inputbox" onchange="resetTaskAndSubmitForm(this.form);"');
    }

    public function renderShipmentsList()
    {
        $selected_shipment = JRequest::getInt('order_exported', '');

        $objList = array();
        $allObj = new stdClass;
        $allObj->value = VirtueMartModelZasilkovna_orders::NOT_EXPORTED;
        $allObj->text = JTEXT::_('PLG_VMSHIPMENT_PACKETERY_DROPDOWN_NOT_EXPORTED');
        $objList[] = $allObj;
        $zasObj = new stdClass;
        $zasObj->value = VirtueMartModelZasilkovna_orders::EXPORTED;
        $zasObj->text = JTEXT::_('PLG_VMSHIPMENT_PACKETERY_DROPDOWN_EXPORTED');
        $objList[] = $zasObj;

        return VmHTML::select('order_exported', $objList, $selected_shipment, 'class="inputbox" onchange="resetTaskAndSubmitForm(this.form);"');
    }

    /**
     * @return void
     */
    private function showDeprecationWarning()
    {
        $app = JFactory::getApplication();
        $readmeDeprecationLink['en'] = 'https://github.com/Zasilkovna/virtuemart3/blob/master/README.md#warning---feature-drop-plan-delivery-and-payment-limitations-settings';
        $readmeDeprecationLink['cz'] = 'https://github.com/Zasilkovna/virtuemart3/blob/master/README.md#upozorn%C4%9Bn%C3%AD---pl%C3%A1n-odstran%C4%9Bn%C3%AD-funkce-omezen%C3%AD-dopravy-a-platby';

        $langTag = JFactory::getLanguage()->getTag();
        if ($langTag === 'cs-CZ' || $langTag === 'sk-SK') {
            $langTag = 'cz';
        } else {
            $langTag = 'en';
        }

        $app = JFactory::getApplication();
        $deprecationMessage = JText::sprintf(
            'PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_DEPRACATION',
            '<a href="' . $readmeDeprecationLink[$langTag] . '" target="_blank">README.md</a>'
        );

        $app->enqueueMessage($deprecationMessage, 'warning');
    }
}
