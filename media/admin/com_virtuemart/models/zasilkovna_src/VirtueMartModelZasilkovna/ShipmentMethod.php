<?php

namespace VirtueMartModelZasilkovna;

class ShipmentMethod
{
    /** @var \stdClass */
    private $method;


    const SHIPPING_TYPE_PICKUPPOINTS = 'pickuppoints'; // this value is also used in the zasilkovna.xml
    const SHIPPING_TYPE_HDCARRIERS = 'hdcarriers'; // this value is also used in the zasilkovna.xml

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

                    return ($globalWeightRule->maxWeightKg > $globalWeightRuleAfter->maxWeightKg ? 1 : -1);
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

                    return ($weightRule->maxWeightKg > $weightRuleAfter->maxWeightKg ? 1 : -1);
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
     * @return array
     */
    public function getAllowedCountries()
    {
        return $this->method->countries ?: [];
    }

    /**
     * @return array
     */
    public function getBlockingCountries()
    {
        return $this->method->blocking_countries ?: [];
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
     * @param iterable $weightRules
     * @param float $weight
     * @return \stdClass|null
     */
    public function resolveWeightRule($weightRules, $weight) {
        $minWeight = null;
        $finalWeightRule = null;
        $weightRules = ($weightRules ?: []);

        foreach ($weightRules as $key => $weightRule) {
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

    /** Global weight rules are meant for unspecified countries
     *
     * @return iterable|null
     */
    public function getGlobalWeightRules()
    {
        return $this->getParams()->globalWeightRules;
    }

    /**
     * @param int $countryId
     * @return bool
     */
    public function hasPricingRuleForCountry($countryId) {
        $rules = $this->getPricingRulesForCountry($countryId);
        return !empty($rules);
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
        $rules = ($this->getPricingRules() ?: []);
        $countryRules = [];

        foreach ($rules as $countryRule) {
            if ($countryRule->country == $countryId) {
                $countryRules[] = $countryRule;
            }
        }

        return $countryRules;
    }

    /**
     * @return mixed
     */
    public function getPricingRules()
    {
        return $this->getParams()->pricingRules;
    }

    /**
     * @return \stdClass
     */
    public function getParams()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getShippingType()
    {
        return (string) $this->getParams()->shipping_type;
    }

    /**
     * @return int|null
     */
    public function getHdCarrierId()
    {
        return $this->getParams()->hd_carrier ? (int) $this->getParams()->hd_carrier : null;
    }

    /**
     * @return void
     */
    public function resetHdCarrier()
    {
        $this->method->hd_carrier = null;
    }

    public function isHdCarrier()
    {
     return $this->getHdCarrierId() !== null;
    }

    public function getPpVendors()
    {
        return $this->getParams()->pp_vendors ?: [];
    }

}
