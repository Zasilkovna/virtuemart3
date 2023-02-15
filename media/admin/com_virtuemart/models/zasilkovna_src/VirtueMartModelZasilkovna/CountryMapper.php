<?php

namespace VirtueMartModelZasilkovna;

class CountryMapper
{
    const countryCodes = ['CZ', 'SK', 'HU', 'RO'];

    static $mapping = null;

    public static function toVmId($countryCode)
    {
        if (self::$mapping === null) {
            self::$mapping = self::getMapppingFromVm();
        }

        return array_key_exists(strtolower($countryCode),self::$mapping)
            ? self::$mapping[strtolower($countryCode)]
            : null;
    }

    public static function toCC($vmId)
    {
        if (self::$mapping === null) {
            self::$mapping = self::getMapppingFromVm();
        }

        return array_search($vmId, self::$mapping, true);
    }

    protected static function getMapppingFromVm()
    {
        $db = \JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('virtuemart_country_id, LOWER(country_2_code) as country_2_code');
        $query->from('#__virtuemart_countries');
        $query->where('country_2_code IN (' . implode(',', $db->quote(self::countryCodes)) . ')');
        $db->setQuery($query);
        $result = $db->loadAssocList('country_2_code', 'virtuemart_country_id');
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        return $result;
    }
}
