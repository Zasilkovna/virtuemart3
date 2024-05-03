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
    const ZASILKOVNA_LIMITATIONS_REMOVED_NOTICE_DISMISSED = 'zasilkovna_limitations_removed_notice_dismissed';
    public const FROM_POST = 'fromPost';
    public const FORM_VALUES = 'formValues';

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
        $model->raiseErrors(JFactory::getApplication());
        
        if (empty($model->errors)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATED'), FlashMessage::TYPE_MESSAGE);
        }

        $this->setRedirectWithMessage($this->redirectPath, $message);
    }

    /**
     * Handle the save task.
     * @param mixed $data
     * @throws Exception
     */
    public function save($data = 0): void
    {
        vRequest::vmCheckToken();
        if ($data === 0) {
            $data = vRequest::getPost();
        }

        $message = $this->updateZasilkovnaConfig($data);

        $redir = 'index.php?option=com_virtuemart';
        $app = JFactory::getApplication();
        if ($app->input->getString('task') === 'apply') {
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
        $this->zasOrdersModel->exportToCSV($this->getExportOrders());
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

        $app = JFactory::getApplication();
        foreach ($result as $error) {
            $app->enqueueMessage($error, 'warning');
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
        $exportOrders = $this->getExportOrders();
        $result = $this->zasOrdersModel->submitToZasilkovna($exportOrders);
        $exportedOrders = $result['exported'];
        $failedOrders = $result['failed'];
        $message = null;

        if (count($exportOrders) === 0) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_NO_ORDERS_SELECTED'), FlashMessage::TYPE_ERROR);
        } elseif (count($exportOrders) === count($exportedOrders)) {
            $message = new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_ALL_ORDERS_SUBMITTED'), FlashMessage::TYPE_MESSAGE);
        } else {
            $app = JFactory::getApplication();
            $app->enqueueMessage(
                sprintf(
                    '%s: %s. %s: (%s):',
                    JText::_('PLG_VMSHIPMENT_PACKETERY_SUBMITTED_ORDERS'),
                    count($exportedOrders),
                    JText::_('PLG_VMSHIPMENT_PACKETERY_NOT_SUBMITTED_ORDERS'),
                    count($failedOrders)
                ),
                'warning'
            );
            foreach ($failedOrders as $failedOrder) {
                $app->enqueueMessage($failedOrder['order_number'] . ": " . $failedOrder['message'], 'warning');
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
        $app = JFactory::getApplication();
        if (!$validationReport->isValid()) {
            foreach ($validationReport->getErrors() as $error) {
                $app->enqueueMessage($error, 'warning');
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
                $app->enqueueMessage($error, 'warning');
            }
        }

        $this->setRedirectWithMessage($redirectPath, $message);
    }

    /**
     * @return void
     */
    public function dismissLimitationsRemovedNotice()
    {
        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $config = $model->loadConfig();

        if (!isset($config[self::ZASILKOVNA_LIMITATIONS_REMOVED_NOTICE_DISMISSED]) || $config[self::ZASILKOVNA_LIMITATIONS_REMOVED_NOTICE_DISMISSED] === false) {
            $config[self::ZASILKOVNA_LIMITATIONS_REMOVED_NOTICE_DISMISSED] = true;
            $model->updateConfig($config);
        }

        $this->setRedirect($this->redirectPath);
    }

    /**
     * @return array
     */
    private function getExportOrders()
    {
        return isset($_POST['exportOrders']) && is_array($_POST['exportOrders']) ? $_POST['exportOrders'] : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return FlashMessage
     */
    private function updateZasilkovnaConfig(array $data): FlashMessage
    {
        $configStorage = new VirtueMartModelZasilkovna\SessionStorage(JFactory::getSession(), 'packeteryConfig');
        $configStorage->set(self::FROM_POST, self::FORM_VALUES, $data);

        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $currentData = $model->loadConfig();

        $formValidator = new VirtueMartModelZasilkovna\ConfigurationValidator($data);
        $formValidator->validate();

        if (!$formValidator->isValid()) {
            $errors = $formValidator->getErrors();
            $messages = [];

            //$error is either error string translation key, or array where 1st element is sprintf template and others are sprintf arguments (all untranslated)
            foreach ($errors as $formField => $error) {
                if (!is_array($error)) {
                    $messages[] = JText::_($error);
                } else {
                    $messages[] = sprintf(
                        JText::_($error[0]),
                        ...array_map(
                            static function ($item) {
                                return JText::_($item);
                            },
                            array_slice($error, 1))
                    );
                }
            }

            return  new FlashMessage(implode("<br>", $messages), FlashMessage::TYPE_ERROR);
        }

        $model->updateConfig(array_replace_recursive($currentData, $formValidator->getValidData()));
        $configStorage->clear(self::FROM_POST, self::FORM_VALUES);

        return  new FlashMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_SAVED'), FlashMessage::TYPE_MESSAGE);
    }
}
