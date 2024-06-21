<?php

namespace VirtueMartModelZasilkovna\Carrier;

class VendorGroups
{
    // constant values are used in zasilkovna.xml
    const ZPOINT = 'zpoint';
    const ZBOX = 'zbox';
    const COUNTRIES_WITH_GROUPS = ['cz', 'sk', 'hu', 'ro',];

    static private $groups = [self::ZPOINT, self::ZBOX,];

    /**
     * @param string $group
     * @param string $countryCode
     * @return bool
     */
    public static function isGroupPresentInCountry($group, $countryCode)
    {
        if (!in_array($countryCode, self::COUNTRIES_WITH_GROUPS, true)) {
            return false;
        }

        return in_array($group, self::$groups, true);
    }
}
