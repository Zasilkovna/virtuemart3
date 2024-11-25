<?php

namespace VirtueMartModelZasilkovna;

class ShipmentMethodValidator
{
    /** @var Carrier\Repository */
    private $carrierRepository;

    public function __construct()
    {
        $this->carrierRepository = new Carrier\Repository();
    }

    /**
     * @param \stdClass $weightRule
     * @param float $maxWeight
     * @return ShipmentValidationReport
     */
    public function validateWeightRule($weightRule, $maxWeight)
    {
        $weightRulesReport = new ShipmentValidationReport();

        if (empty($weightRule->price) && !is_numeric($weightRule->price)) {
            $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_WEIGHT_PRICE_MISSING);
        } elseif (!is_numeric($weightRule->price)) {
            $weightRulesReport->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
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

    /**
     * @param ShipmentMethod $shipmentMethod
     * @return ShipmentValidationReport
     */
    public function validate(ShipmentMethod $shipmentMethod)
    {
        $report = new ShipmentValidationReport();

        $this->validateGlobalWeightRules($report, $shipmentMethod);

        $this->validateCountryWeightRulesAndCountries($report, $shipmentMethod);

        if ($shipmentMethod->getShippingType() === ShipmentMethod::SHIPPING_TYPE_PICKUPPOINTS) {
            $this->validatePPDeliverySettings($report, $shipmentMethod);
        } else {
            $this->validateHdCarrier($report, $shipmentMethod);
        }

        return $report;
    }

    /**
     * @param ShipmentValidationReport $report
     * @param ShipmentMethod $shipmentMethod
     * @return void
     */
    private function validateGlobalWeightRules(
        ShipmentValidationReport $report,
        ShipmentMethod $shipmentMethod
    ) {
        $globalMaxWeight = $shipmentMethod->getGlobalMaxWeight();

        if (empty($globalMaxWeight) && !is_numeric($globalMaxWeight)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING);
        } elseif (!is_numeric($globalMaxWeight)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
        }

        $weightsFE = ($shipmentMethod->getGlobalWeightRules() ?: []);
        foreach ($weightsFE as $weightRule) {
            $weightRulesReport = $this->validateWeightRule($weightRule, $globalMaxWeight);

            if ($weightRulesReport->isValid() === false) {
                $report->merge($weightRulesReport);
                break;
            }
        }
    }

    /**
     * @param ShipmentValidationReport $report
     * @param ShipmentMethod $shipmentMethod
     * @return void
     */
    private function validateCountryWeightRulesAndCountries(
        ShipmentValidationReport $report,
        ShipmentMethod $shipmentMethod
    ) {
        $globalMaxWeight = $shipmentMethod->getGlobalMaxWeight();
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
            $notAllowedCountries = array_diff($countries, $allowedCountries);
            if (!empty($notAllowedCountries)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_ALLOWED_COUNTRIES_ONLY);
            }
        }

        if (!empty($blockingCountries)) {
            $notBlockingCountries = array_diff($countries, $blockingCountries);
            if (count($notBlockingCountries) !== count($countries)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_NO_BLOCKED_COUNTRY);
            }
        }
    }

    /**
     * @param ShipmentValidationReport $report
     * @param ShipmentMethod $shipmentMethod
     * @return void
     */
    public function validateHdCarrier(ShipmentValidationReport $report, ShipmentMethod $shipmentMethod)
    {
        $shippingType = $shipmentMethod->getShippingType();
        $hdCarrierId = $shipmentMethod->getHdCarrierId();
        $blockingCountries = $shipmentMethod->getBlockingCountries();
        $allowedCountries = $shipmentMethod->getAllowedCountries();

        if ($shippingType === ShipmentMethod::SHIPPING_TYPE_PICKUPPOINTS && $hdCarrierId !== null) {
            $report->addError('HD_CARRIER_REDUNDANT_FOR_PP');
        }

        if ($shippingType === ShipmentMethod::SHIPPING_TYPE_HDCARRIERS) {
            if ($hdCarrierId === null) {
                $report->addError('NO_HD_CARRIER_SELECTED');
            } else {
                $carrierVmCountryId = $this->getValidatedCarrierCountryVmId($report, $hdCarrierId);
                if ($carrierVmCountryId === null) {
                    return;
                }

                // intentional type unsafe comparison, handles both string (PHP < 8.1) and int (PHP >= 8.1) returned from db
                if (!empty($allowedCountries) && !in_array($carrierVmCountryId, $allowedCountries, false)) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES);
                }
                // 2x intentional type unsafe comparison, handles both string (PHP < 8.1) and int (PHP >= 8.1) returned from db
                if (
                    (
                        empty($allowedCountries) ||
                        in_array($carrierVmCountryId, $allowedCountries, false)
                    ) &&
                    in_array($carrierVmCountryId, $blockingCountries, false)
                ) {
                    $report->addError(ShipmentValidationReport::ERROR_CODE_HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES);
                }
            }
        }
    }

    public function validatePPDeliverySettings(ShipmentValidationReport $report, ShipmentMethod $shipmentMethod)
    {
        $ppCarrierId = $shipmentMethod->getPpCarrierId();
        $vendorGroups = $shipmentMethod->getVendorGroups();

        $setCountriesCodes = $shipmentMethod->getSetCountriesCodes(true);

        // if ppCarrierId is set, $vendorGroups must be empty and the other way around
        if (($ppCarrierId !== null && !empty($vendorGroups))
            || ($ppCarrierId === null && empty($vendorGroups))) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_CHOOSE_EITHER_PP_CARRIER_OR_VENDORS);

            return;
        }

        if ($ppCarrierId !== null) {
            $carrierVmCountryId = $this->getValidatedCarrierCountryVmId($report, $ppCarrierId);
            if ($carrierVmCountryId === null) {
                return;
            }

            // intentional type unsafe comparison, handles both string (PHP < 8.1) and int (PHP >= 8.1) returned from db
            if (!in_array($carrierVmCountryId, $setCountriesCodes, false)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_PP_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES);
            }

        } elseif (!$shipmentMethod->needsVendors()) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_CHOSEN_COUNTRIES_ARE_NOT_INTERNAL);
        }
    }

    /**
     * @param ShipmentValidationReport $report
     * @param int $carrierId
     * @return int|null
     */
    private function getValidatedCarrierCountryVmId(ShipmentValidationReport $report, $carrierId)
    {
        $carrier = $this->carrierRepository->getCarrierById($carrierId);
        if ($carrier === null || (int)$carrier->deleted === 1) {
            $report->addError(
                'CARRIER_NOT_EXISTS',
                $carrier->name ? [$carrier->name] : ['ID: ' . $carrierId]
            );

            return null;
        }

        $vmCarrierCountry = \VirtueMartModelCountry::getCountryByCode(strtoupper($carrier->country));
        if (!$vmCarrierCountry->published) {
            $report->addError(
                ((int)$carrier->is_pickup_points === 1)
                    ? ShipmentValidationReport::ERROR_CODE_PP_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES
                    : ShipmentValidationReport::ERROR_CODE_HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES
            );

            return null;
        }

        return (int)$vmCarrierCountry->virtuemart_country_id;
    }

}
