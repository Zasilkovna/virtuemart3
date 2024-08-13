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

        return $result && (int)$result['zasilkovna_packet_id'] !== 0;
    }

    /**
     * @param int $virtuemart_order_id
     * @return \VirtueMartModelZasilkovna\Order\Order|null
     * @throws \InvalidArgumentException
     */
    public function getOrderByVmOrderId($virtuemart_order_id)
    {
        $order = null;
        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from(self::PACKETERY_ORDER_TABLE_NAME)
            ->where('virtuemart_order_id = ' . $this->db->quote($virtuemart_order_id));
        $this->db->setQuery($query);
        try {
            $order = Order::fromArray($this->db->loadAssoc());
        } catch (\RuntimeException $exception) {
            vmWarn(500, $query . ' ' . $exception->getMessage());
        }

        return $order;
    }

    /**
     * @param int[]|string[] $packetIds
     * @return string[]
     */
    public function getExternalCarrierPacketIdsByPacketIds(array $packetIds)
    {
        $escapedPacketIds = [];
        foreach ($packetIds as $packetId) {
            if (!is_numeric($packetId)) {
                throw new \InvalidArgumentException('Numeric packet ID is expected');
            }

            $escapedPacketIds[] = (int)$packetId;
        }

        if (empty($escapedPacketIds)) {
            return [];
        }

        $query = $this->db->getQuery(true);
        $query->select('DISTINCT `zasilkovna_packet_id`')
            ->from(self::PACKETERY_ORDER_TABLE_NAME)
            ->where('`is_carrier`=1')
            ->andWhere(
                sprintf(
                    '`zasilkovna_packet_id` IN (%s)',
                    implode(
                        ',',
                        $escapedPacketIds
                    )
                )
            );

        $this->db->setQuery($query);

        return $this->db->loadColumn() ?: [];
    }

    /**
     * @param array<string, mixed> $values
     * @return void
     */
    public function insertOrder($values)
    {
        foreach($values as $key => $value) {
            $values[$key] = $this->db->quote($value);
        }

        $query = $this->db->getQuery(true);
        $query->insert(self::PACKETERY_ORDER_TABLE_NAME);
        $query->columns(array_keys($values));
        $query->values(implode(',', $values));
        $this->db->setQuery($query);
        $this->db->execute();
    }
}
