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
            sprintf(
                "SELECT id, name, country, deleted FROM #__virtuemart_zasilkovna_carriers WHERE id = %d",
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
        $db->query();
    }

    /**
     * Returns internal pickup points configuration
     *
     * @return array[]
     */
    public function getZpointCarriers()
    {
        return [
            'cz' => [
                'id' => 'zpointcz',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ZPOINTCZ',
                'is_pickup_points' => 1,
                'currency' => 'CZK',
                'supports_age_verification' => true,
                'vendors' => [
                    'czzpoint',
                    'czzbox',
                    'czalzabox',
                ],
            ],
            'sk' => [
                'id' => 'zpointsk',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ZPOINTSK',
                'is_pickup_points' => 1,
                'currency' => 'EUR',
                'supports_age_verification' => true,
                'vendors' => [
                    'skzpoint',
                    'skzbox',
                ],
            ],
            'hu' => [
                'id' => 'zpointhu',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ZPOINTHU',
                'is_pickup_points' => 1,
                'currency' => 'HUF',
                'supports_age_verification' => true,
                'vendors' => [
                    'huzpoint',
                    'huzbox',
                ],
            ],
            'ro' => [
                'id' => 'zpointro',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ZPOINTRO',
                'is_pickup_points' => 1,
                'currency' => 'RON',
                'supports_age_verification' => true,
                'vendors' => [
                    'rozpoint',
                    'rozbox',
                ],
            ],
        ];
    }

    /**
     * Gets vendor carriers settings.
     *
     * @return array[]
     */
    public function getVendorCarriers()
    {
        return [
            'czzpoint' => [
                'country' => 'cz',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_CZZPOINT',
                'supports_cod' => true,
            ],
            'czzbox' => [
                'country' => 'cz',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_CZZBOX',
                'supports_cod' => true,
            ],
            'czalzabox' => [
                'country' => 'cz',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_CZALZABOX',
                'supports_cod' => true,
            ],
            'skzpoint' => [
                'country' => 'sk',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_SKZPOINT',
                'supports_cod' => true,
            ],
            'skzbox' => [
                'country' => 'sk',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_SKZBOX',
                'supports_cod' => true,
            ],
            'huzpoint' => [
                'country' => 'hu',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_HUZPOINT',
                'supports_cod' => true,
            ],
            'huzbox' => [
                'country' => 'hu',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_HUZBOX',
                'supports_cod' => true,
            ],
            'rozpoint' => [
                'country' => 'ro',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ROZPOINT',
                'supports_cod' => true,
            ],
            'rozbox' => [
                'country' => 'ro',
                'name' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_NAME_ROZBOX',
                'supports_cod' => true,
            ],
        ];
    }

    /**
     * Gets all non-feed carriers settings.
     *
     * @return array
     */
    public function getNonFeedCarriers()
    {
        $nonFeedCarriers = [];

        $zPointCarriers = $this->getZpointCarriers();
        foreach ($zPointCarriers as $country => $zpointCarrier) {
            $nonFeedCarriers[$zpointCarrier['id']] = ($zpointCarrier + ['country' => $country]);
        }

        foreach ($this->getVendorCarriers() as $carrierId => $vendorCarrier) {
            $nonFeedCarriers[$carrierId] = [
                'id' => $carrierId,
                'name' => $vendorCarrier['name'],

                // Vendor loads some settings from country.
                'is_pickup_points' => $zPointCarriers[$vendorCarrier['country']]['is_pickup_points'],
                'currency' => $zPointCarriers[$vendorCarrier['country']]['currency'],
                'supports_age_verification' => $zPointCarriers[$vendorCarrier['country']]['supports_age_verification'],

                'vendors' => [$carrierId],
                'country' => $vendorCarrier['country'],
                'supports_cod' => $vendorCarrier['supports_cod'],
            ];
        }

        return $nonFeedCarriers;
    }

    /**
     * Gets non-feed carriers settings by country.
     *
     * @param string $country Country.
     *
     * @return array
     */
    public function getNonFeedCarriersByCountry($country)
    {
        $filteredCarriers = [];
        $nonFeedCarriers = $this->getNonFeedCarriers();

        foreach ($nonFeedCarriers as $nonFeedCarrier) {
            if ($nonFeedCarrier['country'] === $country) {
                $filteredCarriers[] = $nonFeedCarrier;
            }
        }

        return $filteredCarriers;
    }

    public function getVendorCarriersByCountry($country)
    {
        $filteredCarriers = [];
        $vendorCarriers = $this->getVendorCarriers();

        foreach ($vendorCarriers as $vendorCode => $vendorCarrier) {
            if ($vendorCarrier['country'] === $country) {
                $filteredCarriers[] = $vendorCarrier;
            }
        }
        echo '<pre>';
        print_r($filteredCarriers);
        echo '</pre>';
        echo '<pre>';
        print_r($country);
        echo '</pre>';
        return $filteredCarriers;
    }
}
