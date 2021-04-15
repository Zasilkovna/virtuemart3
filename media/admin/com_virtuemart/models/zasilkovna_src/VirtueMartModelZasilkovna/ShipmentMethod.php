<?php

namespace VirtueMartModelZasilkovna;

class ShipmentMethod
{
    /** @var \stdClass */
    private $method;

    public function __construct(\stdClass $method)
    {
        $this->method = $method;
    }

    /**
     * @param $method
     * @return \VirtueMartModelZasilkovna\ShipmentMethod
     */
    public static function fromRandom($method)
    {
        if ($method instanceof self) {
            return $method;
        }

        if ($method instanceof \TableShipmentmethods) {
            $method = $method->getProperties(true);
        }

        if (is_array($method)) {
            $method = json_decode(json_encode($method));
        }

        return new self($method);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return json_decode(json_encode($this->method), true);
    }

    /**
     * @return \VirtueMartModelZasilkovna\ShipmentMethod
     */
    public function getResortedClone()
    {
        $method = clone $this->getParams();

        if (!empty($method->globalWeightRules)) {
            $globalWeightRules = (array)$method->globalWeightRules;
            usort(
                $globalWeightRules,
                function ($globalWeightRule, $globalWeightRuleAfter) {
                    if ($globalWeightRule->maxWeightKg == $globalWeightRuleAfter->maxWeightKg) {
                        return 0;
                    }

                    return $globalWeightRule->maxWeightKg > $globalWeightRuleAfter->maxWeightKg ? 1 : -1;
                }
            );

            foreach ($globalWeightRules as $key => $globalWeightRule) {
                $globalWeightRules['globalWeightRules' . $key] = $globalWeightRule;
                unset($globalWeightRules[$key]);
            }

            $method->globalWeightRules = (object)$globalWeightRules;
        }

        foreach ($method->pricingRules as &$pricingRule) {
            if (empty($pricingRule->weightRules)) {
                continue;
            }

            $weightRules = (array)$pricingRule->weightRules;
            usort(
                $weightRules,
                function ($weightRule, $weightRuleAfter) {
                    if ($weightRule->maxWeightKg == $weightRuleAfter->maxWeightKg) {
                        return 0;
                    }

                    return $weightRule->maxWeightKg > $weightRuleAfter->maxWeightKg ? 1 : -1;
                }
            );

            foreach ($weightRules as $key => $weightRule) {
                $weightRules['weightRules' . $key] = $weightRule;
                unset($weightRules[$key]);
            }

            $pricingRule->weightRules = (object)$weightRules;
        }

        return new self($method);
    }

    /**
     * @param $weightRule
     * @param $maxWeight
     * @return \VirtueMartModelZasilkovna\ShipmentValidationReport
     */
    private function validateWeightRule($weightRule, $maxWeight)
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

    /**
     * @return ShipmentValidationReport
     */
    public function validate()
    {
        $report = new ShipmentValidationReport();

        $globalDefaultPrice = $this->getGlobalDefaultPrice();
        if (empty($globalDefaultPrice) && !is_numeric($globalDefaultPrice)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_GLOBAL_DEFAULT_PRICE_MISSING);
        } else {
            if (!is_numeric($globalDefaultPrice)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        $globalMaxWeight = $this->getGlobalMaxWeight();
        if (empty($globalMaxWeight) && !is_numeric($globalMaxWeight)) {
            $report->addError(ShipmentValidationReport::ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING);
        } else {
            if (!is_numeric($globalMaxWeight)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        foreach ($this->getGlobalWeightRules() ?: [] as $weightRule) {
            $weightRulesReport = $this->validateWeightRule($weightRule, $globalMaxWeight);

            if ($weightRulesReport->isValid() === false) {
                $report->merge($weightRulesReport);
                break;
            }
        }

        $rules = $this->getPricingRules();
        $countries = [];

        foreach ($rules ?: [] as $countryRule) {
            if (array_key_exists($countryRule->country, $countries)) {
                $report->addError(ShipmentValidationReport::ERROR_CODE_DUPLICATE_COUNTRIES); // multiple country definitions not allowed
                break;
            }

            $countries[$countryRule->country] = $countryRule->country;

            foreach ($this->getCountryWeightRules($countryRule->country) ?: [] as $weightRule) {
                $weightRulesReport = $this->validateWeightRule($weightRule, $globalMaxWeight);

                if ($weightRulesReport->isValid() === false) {
                    $report->merge($weightRulesReport);
                    break;
                }
            }
        }

        $blockingCountries = $this->getBlockingCountries() ?: [];
        $allowedCountries = $this->getAllowedCountries() ?: [];

        $blockingCountries = array_diff($blockingCountries, $allowedCountries); // when user allowes and blocks same countries
        $allowedCountries = array_diff($allowedCountries, $blockingCountries); // when user allowes and blocks same countries

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

        return $report;
    }

    /**
     * @return mixed
     */
    public function getAllowedCountries()
    {
        return $this->method->countries;
    }

    /**
     * @return mixed
     */
    public function getBlockingCountries()
    {
        return $this->method->blocking_countries;
    }

    /**
     * @param $countryId
     * @return null|float
     */
    public function getCountryFreeShipping($countryId)
    {
        $countryRule = $this->getPricingRuleForCountry($countryId);

        $freeShipping = null;
        if ($countryRule && is_numeric($countryRule->free_shipment)) {
            $freeShipping = (float)$countryRule->free_shipment;
        }

        return $freeShipping;
    }

    /**
     * @param $countryId
     * @return float|null
     */
    public function getCountryDefaultPrice($countryId)
    {
        $countryRule = $this->getPricingRuleForCountry($countryId);

        $shipmentCost = null;
        if ($countryRule && is_numeric($countryRule->shipment_cost)) {
            $shipmentCost = (float)$countryRule->shipment_cost;
        }

        return $shipmentCost;
    }

    /**
     * @param int $countryId
     * @return null|array (object)[['maxWeightKg' => '5.2', 'price' => '3.4']]
     */
    public function getCountryWeightRules($countryId)
    {
        $countryRule = $this->getPricingRuleForCountry($countryId);
        $weightRules = null;

        if ($countryRule) {
            $weightRules = $countryRule->weightRules;
        }

        return $weightRules;
    }

    /**
     * @param int|null $countryId
     * @param float $weight
     * @return \sdtClass|null
     */
    public function getCountryWeightRule($countryId, $weight)
    {
        if ($countryId === null) {
            $weightRules = $this->getGlobalWeightRules();
        } else {
            $weightRules = $this->getCountryWeightRules($countryId);
        }

        $minWeight = null;

        $finalWeightRule = null;
        foreach ($weightRules ?: [] as $key => $weightRule) {
            if (is_numeric($weightRule->maxWeightKg) && is_numeric($weightRule->price) && $weight <= $weightRule->maxWeightKg) {
                if ($minWeight === null || $minWeight > $weightRule->maxWeightKg) {
                    $minWeight = $weightRule->maxWeightKg;
                    $finalWeightRule = $weightRule;
                }
            }
        }

        return $finalWeightRule;
    }

    /**
     * @return mixed
     */
    public function getGlobalFreeShipping()
    {
        return $this->getParams()->free_shipment;
    }

    /**
     * @return mixed
     */
    public function getGlobalDefaultPrice()
    {
        return $this->getParams()->shipment_cost;
    }

    /**
     * @return mixed
     */
    public function getGlobalMaxWeight()
    {
        return $this->getParams()->maxWeight;
    }

    /** Global weight rules are ment for unspecified countries
     *
     * @return iterable|null
     */
    public function getGlobalWeightRules()
    {
        return $this->getParams()->globalWeightRules;
    }

    /**
     * @param $countryId
     * @return mixed
     */
    private function getPricingRuleForCountry($countryId)
    {
        $rules = $this->getPricingRulesForCountry($countryId);
        return array_pop($rules);
    }

    /**
     * @param $countryId
     * @return array
     */
    private function getPricingRulesForCountry($countryId)
    {
        $rules = $this->getPricingRules();
        $countryRules = [];

        foreach ($rules ?: [] as $countryRule) {
            if ($countryRule->country == $countryId) {
                $countryRules[] = $countryRule;
            }
        }

        return $countryRules;
    }

    /**
     * @return mixed
     */
    private function getPricingRules()
    {
        return $this->getParams()->pricingRules;
    }

    /**
     * @return \stdClass
     */
    private function getParams()
    {
        return $this->method;
    }
}

