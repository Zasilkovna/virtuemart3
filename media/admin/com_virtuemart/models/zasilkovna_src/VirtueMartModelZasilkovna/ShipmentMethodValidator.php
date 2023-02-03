<?php

namespace VirtueMartModelZasilkovna;

use VirtueMartModelCountry;

class ShipmentMethodValidator
{
    /**
     * @return ShipmentValidationReport
     */
    public function validate(ShipmentMethod $shipmentMethod)
    {
        $report = new \VirtueMartModelZasilkovna\ShipmentValidationReport();

        $globalDefaultPrice = $shipmentMethod->getGlobalDefaultPrice();
        if (empty($globalDefaultPrice) && !is_numeric($globalDefaultPrice)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_GLOBAL_DEFAULT_PRICE_MISSING);
        } else {
            if (!is_numeric($globalDefaultPrice)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        $globalMaxWeight = $shipmentMethod->getGlobalMaxWeight();
        if (empty($globalMaxWeight) && !is_numeric($globalMaxWeight)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING);
        } else {
            if (!is_numeric($globalMaxWeight)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        $weightsFE = ($shipmentMethod->getGlobalWeightRules() ?: []);
        foreach ($weightsFE as $weightRule) {
            $weightRulesReport = $this->validateWeightRule($weightRule, $globalMaxWeight);

            if ($weightRulesReport->isValid() === false) {
                $report->merge($weightRulesReport);
                break;
            }
        }

        $rules = ($shipmentMethod->getPricingRules() ?: []);
        $countries = [];

        foreach ($rules as $countryRule) {
            if (array_key_exists($countryRule->country, $countries)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_DUPLICATE_COUNTRIES); // multiple country definitions not allowed
                break;
            }

            $countries[$countryRule->country] = $countryRule->country;

            $countryWeightRules = ($shipmentMethod->getCountryWeightRules($countryRule->country) ?: []);
            foreach ($countryWeightRules as $weightRule) {
                $weightRulesReport = $this->validateWeightRule($weightRule, $globalMaxWeight);

                if ($weightRulesReport->isValid() === false) {
                    $report->merge($weightRulesReport);
                    break;
                }
            }
        }

        $blockingCountries = $shipmentMethod->getBlockingCountries();
        $allowedCountries = $shipmentMethod->getAllowedCountries();

        $blockingCountries = array_diff($blockingCountries,
            $allowedCountries); // when user allowes and blocks same countries
        $allowedCountries = array_diff($allowedCountries,
            $blockingCountries); // when user allowes and blocks same countries

        if (!empty($allowedCountries)) {
            $diff = array_diff($countries, $allowedCountries);
            if (!empty($diff)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_ALLOWED_COUNTRIES_ONLY);
            }
        }

        if (!empty($blockingCountries)) {
            $diff = array_diff($countries, $blockingCountries);
            if (count($diff) !== count($countries)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_NO_BLOCKED_COUNTRY);
            }
        }

        $shippingType = $shipmentMethod->getShippingType();
        $hdCarrierId = $shipmentMethod->getHdCarrierId();
        if ($shippingType === 'pickuppoints') {
            $shipmentMethod->resetHdCarrier();
        }
        if ($shippingType === 'hdcarriers') {
            if (empty($hdCarrierId)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_NO_HD_CARRIER_SELECTED);
            } else {
                $carrier = $shipmentMethod->getCarrierRepository()->getCarrier($hdCarrierId);
                if ($carrier === null || $carrier->deleted === 1) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_NOT_EXISTS);

                    return $report;
                }
                $vmCarrierCountry = VirtueMartModelCountry::getCountryByCode(strtoupper($carrier->country));

                if (!$vmCarrierCountry->published) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_NO_PUBLISHED_COUNTRY);
                }
                $carrierVmCountryId = $vmCarrierCountry->virtuemart_country_id;
                if (!empty($allowedCountries) && !in_array($carrierVmCountryId, $allowedCountries, true)) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES);
                }
                if ((empty($allowedCountries) || in_array($carrierVmCountryId, $allowedCountries,
                            true)) && in_array($carrierVmCountryId, $blockingCountries, true)) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_IS_IN_BLOCKING_COUNTRIES);
                }
            }

        }

        return $report;
    }

    /**
     * @param $weightRule
     * @param $maxWeight
     * @return \VirtueMartModelZasilkovna\ShipmentValidationReport
     */
    public function validateWeightRule($weightRule, $maxWeight)
    {
        $weightRulesReport = new ShipmentValidationReport();

        if (empty($weightRule->price) && !is_numeric($weightRule->price)) {
            $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_WEIGHT_PRICE_MISSING);
        } else {
            if (!is_numeric($weightRule->price)) {
                $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        if (empty($weightRule->maxWeightKg) && !is_numeric($weightRule->maxWeightKg)) {
            $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_WEIGHT_MISSING);
        } else {
            if (!is_numeric($weightRule->maxWeightKg)) {
                $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            } else {
                if ($weightRule->maxWeightKg > $maxWeight) {
                    $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_WEIGHT_EXCEEDED);
                }
            }
        }

        return $weightRulesReport;
    }

}
