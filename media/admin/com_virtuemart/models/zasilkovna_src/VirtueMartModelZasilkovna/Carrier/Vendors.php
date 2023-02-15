<?php

namespace VirtueMartModelZasilkovna\Carrier;

class Vendors
{
    public static function getVendors()
    {
        return [
            56 => [
                [
                    'country' => 'cz',
                    'id' => 'czzpoint',
                    'label' => 'CZ Výdejní místa',
                ],
                [
                    'country' => 'cz',
                    'id' => 'czzbox',
                    'label' => 'CZ Z-boxy',
                ],
                [
                    'country' => 'cz',
                    'id' => 'czalzabox',
                    'label' => 'CZ Alzaboxy',
                ],
            ],
            189 => [
                [
                    'country' => 'sk',
                    'id' => 'skzpoint',
                    'label' => 'SK Výdejní místa',
                ],
                [
                    'country' => 'sk',
                    'id' => 'skzbox',
                    'label' => 'SK Z-boxy',
                ],
            ],
            97 => [
                [
                    'country' => 'hu',
                    'id' => 'huzpoint',
                    'label' => 'HU Výdejní místa',
                ],
                [
                    'country' => 'hu',
                    'id' => 'huzbox',
                    'label' => 'HU Z-boxy',
                ],
            ],
            175 => [
                [
                    'country' => 'ro',
                    'id' => 'rozpoint',
                    'label' => 'RO Výdejní místa',
                ],
                [
                    'country' => 'sk',
                    'id' => 'rozbox',
                    'label' => 'RO Z-boxy',
                ],
            ]

        ];
    }

    public static function filterOutVendorsByCountryIds(array $countryIds = [])
    {
        $vendors = self::getVendors();
        $filteredVendors = [];
        foreach ($vendors as $countryId => $countryVendors) {
            if (!in_array($countryId, $countryIds, true)) {
                $filteredVendors[$countryId] = $countryVendors;
            }

        }

        return $filteredVendors;
    }

}
