<?php

class VirtueMartModelZasilkovna_shipment_method
{
    /** @var \stdClass */
    private $method;

    public function __construct(\stdClass $method)
    {
        $this->method = $method;
    }

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

    private function validateWeightRule($weightRule)
    {
        $weightRulesReport = new PacketeryValidationReport();

        if (empty($weightRule->price) && !is_numeric($weightRule->price)) {
            $weightRulesReport->addError(PacketeryValidationReport::ERROR_CODE_WEIGHT_PRICE_MISSING);
        } else {
            if (!is_numeric($weightRule->price)) {
                $weightRulesReport->addError(PacketeryValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        if (empty($weightRule->maxWeightKg) && !is_numeric($weightRule->maxWeightKg)) {
            $weightRulesReport->addError(PacketeryValidationReport::ERROR_CODE_WEIGHT_MISSING);
        } else {
            if (!is_numeric($weightRule->maxWeightKg)) {
                $weightRulesReport->addError(PacketeryValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        return $weightRulesReport;
    }

    /**
     * @return PacketeryValidationReport
     */
    public function validate()
    {
        $report = new PacketeryValidationReport();

        $globalDefaultPrice = $this->getGlobalDefaultPrice();
        if (empty($globalDefaultPrice) && !is_numeric($globalDefaultPrice)) {
            $report->addError(PacketeryValidationReport::ERROR_CODE_GLOBAL_DEFAULT_PRICE_MISSING);
        } else {
            if (!is_numeric($globalDefaultPrice)) {
                $report->addError(PacketeryValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        $globalMaxWeight = $this->getGlobalMaxWeight();
        if (empty($globalMaxWeight) && !is_numeric($globalMaxWeight)) {
            $report->addError(PacketeryValidationReport::ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING);
        } else {
            if (!is_numeric($globalMaxWeight)) {
                $report->addError(PacketeryValidationReport::ERROR_CODE_INVALID_TYPE);
            }
        }

        foreach ($this->getGlobalWeightRules() ?: [] as $weightRule) {
            $weightRulesReport = $this->validateWeightRule($weightRule);

            if ($weightRulesReport->isValid() === false) {
                $report->merge($weightRulesReport);
                break;
            }
        }

        $rules = $this->getPricingRules();
        $countries = [];

        foreach ($rules ?: [] as $countryRule) {
            if (array_key_exists($countryRule->country, $countries)) {
                $report->addError(PacketeryValidationReport::ERROR_CODE_DUPLICATE_COUNTRIES); // multiple country definitions not allowed
                break;
            }

            $countries[$countryRule->country] = $countryRule->country;

            foreach ($this->getCountryWeightRules($countryRule->country) ?: [] as $weightRule) {
                $weightRulesReport = $this->validateWeightRule($weightRule);

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
                $report->addError(PacketeryValidationReport::ERROR_CODE_ALLOWED_COUNTRIES_ONLY);
            }
        }

        if (!empty($blockingCountries)) {
            $diff = array_diff($countries, $blockingCountries);
            if (count($diff) !== count($countries)) {
                $report->addError(PacketeryValidationReport::ERROR_CODE_NO_BLOCKED_COUNTRY);
            }
        }

        return $report;
    }

    public function getAllowedCountries()
    {
        return $this->method->countries;
    }

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

    public function getGlobalFreeShipping()
    {
        return $this->getParams()->free_shipment;
    }

    public function getGlobalDefaultPrice()
    {
        return $this->getParams()->shipment_cost;
    }

    public function getGlobalMaxWeight()
    {
        return $this->getParams()->maxWeight;
    }

    /** Global weight rules are ment for unspecified countries
     * @return iterable|null
     */
    public function getGlobalWeightRules()
    {
        return $this->getParams()->globalWeightRules;
    }

    private function getPricingRuleForCountry($countryId)
    {
        $rules = $this->getPricingRulesForCountry($countryId);
        return array_pop($rules);
    }

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

    private function getPricingRules()
    {
        return $this->getParams()->pricingRules;
    }

    private function getParams()
    {
        return $this->method;
    }
}

class PacketeryValidationReport
{
    const ERROR_CODE_INVALID_TYPE = 'INVALID_TYPE'; // when number contains characters
    const ERROR_CODE_WEIGHT_PRICE_MISSING = 'WEIGHT_PRICE_MISSING';
    const ERROR_CODE_WEIGHT_MISSING = 'WEIGHT_MISSING';
    const ERROR_CODE_GLOBAL_DEFAULT_PRICE_MISSING = 'GLOBAL_DEFAULT_PRICE_MISSING';
    const ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING = 'GLOBAL_MAX_WEIGHT_MISSING';
    const ERROR_CODE_DUPLICATE_COUNTRIES = 'DUPLICATE_COUNTRIES';
    const ERROR_CODE_ALLOWED_COUNTRIES_ONLY = 'ALLOWED_COUNTRIES_ONLY';
    const ERROR_CODE_NO_BLOCKED_COUNTRY = 'NO_BLOCKED_COUNTRY';

    /** @var array */
    private $errors = [];

    function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    public function addError($code)
    {
        $this->errors[] = (object)[
            'code' => $code,
            'translationCode' => $this->getTranslationCode($code),
        ];
    }

    /**
     * @return string[]
     */
    public function getErrorCodes()
    {
        return array_map(
            function ($error) {
                return $error->code;
            },
            $this->errors
        );
    }

    private function getTranslationCode($code)
    {
        return 'PLG_VMSHIPMENT_ZASILKOVNA_SHIPPING_ERROR_' . $code;
    }

    public function merge(PacketeryValidationReport $report)
    {
        $this->errors = array_merge($this->errors, $report->getErrors());
    }
}

;