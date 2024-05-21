<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Repository
{
    /**
     * @param array $carrierData
     */
    public function insertUpdateCarrier(array $carrierData) {
        $db = \JFactory::getDBO();
        $columns = [];
        $values = [];
        $onDuplicates = [];

        foreach($carrierData as $key => $item) {
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
        $db->execute();
    }

    /**
     * Gets total count of usable carriers.
     * @return int
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
     * @param bool|null $isPickupPoints
     * @param string|null $countryCode
     * @return array
     */
    public function getAllActiveCarrierIds($isPickupPoints = null, $countryCode = null)
    {
        $db = \JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('id')
            ->from('#__virtuemart_zasilkovna_carriers')
            ->where('`deleted` = 0');

        if ($isPickupPoints !== null) {
            $query->andWhere(sprintf('`is_pickup_points` = %d', $isPickupPoints));
        }

        if ($countryCode !== null) {
            $query->andWhere(sprintf('`country` = %s', $db->quote($countryCode)));
        }

        $db->setQuery($query);

        return $db->loadAssocList('id', 'id');
    }

    /**
     * Gets all active HD carriers for published countries.
     *
     * @return array
     */
    public function getActiveHdCarriersForPublishedCountries()
    {
        $db = \JFactory::getDBO();
        $db->setQuery("
            SELECT vzc.id,
                   vzc.name,
                   vzc.country,
                   vc.virtuemart_country_id AS vm_country
            FROM #__virtuemart_zasilkovna_carriers vzc
            LEFT JOIN #__virtuemart_countries vc 
                ON UCASE(vzc.country) = vc.country_2_code
            WHERE vzc.deleted = 0 
                AND vzc.is_pickup_points = 0
                AND vc.published = 1
            ORDER BY vzc.country
            ");

        return $db->loadAssocList();
    }

    /**
     * @param int $carrierId
     * @return null|\stdClass
     */
    public function getCarrierById($carrierId)
    {
        $db = \JFactory::getDBO();
        $db->setQuery(
            sprintf("
                SELECT vzc.id, 
                   vzc.name, 
                   vzc.country, 
                   vzc.deleted,
                   vzc.requires_size,
                   vc.virtuemart_country_id AS vm_country
                FROM #__virtuemart_zasilkovna_carriers vzc
                LEFT JOIN #__virtuemart_countries vc 
                    ON UCASE(vzc.country) = vc.country_2_code
                WHERE id = %d
                ",
                $carrierId
            )
        );

        return $db->loadObject(\stdClass::class);
    }

    /**
     * @param array $carrierIds
     * @return void
     */
    public function setCarriersDeleted(array $carrierIds) {
        if (empty($carrierIds)) {
            return;
        }

        foreach ($carrierIds as &$carrierId) {
            $carrierId = (int)$carrierId; // to escape values
        }

        $carrierIdsImploded = implode(',', $carrierIds);

        $db = \JFactory::getDBO();
        $db->setQuery("UPDATE #__virtuemart_zasilkovna_carriers SET deleted = 1 WHERE id IN ($carrierIdsImploded)");
        $db->execute();
    }
}
