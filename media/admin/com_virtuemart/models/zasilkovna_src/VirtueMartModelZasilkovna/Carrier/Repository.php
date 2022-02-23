<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Repository
{
    const FORM_FIELD_PACKETA_PICKUP_POINTS = 'packetaPickupPoints';

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
        $db->query();
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
     * @return array
     */
    public function getAllActiveCarrierIds() {
        $db = \JFactory::getDBO();
        $db->setQuery("SELECT id FROM #__virtuemart_zasilkovna_carriers WHERE deleted = 0");
        return $db->loadAssocList('id', 'id');
    }

    /**
     * Gets all active carriers.
     *
     * @param array $allowedCountryCodes
     * @param array $blockedCountryCodes
     * @return array
     */
    public function getAllActiveCarriers($allowedCountryCodes = [], $blockedCountryCodes = []) {
        $db = \JFactory::getDBO();

        $andWhere = ["{$db->quoteName('deleted')} = 0"];

        if ($allowedCountryCodes) {
            $countriesWhere = [];
            foreach ($allowedCountryCodes as $country) {
                $country = strtolower($country);
                $countriesWhere[] = "{$db->quoteName('country')} = {$db->quote($country)}";
            }

            $andWhere[] = '(' . implode(' OR ', $countriesWhere) . ')';
        }

        if ($blockedCountryCodes) {
            foreach ($blockedCountryCodes as $blockedCountryCode) {
                $blockedCountryCode = strtolower($blockedCountryCode);
                $andWhere[] = "{$db->quoteName('country')} <> {$db->quote($blockedCountryCode)}";
            }
        }

        $db->setQuery("SELECT * FROM #__virtuemart_zasilkovna_carriers WHERE " . implode(' AND ', $andWhere) . " ORDER BY {$db->quoteName('name')} ASC");
        return $db->loadAssocList();
    }

    /**
     * @param string|null $carrierId
     * @return bool|null
     */
    public function isPickupPointCarrier($carrierId) {
        if ($carrierId === null) {
            return false;
        }

        if ($carrierId === self::FORM_FIELD_PACKETA_PICKUP_POINTS) {
            return true;
        }

        $db = \JFactory::getDBO();
        $db->setQuery("SELECT {$db->quoteName('is_pickup_points')} FROM #__virtuemart_zasilkovna_carriers WHERE {$db->quoteName('id')} = {$db->quote($carrierId)}");
        $record = $db->loadResult();

        if (is_numeric($record)) {
            return $record === '1';
        }

        return null;
    }

    /**
     * @param string|null $carrierId
     * @return bool|null
     */
    public function isHomeDeliveryCarrier($carrierId) {
        if ($carrierId === null) {
            return false;
        }

        if ($carrierId === self::FORM_FIELD_PACKETA_PICKUP_POINTS) {
            return false;
        }

        $db = \JFactory::getDBO();
        $db->setQuery("SELECT {$db->quoteName('is_pickup_points')} FROM #__virtuemart_zasilkovna_carriers WHERE {$db->quoteName('id')} = {$db->quote($carrierId)}");
        $record = $db->loadResult();

        if (is_numeric($record)) {
            return $record === '0';
        }

        return null;
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
        $db->query();
    }
}
