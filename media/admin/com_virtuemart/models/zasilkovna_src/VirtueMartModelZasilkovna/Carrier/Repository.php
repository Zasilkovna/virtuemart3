<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Repository
{
    /**
     * @param array $data
     */
    public function insertUpdateCarrier($data) {
        $db = \JFactory::getDBO();
        $columns = [];
        $values = [];
        $onDuplicates = [];

        foreach($data as $key => $item) {
            $escapedKey = $db->quoteName($db->escape($key));

            if (is_bool($item)) {
                $item = (int)$item;
            }

            $values[$key] = $db->quote($db->escape($item));
            $columns[$key] = $escapedKey;

            if ($key !== 'id') {
                $onDuplicates[$key] = sprintf(' %s = VALUES(%s) ', $escapedKey, $escapedKey);
            }
        }

        $columnsImploded = implode(', ', $columns);
        $valuesImploded = implode(', ', $values);
        $onDuplicatesImploded = implode(', ', $onDuplicates);

        $db = \JFactory::getDBO();
        $db->setQuery( "INSERT INTO #__virtuemart_zasilkovna_carriers ($columnsImploded) VALUES ($valuesImploded) ON DUPLICATE KEY UPDATE $onDuplicatesImploded;");
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
