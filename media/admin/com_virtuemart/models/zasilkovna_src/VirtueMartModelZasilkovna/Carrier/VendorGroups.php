<?php

namespace VirtueMartModelZasilkovna\Carrier;

class VendorGroups
{
    // constant values are used in zasilkovna.xml
    const ZPOINT = 'zpoint';
    const ZBOX = 'zbox';

    static private $groups = [self::ZPOINT, self::ZBOX,];
    static private $countriesWithGroups = ['cz', 'sk', 'hu', 'ro',];

    /**
     * @param string $group
     * @param string $countryCode
     * @return bool
     */
    public static function isGroupPresentInCountry($group, $countryCode)
    {
        if (!in_array($countryCode, self::$countriesWithGroups, true)) {
            return false;
        }

        return in_array($group, self::$groups, true);
    }
}
