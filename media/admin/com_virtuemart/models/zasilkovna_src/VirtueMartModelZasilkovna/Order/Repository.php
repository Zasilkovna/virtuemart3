<?php

namespace VirtueMartModelZasilkovna\Order;

class Repository
{
    const PACKETERY_ORDER_TABLE_NAME = "#__virtuemart_shipment_plg_zasilkovna";

    /**
     * @var \JDatabaseDriver
     */
    private $db;

    public function __construct()
    {
        $this->db = \JFactory::getDbo();
    }

    /**
     * @param int $vmOrderId
     * @return bool
     */
    public function hasOrderPacketId($vmOrderId)
    {

        $query = $this->db->getQuery(true);
        $query->select('zasilkovna_packet_id');
        $query->from(self::PACKETERY_ORDER_TABLE_NAME);
        $query->where('virtuemart_order_id = ' . $this->db->quote($vmOrderId));
        $this->db->setQuery($query);

        $result = $this->db->loadAssoc();

        return $result && $result['zasilkovna_packet_id'] !== "0";
    }

    /**
     * @param int $virtuemart_order_id
     * @return \VirtueMartModelZasilkovna\Order\Order
     */
    public function getOrderByVmOrderId($virtuemart_order_id)
    {
        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from(self::PACKETERY_ORDER_TABLE_NAME)
            ->where('virtuemart_order_id = ' . $this->db->quote($virtuemart_order_id));
        $this->db->setQuery($query);
        $order = $this->db->loadObject(\VirtueMartModelZasilkovna\Order\Order::class);

        if(!$order) {
            vmWarn(500, $query . " " . $this->db->getErrorMsg());
        }

        return $order;
    }
}
