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

        $configModel = VmModel::getModel('config');

        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel();

        if(!$model->getConfig(VirtuemartControllerZasilkovna::ZASILKOVNA_LIMITATIONS_REMOVED_NOTICE_DISMISSED, false)) {
            $this->showLimitationsRemovedNotice();
        }

        $shipModel = VmModel::getModel('shipmentmethod');
        $shipments = $shipModel->getShipments();
        function cmpShipments($a, $b) {
            return strcmp($a->virtuemart_shipmentmethod_id, $b->virtuemart_shipmentmethod_id);
        }

        usort($shipments, "cmpShipments"); //sort, coz it comes in random order
        $this->shipmentMethods = $shipments;

        $this->moduleVersion = VirtueMartModelZasilkovna::VERSION;
        $this->errors = $model->errors;
        $this->warnings = $model->warnings;

        $paymentModel = VmModel::getModel('paymentmethod');
        $payments = $paymentModel->getPayments();
        function cmpPayments($a, $b) {
            return strcmp($a->virtuemart_paymentmethod_id, $b->virtuemart_paymentmethod_id);
        }

        usort($payments, "cmpPayments"); //sort, coz it comes in random order
        $this->paymentMethods = $payments;

        $usermodel = VmModel::getModel('user');

        /** @var VirtueMartModelZasilkovna_orders $ordersModel */
        $ordersModel = VmModel::getModel('zasilkovna_orders');

        if(!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

        $orderStatusModel = VmModel::getModel('orderstatus');
        $orderStates = $orderStatusModel->getOrderStatusList();
        
        $orderStatusOptions[] = JHtml::_('select.option', null, JText::_('PLG_VMSHIPMENT_PACKETERY_SELECT_ORDER_STATUS'));
        foreach($orderStates as $orderState) {
            $orderStatusOptions[] = JHtml::_('select.option', $orderState->order_status_code, JTEXT::_($orderState->order_status_name));
        }

        $this->SetViewTitle('ORDER');


        JToolBarHelper::title(JText::_('COM_VIRTUEMART_CONFIG'), 'head vm_config_48');


        $config = VmConfig::loadConfig();
        unset ($config->_params['pdf_invoice']); // parameter remove and replaced by inv_os
        $this->config = $config;

        $mainframe = JFactory::getApplication();
        $this->joomlaconfig = $mainframe;

        $this->userparams = JComponentHelper::getParams('com_users');
        $this->jTemplateList = ShopFunctions::renderTemplateList(JText::_('COM_VIRTUEMART_ADMIN_CFG_JOOMLA_TEMPLATE_DEFAULT'));
        $this->vmLayoutList = $configModel->getLayoutList('virtuemart');
        $this->categoryLayoutList = $configModel->getLayoutList('category');
        $this->productLayoutList = $configModel->getLayoutList('productdetails');
        $this->noimagelist = $configModel->getNoImageList();
        $this->orderStatusModel = $orderStatusModel;
        $this->currConverterList = $configModel->getCurrencyConverterList();
        $this->activeLanguages = $configModel->getActiveLanguages(VmConfig::get('active_languages'));
        $this->orderByFields = $configModel->getProductFilterFields('browse_orderby_fields');
        $this->searchFields = $configModel->getProductFilterFields('browse_search_fields');
        $this->aclGroups = $usermodel->getAclGroupIndentedTree();

        if(is_Dir(VmConfig::get('vmtemplate') . DS . 'images' . DS . 'availability' . DS)) {
            $imagePath = VmConfig::get('vmtemplate') . '/images/availability/';
        }
        else {
            $imagePath = '/components/com_virtuemart/assets/images/availability/';
        }
        $this->imagePath = $imagePath;

        $this->setLayout('orders');


        $this->addStandardDefaultViewLists($ordersModel, 'created_on');
        $this->lists['state_list'] = $this->renderOrderstatesList();

        $ordersModel->setPaginationLimits(true);
        
        $shipping_method_selectec_id = $mainframe->input->getInt('order_exported', 0);
        $orderslist = $ordersModel->getOrdersListByShipment($shipping_method_selectec_id);

        $this->orderstatuses = $orderStates;
        $this->orderStatusOptions = $orderStatusOptions;

        /*
         * UpdateStatus removed from the toolbar; don't understand how this was intented to work but
         * the order ID's aren't properly passed. Might be readded later; the controller needs to handle
         * the arguments.
         */

        /* Toolbar */
        JToolBarHelper::save('updateAndExportZasilkovnaOrders', 'CSV');
        /** @var VirtueMartModelZasilkovna $zas_model */
        $zas_model = VmModel::getModel('zasilkovna');

        $this->media_url = $zas_model->_media_url;

        JToolBarHelper::save('submitToZasilkovna', JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMIT_ORDERS_TO_ZASILKOVNA'));
        JToolBarHelper::custom('printLabels', 'copy', '', JText::_('PLG_VMSHIPMENT_PACKETERY_DO_PRINT_LABELS'), false, false);

        /* Assign the data */
        $this->orderslist = $orderslist;
        $this->pagination = $ordersModel->getPagination();

        $this->shipmentSelect = $this->renderShipmentsList();
        $model->raiseErrors($mainframe);
        parent::display($tpl);
    }

    public function renderOrderstatesList() {
        $app = JFactory::getApplication();
        $orderstates = $app->input->getString('order_status_code', '');

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
        $app = JFactory::getApplication();
        $selected_shipment = (int)$app->input->get('order_exported');

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
    private function showLimitationsRemovedNotice()
    {
        $readmeDeprecationUrl = sprintf(
            'https://github.com/Zasilkovna/virtuemart3/blob/master/README.md%s',
            JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_DEPRECATION_README_URL_ANCHOR')
        );

        $dismissUrl = Juri::base(true) . '/index.php?option=com_virtuemart&view=zasilkovna&task=dismissLimitationsRemovedNotice';
        $dismissButtonHtml = sprintf(
            '<a class="btn btn-warning" href="%s">%s</a>',
            $dismissUrl,
            JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_DEPRECATION_DISMISS')
        );

        $limitationsRemovedMessageHtml = sprintf(
            JText::_('PLG_VMSHIPMENT_PACKETERY_PAYMENT_SHIPMENT_RESTRICTION_DEPRECATION'),
            sprintf('<a href="%s" target="_blank">', $readmeDeprecationUrl),
            '</a>'
        );

        $fullFlashMessage = sprintf('%s<br>%s', $limitationsRemovedMessageHtml, $dismissButtonHtml);

        /** @var JApplicationCms $app */
        $app = JFactory::getApplication();
        $app->enqueueMessage($fullFlashMessage, \VirtueMartModelZasilkovna\FlashMessage::TYPE_NOTICE);
    }
}
