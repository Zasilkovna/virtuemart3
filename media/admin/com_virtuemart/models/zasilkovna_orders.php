<?php
/**
 *
 * Model for orders with zasilkovna shipping
 *
 * @package    zasilkovna_orders
 * @author Zasilkovna
 * @link http://www.zasilkovna.cz
 * @version 1.1
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmModel')) require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmmodel.php');

use VirtueMartModelZasilkovna\Label;

/**
 * Class VirtueMartModelZasilkovna_orders
 */
class VirtueMartModelZasilkovna_orders extends VmModel
{
    const ALL_ORDERS = -5;
    const EXPORTED = -4;
    const NOT_EXPORTED = -3;
    const ZASILKOVNA_ORDERS = 0;

    /** @var VirtueMartModelZasilkovna */
    private $zas_model;

    /** @var string[] */
    public $errors;

    /**  @var VirtueMartModelZasilkovna\Order\Repository */
    private  $repository;

    /**
     * VirtueMartModelZasilkovna_orders constructor.
     * @throws Exception
     */
    public function __construct() {
        parent::__construct();
        $this->zas_model = VmModel::getModel('zasilkovna');
        $this->errors = [];
        $this->setMainTable('orders');
        $this->addvalidOrderingFieldName(array('order_name', 'payment_method', 'virtuemart_order_id'));
        $this->repository = new \VirtueMartModelZasilkovna\Order\Repository();
    }

    /**
     * @param string $content
     * @return void
     */
    private function echoLabelContent($content)
    {
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="labels-' . date("Ymd-His") . '.pdf"');
        echo $content;
    }

    /**
     * @param int[]|string[] $packetIds
     * @param Label\Format $format
     * @param int $offset
     * @return array
     */
    public function printPacketaLabels(array $packetIds, Label\Format $format, $offset = 0)
    {
        $errors = [];
        if (empty($packetIds)) {
            $errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_NO_PACKET_TO_PRINT');

            return $errors;
        }

        $soapClient = new SoapClient(VirtueMartModelZasilkovna::PACKETA_WSDL);
        $apiPassword = $this->zas_model->api_pass;

        try {
            $pdfContent = $soapClient->packetsLabelsPdf($apiPassword, $packetIds, $format->getValue(), $offset);
        } catch (SoapFault $e) {
            $errors[] = $e->faultstring . " ";
            if (is_array($e->detail->PacketIdsFault->ids->packetId)) {
                $wrongPacketIds = "";
                foreach ($e->detail->PacketIdsFault->ids->packetId as $wrongPacketId) {
                    $wrongPacketIds .= $wrongPacketId . " ";
                }
                $errors[] = $wrongPacketIds;
            } else {
                if (is_object($e->detail->PacketIdsFault)) { //only one error
                    $errors[] = $e->detail->PacketIdsFault->ids->packetId;
                }
            }

            return $errors;
        }

        $this->setPrintLabelFlag($packetIds);
        $this->echoLabelContent($pdfContent);

        exit;
    }

    /**
     * @param int[]|string[] $packetIds
     * @param Label\Format $format
     * @param int $offset
     * @return array
     */
    public function printCarrierLabels(array $packetIds, Label\Format $format, $offset = 0)
    {
        $errors = [];
        if (empty($packetIds)) {
            $errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_NO_PACKET_TO_PRINT');

            return $errors;
        }

        $validPacketIds = $this->repository->getExternalCarrierPacketIdsByPacketIds($packetIds);
        if (empty($validPacketIds)) {
            $errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_NO_PACKET_TO_PRINT');

            return $errors;
        }

        $soapClient = new SoapClient(VirtueMartModelZasilkovna::PACKETA_WSDL);
        $apiPassword = $this->zas_model->api_pass;

        try {
            $packetsWithCarrierNumbers = [];

            foreach ($validPacketIds as $packetId) {
                $courierNumber = $soapClient->packetCourierNumberV2($apiPassword, $packetId)->courierNumber;

                $packetsWithCarrierNumbers[] = [
                    'packetId' => $packetId,
                    'courierNumber' => $courierNumber,
                ];
            }

            $pdfContent = $soapClient->packetsCourierLabelsPdf(
                $apiPassword,
                $packetsWithCarrierNumbers,
                $offset,
                $format->getValue()
            );

        } catch (SoapFault $soapFault) {
            $errors[] = $soapFault->faultstring;

            return $errors;
        }

        $this->setPrintLabelFlag($validPacketIds);
        $this->echoLabelContent($pdfContent);

        exit;
    }

    public function submitToZasilkovna($orders_id_arr) {

        $db = JFactory::getDBO();
        $gw = new SoapClient(VirtueMartModelZasilkovna::PACKETA_WSDL);
        $zas_model = VmModel::getModel('zasilkovna');

        $apiPassword = $zas_model->api_pass;
        $ordersForExport = $this->prepareForExport($orders_id_arr);
        $exportedOrders = array();
        $failedOrders = array();
        $exportedOrdersNumber = array();
        foreach($ordersForExport as $order) {
            try {
                if(isset($order['zasilkovna_packet_id']) && ($order['zasilkovna_packet_id'] != 0)) {//some better check?
                    throw new Exception(JTEXT::_('PLG_VMSHIPMENT_PACKETERY_ALREADY_SUBMITTED'));
                }
                $attributes = array(
                    'number' => $order['order_number'],
                    'name' => $order['recipient_firstname'],
                    'surname' => $order['recipient_lastname'],
                    'email' => $order['recipient_email'],
                    'phone' => $order['recipient_phone'],
                    'addressId' => $order['point_id'],
                    'cod' => $order['packet_cod'],
                    'value' => $order['value'],
                    'weight' => $order['weight'],
                    'currency' => $order['currency'],
                    'eshop' => $sender_label = $this->zas_model->getConfig('zasilkovna_eshop_label'),
                    'adultContent' => (int)$order['adult_content'] === 1,
                );

                if (!empty($order['carrier_point'])) {
                    $attributes['carrierPickupPoint'] = $order['carrier_point'];
                }

                if ((int)$order['is_carrier'] === 1 && $order['carrier_point'] === '') {
                    $attributes['street'] = $order['recipient_street'];
                    $attributes['city'] = $order['recipient_city'];
                    $attributes['zip'] = $order['recipient_zip'];
                    if ($order['recipient_house_number']) {
                        $attributes['houseNumber'] = $order['recipient_house_number'];
                    }
                }

                $packet = $gw->createPacket($apiPassword, $attributes);
                $q = "UPDATE " . $this->zas_model->getDbTableName() . " SET zasilkovna_packet_id=" . (int)$packet->id . " WHERE order_number = '" . $db->escape($order['order_number']) . "'; ";
                $db->setQuery($q);
                $db->execute();
                $exportedOrders[] = array('order_number' => $order['order_number'], 'zasilkovna_id' => $packet->id);
                $exportedOrdersNumber[] = $order['order_number'];
            }
            catch(Exception $e) {
                $error_msg = "";
                if(get_class($e) == 'SoapFault') {
                    if(is_array($e->detail->PacketAttributesFault->attributes->fault)) { //more errors
                        foreach($e->detail->PacketAttributesFault->attributes->fault as $error) {
                            $error_msg .= $error->name . ": " . $error->fault . " ";
                        }
                    }
                    else if(is_object($e->detail->PacketAttributesFault->attributes->fault)) { //only one error
                        $error_msg .= $e->detail->PacketAttributesFault->attributes->fault->name . ": " . $e->detail->PacketAttributesFault->attributes->fault->fault . " ";
                    }
                    else { //structure error (missing parameter etc)
                        $error_msg .= $e->faultstring . " ";
                    }
                }
                else {
                    $error_msg = $e->getMessage();
                }

                $failedOrders[] = array('order_number' => $order['order_number'], 'message' => $error_msg);
            }
        }

        $this->setExportedFlag($exportedOrdersNumber);

        return array('exported' => $exportedOrders, 'failed' => $failedOrders);
    }

    public function cancelOrderSubmitToZasilkovna($order_id) {
        if(!isset($order_id)) return false;
        $db = JFactory::getDBO();
        $q = "UPDATE " . $this->zas_model->getDbTableName() . " SET exported=0, zasilkovna_packet_id=0 WHERE virtuemart_order_id = " . (int)$order_id . ";";
        $db->setQuery($q);
        $db->execute();

        return true;
    }

    private function setPrintLabelFlag($printedLabels) {
        if(count($printedLabels)) {
            $db = JFactory::getDBO();

            $escapedLabels = [];
            foreach ($printedLabels as $printedLabel) {
                $escapedLabels[] = $db->escape($printedLabel);
            }

            $printedLabelsString = implode("','", $escapedLabels);
            $q = "UPDATE " . $this->zas_model->getDbTableName() . " SET printed_label=1 WHERE zasilkovna_packet_id IN ('" . $printedLabelsString . "') ";
            $db->setQuery($q);
            $db->execute();
        }
    }

    private function setExportedFlag($exportedOrders) {
        if(count($exportedOrders)) {
            $db = JFactory::getDBO();

            $escapedOrders = [];
            foreach ($exportedOrders as $exportedOrder) {
                $escapedOrders[] = $db->escape($exportedOrder);
            }

            $exportedOrdersString = implode("','", $escapedOrders);
            $q = "UPDATE " . $this->zas_model->getDbTableName() . " SET exported=1 WHERE order_number IN ('" . $exportedOrdersString . "') ";
            $db->setQuery($q);
            $db->execute();
        }
    }

    public function exportToCSV($orders_id_arr) {
        $app = JFactory::getApplication();
        if(sizeof($orders_id_arr) == 0) {
            $app->enqueueMessage(JTEXT::_('PLG_VMSHIPMENT_PACKETERY_NO_PACKET_TO_CSV'), 'warning');

            return;
        }

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"export-" . date("Ymd-His") . ".csv\"");
        $ordersForExport = $this->prepareForExport($orders_id_arr);

        // check that all orders selected for export have correctly filled target branch ID
        $unusableOrders = array();
        foreach ($ordersForExport as $order) {
            if ((int) $order['point_id'] <= 0) {
                $unusableOrders[] = $order['order_number'];
            }
        }

        if (!empty($unusableOrders)) {
            $warningMesage = JText::_('PLG_VMSHIPMENT_PACKETERY_MISSING_BRANCH_CSV_EXPORT')
                . implode(',', $unusableOrders);
            $app->enqueueMessage($warningMesage, 'warning');
            return;
        }

        $exportedOrders = array();
        $sender_label = $this->zas_model->getConfig('zasilkovna_eshop_label');

        echo '"verze 5"'.PHP_EOL.PHP_EOL;

        foreach($ordersForExport as $row) {
            //foreach ($row as $key => $col)if(empty($col))$row[$key]="";

            $order = array(
                $row['order_number'],
                $row['recipient_firstname'],
                $row['recipient_lastname'],
                $row['recipient_company'],
                $row['recipient_email'],
                $row['recipient_phone'],
                $row['packet_cod'],
                $row['currency'],
                $row['value'],
                $row['weight'],
                $row['point_id'],
                $sender_label,
                ($row['adult_content'])? "1" : "0", //adult content
                '', //delayed delivery
                $row['recipient_street'],
                $row['recipient_house_number'],
                $row['recipient_city'],
                $row['recipient_zip'],
                $row['carrier_point'],
                $row['width'],
                $row['height'],
                $row['depth'],
            );

            echo "," . implode(',', $order) . PHP_EOL;
            $exportedOrders[]=$row['order_number'];
        }
        $this->setExportedFlag($exportedOrders);
        exit();
    }

    /**
     * @param string $orderNumber
     * @return float
     */
    public function getOrderTotal($orderNumber) {
        $db = JFactory::getDBO();
        $db->setQuery('SELECT `order_total` FROM `#__virtuemart_orders` WHERE `order_number` = ' . $db->quote($orderNumber));

        return (float) $db->loadResult();
    }

    public function updateOrders($orders) {
        $db = JFactory::getDBO();

        foreach($orders as $key => $order) {
            $q = "UPDATE " . $this->zas_model->getDbTableName() . " SET ";
            $set_q = array();
            if ((int)$order['submitted'] === 1) {
                //$set_q[] = " zasilkovna_packet_id = " . $order['zasilkovna_packet_id'];
                continue; // skip update of order if order is submitted
            }
            else {
				//$fmt = new NumberFormatter( 'de_DE', NumberFormatter::DECIMAL );

                $set_q[] = " packet_cod = " . (float) str_replace(',', '.', $order['packet_cod']) . " ";
                //$set_q[] = " email = '" . $db->escape($order['email']) . "' ";
                //$set_q[] = " phone = '" . $db->escape($order['phone']) . "' ";

                if ($order['zasilkovna_packet_price'] === '') {
                    $order['zasilkovna_packet_price'] = $this->getOrderTotal($order['order_number']);
                }
                $set_q[] = " zasilkovna_packet_price = " . (float)str_replace(',', '.', $order['zasilkovna_packet_price']) . " ";

                if ($order['weight'] === '') {
                    $set_q[] = " weight = NULL ";
                } else {
                    $set_q[] = " weight = " . (float)$order['weight'] . " ";
                }

                if(isset($order['adult_content']) && $order['adult_content'] == 'on') {
                    $set_q[] = " adult_content = 1";
                }
                else {
                    $set_q[] = " adult_content = 0";
                }
            }
            foreach(array('address', 'city', 'zip_code') as $field) {
                if(isset($order[$field])) {
                    $set_q[] = " " . $field . " = '" . $db->escape($order[$field]) . "' ";
                }
            }
            $q .= implode(' , ', $set_q) . " WHERE order_number='" . $db->escape($order['order_number']) . "'; ";

            $db->setQuery($q);
            $db->execute();
        }

    }

    private function createConvertInstance() {
        $this->_app = JFactory::getApplication();
        if(empty($vendorId)) $vendorId = 1;

        $this->_db = JFactory::getDBO();
        $q = 'SELECT `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`="' . (int)$vendorId . '"';
        $this->_db->setQuery($q);
        $this->_vendorCurrency = $this->_db->loadResult();

        $converterFile = VmConfig::get('currency_converter_module');

        if(file_exists(JPATH_VM_ADMINISTRATOR . DS . 'plugins' . DS . 'currency_converter' . DS . $converterFile)) {
            $module_filename = substr($converterFile, 0, -4);
            require_once(JPATH_VM_ADMINISTRATOR . DS . 'plugins' . DS . 'currency_converter' . DS . $converterFile);
            if(class_exists($module_filename)) {
                $this->_currencyConverter = new $module_filename();
            }
        }
        else {
            if(!class_exists('convertECB')) require(JPATH_VM_ADMINISTRATOR . DS . 'plugins' . DS . 'currency_converter' . DS . 'convertECB.php');
            $this->_currencyConverter = new convertECB();

        }
    }

    protected function prepareForExport($orders_arr)
    {
        if (!$orders_arr)
        {
            return;
        }

        $db = JFactory::getDBO();
        $orderNumbers = [];
        foreach ($orders_arr as $orderNumber) {
            $orderNumbers[] = $db->escape($orderNumber);
        }

        $ordersForINStatement = implode("','", $orderNumbers);
        $q = "SELECT o.order_number,curr.currency_code_3 order_currency_name,
        plg.zasilkovna_packet_price order_total,oi.first_name,oi.last_name,
        oi_bt.email,IFNULL(oi.phone_1, oi_bt.phone_1) as phone_1,IFNULL(oi.phone_2, oi_bt.phone_2) as phone_2,plg.packet_cod,
       	plg.branch_id,plg.zasilkovna_packet_id, plg.carrier_pickup_point, plg.is_carrier, 
        plg.address as address, plg.adult_content AS adult_content, plg.city, plg.zip_code, plg.branch_currency, plg.weight FROM #__virtuemart_orders o ";
        $q .= "INNER JOIN #__virtuemart_order_userinfos oi ON o.virtuemart_order_id=oi.virtuemart_order_id AND oi.address_type = IF(o.STsameAsBT = 1, 'BT', 'ST') ";
        $q .= "INNER JOIN #__virtuemart_order_userinfos oi_bt ON o.virtuemart_order_id=oi_bt.virtuemart_order_id AND oi_bt.address_type = 'BT' ";
        $q .= "INNER JOIN " . $this->zas_model->getDbTableName() . " plg ON plg.order_number=o.order_number ";
        $q .= "LEFT JOIN #__virtuemart_currencies curr ON curr.virtuemart_currency_id=o.order_currency ";
        $q .= " WHERE o.order_number IN ('" . $ordersForINStatement . "') GROUP BY o.order_number";
        $db->setQuery($q);
        $rows = $db->loadAssocList();

        $this->createConvertInstance();
        $ordersForExport = array();
        foreach($rows as $key => $row) {
            $orderForExport = array();

            $streetMatches = array();

            $match = preg_match('/^(.*[^0-9]+) (([1-9][0-9]*)\/)?([1-9][0-9]*[a-cA-C]?)$/', $row['address'], $streetMatches);

            if (!$match) {
                $houseNumber = null;
                $street = $row['address'];
            } elseif (!isset($streetMatches[4])) {
                $houseNumber = null;
                $street = $streetMatches[1];
            } else {
                $houseNumber = (!empty($streetMatches[3])) ? $streetMatches[3] . "/" . $streetMatches[4] : $streetMatches[4];
                $street = $streetMatches[1];
            }


            $phone = "";
            foreach (array('phone_2', 'phone_1') as $field)
            {
                $phone_n = $this->normalizePhone($row[$field]);
                if (NULL !== $phone_n)
                {
                    $phone = $phone_n;
                }
            }

			$orderForExport['order_number'] = $row['order_number'];
			$orderForExport['recipient_firstname'] = $row['first_name'];
            $orderForExport['recipient_lastname'] = $row['last_name'];
            $orderForExport['recipient_company'] = "";
            $orderForExport['recipient_email'] = $row['email'];
            $orderForExport['recipient_phone'] = $phone;
            $orderForExport['packet_cod'] = $row['packet_cod'];
            $orderForExport['currency'] = $row["order_currency_name"];
            $orderForExport['value'] = $row['order_total'];
            $orderForExport['weight'] = $row['weight'];
            $orderForExport['point_id'] = $row['branch_id'];
            $orderForExport['adult_content'] = $row['adult_content'];
            $orderForExport['recipient_street'] = $street;
            $orderForExport['recipient_house_number'] = $houseNumber;
            $orderForExport['recipient_city'] = $row["city"];
            $orderForExport['recipient_zip'] = $row['zip_code'];
            $orderForExport['carrier_point'] = $row['carrier_pickup_point'];
            $orderForExport['is_carrier'] = $row['is_carrier'];
            $orderForExport['width'] = "";
            $orderForExport['height'] = "";
            $orderForExport['depth'] = "";
            $orderForExport['zasilkovna_packet_id'] = $row['zasilkovna_packet_id'];

            $ordersForExport[] = $orderForExport;
        }

        return $ordersForExport;
    }

    /**
    public function convertToBranchCurrency($value, $fromCurrency, $toCurrency) {
        $this->createConvertInstance();
        $branch_currency = strtoupper($toCurrency);
        $total = $this->_currencyConverter->convert($value, strtoupper($fromCurrency), $branch_currency);
        if($branch_currency == 'CZK') {
            $total = round($total);
        }
        else {
            $total = round($total, 2);
        }

        return $total;
    }
	 */


    /**
    private function csv_escape($s) {
        return str_replace('"', '\"', $s);
    }
	 */


    /**
     * Validates phone number and returns NULL if not valid
     * @param $value
     * @return string|null
     */
    private function normalizePhone($value)
    {
        $value = str_replace(' ', '', trim($value));

        // only + and numbers are allowed
        if (preg_match('/^\+?\d+$/', $value) !== 1)
        {
            $value = '';
        }

        return ($value ?: NULL);
    }


    /**
     * This function gets the orderId, for anonymous users
     *
     * @author Max Milbers
     */
    public function getOrderIdByOrderPass($orderNumber, $orderPass) {

        $db = JFactory::getDBO();
        $q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `order_pass`="' . $db->getEscaped($orderPass) . '" AND `order_number`="' . $db->getEscaped($orderNumber) . '"';
        $db->setQuery($q);
        $orderId = $db->loadResult();

        return $orderId;

    }

    /**
     * This function gets the orderId, for payment response
     * author Valerie Isaksen
     */
    public function getOrderIdByOrderNumber($orderNumber) {

        $db = JFactory::getDBO();
        $q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `order_number`="' . $db->getEscaped($orderNumber) . '"';
        $db->setQuery($q);
        $orderId = $db->loadResult();

        return $orderId;

    }

    /**
     * This function seems completly broken, JRequests are not allowed in the model, sql not escaped
     * This function gets the secured order Number, to send with paiement
     */
    public function getOrderNumber($virtuemart_order_id) {

        $db = JFactory::getDBO();
        $q = 'SELECT `order_number` FROM `#__virtuemart_orders` WHERE virtuemart_order_id="' . (int)$virtuemart_order_id . '"  ';
        $db->setQuery($q);
        $OrderNumber = $db->loadResult();

        return $OrderNumber;

    }

    /**
     * Was also broken, actually used?
     *
     * get next/previous order id
     */
    public function getOrderId($direction = 'DESC', $order_id) {

        if($direction == 'ASC') {
            $arrow = '>';
        }
        else {
            $arrow = '<';
        }

        $db = JFactory::getDBO();
        $q = 'SELECT `virtuemart_order_id` FROM `#__virtuemart_orders` WHERE `virtuemart_order_id`' . $arrow . (int)$order_id;
        $q .= ' ORDER BY `virtuemart_order_id` ' . $direction;
        $db->setQuery($q);

        if($oderId = $db->loadResult()) {
            return $oderId;
        }

        return 0;
    }


    /**
     * Load a single order
     */
    public function getOrder($virtuemart_order_id) {

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
			WHERE o.virtuemart_order_id=" . $virtuemart_order_id;
        $db->setQuery($q);
        $order['details'] = $db->loadObjectList('address_type');

        // Get the order history
        $q = "SELECT *
			FROM #__virtuemart_order_histories
			WHERE virtuemart_order_id=" . $virtuemart_order_id . "
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
			WHERE `virtuemart_order_id`="' . $virtuemart_order_id . '" group by `virtuemart_order_item_id`';

        $db->setQuery($q);
        $order['items'] = $db->loadObjectList();

        // Get the order items
        $q = "SELECT  *
			FROM #__virtuemart_order_calc_rules AS z
			WHERE  virtuemart_order_id=" . $virtuemart_order_id;
        $db->setQuery($q);
        $order['calc_rules'] = $db->loadObjectList();

        return $order;
    }

    public function getZasilkovnaOrdersList() {
        return $this->getOrdersListByShipment(0);
    }


    /*
        Copy _ALL_ orders to zasilkovna table
        (to be able to edit and submit all orders to zasilkovna)
    */
    /**
	private function copyOrdersToZasilkovnaTable()
    {
        $q = "INSERT INTO {$this->zas_model->getDbTableName()} (virtuemart_order_id,virtuemart_shipmentmethod_id,order_number,email,phone,branch_id,zasilkovna_packet_price,first_name,last_name,address,city,zip_code,virtuemart_country_id)
        
        SELECT o.virtuemart_order_id,o.virtuemart_shipmentmethod_id,o.order_number, oi_bt.email, IFNULL(oi.phone_1, oi_bt.phone_1) as phone_1,-1,o.order_total,oi.first_name,oi.last_name,oi.address_1,oi.city,oi.zip,oi.virtuemart_country_id FROM #__virtuemart_orders as o
            INNER JOIN #__virtuemart_order_userinfos as oi ON o.virtuemart_order_id = oi.virtuemart_order_id AND oi.address_type = IF(o.STsameAsBT = 1, 'BT', 'ST')
            INNER JOIN #__virtuemart_order_userinfos as oi_bt ON o.virtuemart_order_id = oi_bt.virtuemart_order_id AND oi_bt.address_type = 'BT'
            WHERE o.virtuemart_order_id NOT IN (SELECT virtuemart_order_id FROM {$this->zas_model->getDbTableName()})";
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $db->execute();
    }
 	*/

    public function getOrdersListByShipment($shipment_id = 0) {
        //$this->copyOrdersToZasilkovnaTable();
        //check if table already exists
        // -- it gets created when shiping module is run for the first time
        $db = JFactory::getDBO();
        $db->setQuery("SELECT order_number FROM " . $this->zas_model->getDbTableName());
        $res = $db->loadResult();
        if(!$res) return array();

        //$this->_noLimit = $noLimit;
        $select = " o.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS order_name "
            . ',pm.payment_name AS payment_method';
        $from = $this->getZasilkovnaOrdersListQuery();
        $select .= ', plg.printed_label,plg.address,plg.city,plg.zip_code,plg.virtuemart_country_id, plg.branch_id, 
        plg.exported AS exported,plg.is_cod AS is_cod, plg.packet_cod AS packet_cod, plg.branch_currency, plg.branch_name_street AS name_street, 
        brnch.country as country, plg.adult_content, plg.email, u_bt.email AS billing_email, 
        IF(IFNULL(u.phone_1, u_bt.phone_1) <> "", IFNULL(u.phone_1, u_bt.phone_1), IFNULL(u.phone_2, u_bt.phone_2)) as phone,              
        plg.zasilkovna_packet_id,plg.zasilkovna_packet_price,plg.weight ';
        if($shipment_id == self::ALL_ORDERS) {
            //no where statement => select all
            ;
        }
        else if($shipment_id == self::EXPORTED) {
            $where[] = ' exported = 1';
        }
        else if($shipment_id == self::NOT_EXPORTED) {
            $where[] = ' exported = 0';
        }
        else if($shipment_id == self::ZASILKOVNA_ORDERS) {
            $zas_methods = $this->zas_model->getShipmentMethodIds();
            $where[] = ' o.virtuemart_shipmentmethod_id IN (' . (!empty($zas_methods) ? implode(',', $zas_methods) : 'NULL') . ')';
        }
        else {
            //exact shipping method was selected, filter by its id.
            $where[] = ' o.virtuemart_shipmentmethod_id = ' . (int)$shipment_id;
        }

        $app = JFactory::getApplication();
        if ($search = (string)$app->get('search')) {

            $search = '"%' . $this->_db->getEscaped($search, true) . '%"';

            $where[] = ' ( u.first_name LIKE ' . $search . ' OR u.middle_name LIKE ' . $search . ' OR u.last_name LIKE ' . $search . ' OR `order_number` LIKE ' . $search . ')';
        }


        if ($order_status_code = (string)$app->get('order_status_code')) {
            $where[] = ' o.order_status = "' . $db->escape($order_status_code) . '" ';
        }

        if(count($where) > 0) {
            $whereString = ' WHERE (' . implode(' AND ', $where) . ') ';
        }
        else {
            $whereString = '';
        }

        if ($app->get('view') == 'orders') {
            $ordering = $this->_getOrdering();
        }
        else {
            $ordering = ' order by o.modified_on DESC';
        }

        $this->_data = $this->exeSortSearchListQuery(0, $select, $from, $whereString, '', $ordering);

        return $this->_data;
    }

    /**
     * List of tables to include for the product query
     *
     * @author Zasilkovna
     */
    private function getZasilkovnaOrdersListQuery() {
        $db = JFactory::getDBO();

        return ' FROM #__virtuemart_orders as o
			LEFT JOIN #__virtuemart_order_userinfos as u
			ON u.virtuemart_order_id = o.virtuemart_order_id AND u.address_type = IF(o.STsameAsBT = 1, "BT", "ST")
                        LEFT JOIN #__virtuemart_order_userinfos u_bt 
                        ON u_bt.virtuemart_order_id = o.virtuemart_order_id AND u_bt.address_type = "BT"
			LEFT JOIN #__virtuemart_paymentmethods_' . $db->escape(VMLANG) . ' as pm
			ON o.virtuemart_paymentmethod_id = pm.virtuemart_paymentmethod_id
			RIGHT JOIN ' . $this->zas_model->getDbTableName() . ' as plg ON plg.order_number=o.order_number
			LEFT JOIN #__virtuemart_zasilkovna_carriers as brnch ON brnch.id=plg.branch_id';
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
        if($table->load((int)$orderLineId)) {
            return $table;
        }
        else {
            $table->reset();
            $table->virtuemart_order_id = $orderId;

            return $table;
        }
    }

    /**
     * @param array $formData
     * @return void
     */
    public function updateOrderDetail(array $formData)
    {
        if ($this->repository->hasOrderPacketId((int)$formData['virtuemart_order_id'])) {
            $this->errors[] = JText::_('PLG_VMSHIPMENT_PACKETERY_ALREADY_SUBMITTED');

            return;
        }

        $formData['submitted'] = 0;

        $this->updateOrders([$formData['virtuemart_order_id'] => $formData]);
    }
}
