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

use VirtueMartModelZasilkovna\FlashMessage;

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmController')) require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmcontroller.php');

/** @var VirtueMartModelZasilkovna */
VmModel::getModel('zasilkovna')->loadLanguage();

use VirtueMartModelZasilkovna\Label;

/**
 * Class VirtuemartControllerZasilkovna
 * @property $redirectPath
 */
class VirtuemartControllerZasilkovna extends VmController
{
    const ZASILKOVNA_DEPRECATION_WARNING_DISMISSED = 'zasilkovna_deprecation_warning_dismissed';

    /** @var \VirtueMartModelZasilkovna\Order\Detail */
    private $orderDetail;

    /** @var VirtueMartModelZasilkovna_orders $zasOrdersModel */
    private $zasOrdersModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderDetail = new \VirtueMartModelZasilkovna\Order\Detail();
        $this->zasOrdersModel = VmModel::getModel('zasilkovna_orders');
    }

    /**
     * Updates carriers.
     */
    public function updateCarriers()
    {
        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $message = null;

        $model->updateCarriers();
        $model->raiseErrors();
        
        if (empty($model->errors)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATED'), FlashMessage::TYPE_MESSAGE);
        }

        $this->setRedirectWithMessage($this->redirectPath, $message);
    }

    /**
     * Handle the save task.
     * @param int $data
     * @throws Exception
     */
    public function save($data = 0)
    {
        vRequest::vmCheckToken();
        $data = vRequest::getPost();
        $message = null;

        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $currentData = $model->loadConfig();

        if (strlen($data['zasilkovna_api_pass']) !== 32) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_API_PASS_INVALID'), FlashMessage::TYPE_ERROR);
        } else {
            $model->updateConfig(array_replace_recursive($currentData, $data));
        }

        $redir = 'index.php?option=com_virtuemart';
        if (JRequest::getCmd('task') === 'apply') {
            $redir = $this->redirectPath;
        }
        $this->updateZasilkovnaOrders();
        $this->setRedirectWithMessage($redir, $message);
    }

    /**
     * Save change packets info to zasilkovna plugin db.
     */
    public function updateZasilkovnaOrders()
    {
        $this->zasOrdersModel->updateOrders($_POST['orders']);
    }

    /**
     * Export orders to csv.
     */
    public function exportZasilkovnaOrders()
    {
        $this->zasOrdersModel->exportToCSV($_POST['exportOrders']);
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

    public function printLabels()
    {
        $postData = vRequest::getPost();

        $packetIds = [];
        if (isset($postData['printLabels'])) {
            $packetIds = $postData['printLabels'];
        }

        if (strpos($postData['print_type'], 'carriers_') === 0) {
            $format = str_replace(['carriers_', '_'], ['', ' '], $postData['print_type']);
            $result = $this->zasOrdersModel->printCarrierLabels(
                $packetIds,
                new Label\Format($format),
                (int)$postData['label_first_page_skip']
            );
        } else {
            $format = str_replace('_', ' ', $postData['print_type']);
            $result = $this->zasOrdersModel->printPacketaLabels(
                $packetIds,
                new Label\Format($format),
                (int)$postData['label_first_page_skip']
            );
        }

        foreach ($result as $error) {
            JError::raiseWarning(100, $error);
        }

        $this->setRedirect($this->redirectPath);
    }

    /**
     * Cancel order.
     */
    public function cancelOrderSubmitToZasilkovna()
    {
        $this->zasOrdersModel->cancelOrderSubmitToZasilkovna($_GET['cancel_order_id']);

        // Create message content.
        $message = null;

        if ($this->setRedirect($this->redirectPath)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_SUBMIT_CANCELED'), FlashMessage::TYPE_MESSAGE);
        }

        $this->setRedirectWithMessage($this->redirectPath, $message);
    }

    public function submitToZasilkovna()
    {
        $this->updateZasilkovnaOrders();
        $result = $this->zasOrdersModel->submitToZasilkovna($_POST['exportOrders']);
        $exportedOrders = $result['exported'];
        $failedOrders = $result['failed'];
        $message = null;

        if (count($_POST['exportOrders']) === 0) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_NO_ORDERS_SELECTED'), FlashMessage::TYPE_ERROR);
        } elseif (count($_POST['exportOrders']) === count($exportedOrders)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_ALL_ORDERS_SUBMITTED'), FlashMessage::TYPE_MESSAGE);
        } else {
            JError::raiseWarning(100,
                JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMITTED_ORDERS') . ": " . count($exportedOrders) . ". " . JText::_('PLG_VMSHIPMENT_PACKETERY_NOT_SUBMITTED_ORDERS') . ": (" . count($failedOrders) . "):");
            foreach ($failedOrders as $failedOrder) {
                JError::raiseWarning(100, $failedOrder['order_number'] . ": " . $failedOrder['message']);
            }
            $this->messageType = FlashMessage::TYPE_ERROR;
        }

        $this->setRedirectWithMessage($this->redirectPath, $message);
    }

    /**
     * Overwrite the remove task
     * Removing config is forbidden.
     * @author Max Milbers
     */
    public function remove()
    {
        $message = new FlashMessage(JText::_('COM_VIRTUEMART_ERROR_CONFIGS_COULD_NOT_BE_DELETED'), FlashMessage::TYPE_MESSAGE);
        $this->setRedirectWithMessage($this->redirectPath, $message);
    }

    /**
     * @param $redirectPath
     * @param FlashMessage|null $message
     * @return JControllerLegacy
     */
    public function setRedirectWithMessage($redirectPath, FlashMessage $message = null)
    {
        return $this->setRedirect(
            $redirectPath,
            $message ? $message->getMessage() : null,
            $message ? $message->getType() : null);
    }

    /**
     * @return void
     */
    public function updatePacketeryOrderDetail()
    {
        vRequest::vmCheckToken();
        $formData = vRequest::getPost();
        $message = null;
        $redirectPath = sprintf(
            '%sindex.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=%s',
            JUri::base(false),
            $formData['virtuemart_order_id']
        );

        $validationReport = $this->orderDetail->validateFormData($formData);
        if (!$validationReport->isValid()) {
            foreach ($validationReport->getErrors() as $error) {
                JError::raiseWarning(600, $error);
            }
            $this->setRedirectWithMessage($redirectPath);

            return;
        }

        $this->zasOrdersModel->updateOrderDetail($formData);

        if (empty($zasOrdersModel->errors)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_DETAILS_UPDATED'),
                FlashMessage::TYPE_MESSAGE);
        } else {
            foreach ($zasOrdersModel->errors as $error) {
                JError::raiseWarning(600, $error);
            }
        }

        $this->setRedirectWithMessage($redirectPath, $message);
    }

    /**
     * @return void
     */
    public function dismissDeprecationWarning()
    {
        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $config = $model->loadConfig();

        if (!isset($config[self::ZASILKOVNA_DEPRECATION_WARNING_DISMISSED]) || $config[self::ZASILKOVNA_DEPRECATION_WARNING_DISMISSED] === false) {
            $config[self::ZASILKOVNA_DEPRECATION_WARNING_DISMISSED] = true;
            $model->updateConfig($config);
        }

        $this->setRedirect($this->redirectPath);
    }
}
