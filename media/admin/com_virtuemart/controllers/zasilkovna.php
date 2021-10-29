<?php
/**
 *
 * Config controller
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
 * @version $Id: config.php 6188 2012-06-29 09:38:30Z Milbo $
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmController')) require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmcontroller.php');

/** @var VirtueMartModelZasilkovna */
VmModel::getModel('zasilkovna')->loadLanguage();


/**
 * Class VirtuemartControllerZasilkovna
 * @property $redirectPath
 */
class VirtuemartControllerZasilkovna extends VmController
{

    /**
     * Handle the save task.
     * @param int $data
     */
    public function save($data = 0)
    {
        vRequest::vmCheckToken();
        $data = vRequest::getPost();

        // normalization of numbers (convert decimal comma to decimal point)
        // global settings
        $itemKeyList = array('maximum_weight', 'default_price', 'free_shipping');
        foreach ($itemKeyList as $key)
        {
            if (!empty($data['global']['values'][$key]))
            {
                $data['global']['values'][$key] = str_replace(',', '.', $data['global']['values'][$key]);
            }
        }

        // country settings (default values and weight rules)
        /** @var VirtueMartModelZasilkovna $zasModel */
        $zasModel = VmModel::getModel('zasilkovna');
        $supportedCountries = array_keys($zasModel->getCountries(TRUE));

        foreach ($supportedCountries as $countryCode)
        {
            foreach ($data[$countryCode] as $countryRuleKey => $countryRuleValues)
            {
                $propertyNames = ('values' === $countryRuleKey ? array('default_price', 'free_shipping') : array('weight_from', 'weight_to', 'price'));
                foreach ($propertyNames as $property)
                {
                    if (!empty($countryRuleValues[$property]))
                    {
                        $data[$countryCode][$countryRuleKey][$property] = str_replace(',', '.', $countryRuleValues[$property]);
                    }
                }
            }
        }

        $db =& JFactory::getDBO();
        $q = "UPDATE #__extensions SET custom_data='" . serialize($data) . "' WHERE element='zasilkovna'";
        $db->setQuery($q);
        $db->execute();

        $redir = 'index.php?option=com_virtuemart';
        if(JRequest::getCmd('task') == 'apply') {
            $redir = $this->redirectPath;
        }
        $this->updateZasilkovnaOrders();
        $this->setRedirect($redir);
    }


    /**
     * Save change packets info to zasilkovna plugin db.
     */
    public function updateZasilkovnaOrders()
    {
        /** @var VirtueMartModelZasilkovna_orders $zasOrdersModel */
        $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
        $zasOrdersModel->updateOrders($_POST['orders']);
    }


    /**
     * Export orders to csv.
     */
    public function exportZasilkovnaOrders()
    {
        /** @var VirtueMartModelZasilkovna_orders $zasOrdersModel */
        $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
        $zasOrdersModel->exportToCSV($_POST['exportOrders']);
    }


    /**
     * Update and export orders to csv.
     */
    public function updateAndExportZasilkovnaOrders()
    {
        $this->updateZasilkovnaOrders();
        $this->exportZasilkovnaOrders();
        $this->setRedirect($this->redirectPath);
    }

    public function printLabels() {
        $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
        $result = $zasOrdersModel->printLabels($_POST['printLabels'], $_POST['print_type'], $_POST['label_first_page_skip']);
        foreach($result as $error) {
            JError::raiseWarning(100, $error);
        }
        $this->setRedirect($this->redirectPath);
    }

    /**
     * Cancel order.
     */
    public function cancelOrderSubmitToZasilkovna()
    {
        /** @var VirtueMartModelZasilkovna_orders $zasOrdersModel */
        $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
        $zasOrdersModel->cancelOrderSubmitToZasilkovna($_GET['cancel_order_id']);

        // Create message content.
        $msg = NULL;

        if($this->setRedirect($this->redirectPath))
        {
            $msg = JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_SUBMIT_CANCELED');//"Všechny objednávky byly přidány do systému Zásilkovny.";
        }

        $this->setRedirect($this->redirectPath, $msg, 'message');
    }

    public function submitToZasilkovna() {
        $this->updateZasilkovnaOrders();
        $zasOrdersModel = VmModel::getModel('zasilkovna_orders');
        $result = $zasOrdersModel->submitToZasilkovna($_POST['exportOrders']);
        $exportedOrders = $result['exported'];
        $failedOrders = $result['failed'];
        if(count($_POST['exportOrders']) == 0) {
            $msg = JText::_('PLG_VMSHIPMENT_PACKETERY_NO_ORDERS_SELECTED');//"Žádné objednávky nebyly vybrány k odeslání.";
            $type = 'error';
        }
        else if(count($_POST['exportOrders']) == count($exportedOrders)) {
            $msg = JText::_('PLG_VMSHIPMENT_PACKETERY_ALL_ORDERS_SUBMITTED');//"Všechny objednávky byly přidány do systému Zásilkovny.";
            $type = 'message';
        }
        else {
            JError::raiseWarning(100, JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMITTED_ORDERS') . ": " . count($exportedOrders) . ". " . JText::_('PLG_VMSHIPMENT_PACKETERY_NOT_SUBMITTED_ORDERS') . ": (" . count($failedOrders) . "):");
            foreach($failedOrders as $failedOrder) {
                JError::raiseWarning(100, $failedOrder['order_number'] . ": " . $failedOrder['message']);
            }
            $type = 'error';
        }
        $this->setRedirect($this->redirectPath, $msg, $type);
    }

    /**
     * Overwrite the remove task
     * Removing config is forbidden.
     * @author Max Milbers
     */
    public function remove()
    {
        $msg = JText::_('COM_VIRTUEMART_ERROR_CONFIGS_COULD_NOT_BE_DELETED');

        $this->setRedirect($this->redirectPath, $msg);
    }

}
