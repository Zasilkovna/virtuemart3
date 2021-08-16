<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Repository
{
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
     * @param $carrierId
     * @param array $data
     */
    public function updateCarrier($carrierId, array $data) {
        $db = \JFactory::getDBO();
        $set = [];
        foreach ($data as $column => $columnData) {
            $columnData = $db->escape($columnData);
            $set[] = " $column = '$columnData' ";
        }
        $setImploded = implode(', ', $set);
        $carrierId = (int)$carrierId;

        $db->setQuery("UPDATE #__virtuemart_zasilkovna_carriers SET $setImploded WHERE id = $carrierId");
        $db->query();
    }

    /**
     * @param $data
     */
    public function insertCarrier($data) {
        $db = \JFactory::getDBO();
        $columns = array_keys($data);
        $columnsImploded = implode(', ', $columns);

        foreach($data as &$item) {
            $item = '"' . $db->escape($item) . '"';
        }

        $imploded = implode(', ', $data);

        $db = \JFactory::getDBO();
        $db->setQuery("INSERT INTO #__virtuemart_zasilkovna_carriers ($columnsImploded) VALUES ($imploded)");
        $db->query();
    }

    /**
     * @param $carrierIds
     */
    public function setOtherCarriersDeleted($carrierIds) {
        foreach ($carrierIds as &$carrierId) {
            $carrierId = (int)$carrierId; // to escape values
        }

        $carrierIdsImploded = implode(',', $carrierIds);

        $db = \JFactory::getDBO();
        $db->setQuery("UPDATE #__virtuemart_zasilkovna_carriers SET deleted = 1 WHERE id NOT IN ($carrierIdsImploded)");
        $db->query();
    }
}
