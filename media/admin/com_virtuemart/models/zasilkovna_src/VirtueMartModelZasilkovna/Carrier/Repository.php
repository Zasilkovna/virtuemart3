<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Repository
{
    /**
     * @param mixed|int $carrierId
     * @return bool
     */
    public function carrierExists($carrierId) {
        $carrierId = (int)$carrierId;
        $query = 'SELECT 1 FROM #__virtuemart_zasilkovna_carriers WHERE id = ' . $carrierId;

        $db = \JFactory::getDBO();
        $db->setQuery($query);

        if ($db->loadObject()) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed|int $carrierId
     * @param array $data
     */
    public function updateCarrier($carrierId, array $data) {
        $db = \JFactory::getDBO();
        $set = [];
        foreach ($data as $column => $columnData) {
            $columnData = $db->escape($columnData);
            $column = $db->escape($column);
            $set[] = sprintf(' %s = "%s" ', $column, $columnData);
        }
        $setImploded = implode(', ', $set);
        $carrierId = (int)$carrierId;

        $db->setQuery("UPDATE #__virtuemart_zasilkovna_carriers SET $setImploded WHERE id = $carrierId");
        $db->query();
    }

    /**
     * @param array $data
     */
    public function insertCarrier($data) {
        $db = \JFactory::getDBO();
        $columns = array_keys($data);
        $columnsImploded = implode(', ', $columns);

        foreach($data as &$item) {
            $item = sprintf('"%s"', $db->escape($item));
        }

        $imploded = implode(', ', $data);

        $db = \JFactory::getDBO();
        $db->setQuery("INSERT INTO #__virtuemart_zasilkovna_carriers ($columnsImploded) VALUES ($imploded)");
        $db->query();
    }

    /**
     * Gets total count of usable carriers.
     */
    public function getTotalUsableCarriersCount() {
        $db = \JFactory::getDBO();
        $db->setQuery("SELECT COUNT(*) AS counted FROM #__virtuemart_zasilkovna_carriers WHERE deleted = 0");
        $result = $db->loadObject();
        return (int)$result->counted;
    }

    /**
     * Gets all active carriers.
     *
     * @return array
     */
    public function getAllActiveCarrierIds() {
        $db = \JFactory::getDBO();
        $db->setQuery("SELECT id FROM #__virtuemart_zasilkovna_carriers WHERE deleted = 0");
        return $db->loadAssocList('id', 'id');
    }

    /**
     * @param array $carrierIds
     */
    public function setCarriersDeleted($carrierIds) {
        if (empty($carrierIds)) {
            return;
        }

        foreach ($carrierIds as &$carrierId) {
            $carrierId = (int)$carrierId; // to escape values
        }

        $carrierIdsImploded = implode(',', $carrierIds);

        $db = \JFactory::getDBO();
        $db->setQuery("UPDATE #__virtuemart_zasilkovna_carriers SET deleted = 1 WHERE id IN ($carrierIdsImploded)");
        $db->query();
    }
}
