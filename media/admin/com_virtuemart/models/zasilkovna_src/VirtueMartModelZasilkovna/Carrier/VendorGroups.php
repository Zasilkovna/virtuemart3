<?php

namespace VirtueMartModelZasilkovna\Carrier;

class VendorGroups
{
    // constant values are used in zasilkovna.xml
    const ALZABOX = 'alzabox';
    const ZPOINT = 'zpoint';
    const ZBOX = 'zbox';

    static private $countryCodeMapping = [
        'cz' => [self::ALZABOX, self::ZPOINT, self::ZBOX,],
        'sk' => [self::ZPOINT, self::ZBOX,],
        'ro' => [self::ZPOINT, self::ZBOX,],
        'hu' => [self::ZPOINT, self::ZBOX,],
    ];

    /**
     * @param string $group
     * @param string $countryCode
     * @return bool
     */
    public static function isGroupPresentInCountry($group, $countryCode)
    {
        if (!isset(self::$countryCodeMapping[$countryCode])) {
            return false;
        }

        return in_array($group, self::$countryCodeMapping[$countryCode], true);
    }
}
