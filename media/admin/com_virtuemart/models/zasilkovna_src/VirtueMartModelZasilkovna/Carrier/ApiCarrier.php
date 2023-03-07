<?php

namespace VirtueMartModelZasilkovna\Carrier;

/**
 * Class ApiCarrier is value object class representing carrier settings from API.
 */
class ApiCarrier
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $pickupPoints;

    /**
     * @var bool
     */
    private $apiAllowed;

    /**
     * @var bool
     */
    private $separateHouseNumber;

    /**
     * @var bool
     */
    private $customsDeclarations;

    /**
     * @var bool
     */
    private $requiresEmail;

    /**
     * @var bool
     */
    private $requiresPhone;

    /**
     * @var bool
     */
    private $requiresSize;

    /**
     * @var bool
     */
    private $disallowsCod;

    /**
     * @var string
     */
    private $country;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var float
     */
    private $maxWeight;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isPickupPoints()
    {
        return $this->pickupPoints;
    }

    /**
     * @param bool $pickupPoints
     */
    public function setPickupPoints($pickupPoints)
    {
        $this->pickupPoints = $pickupPoints;
    }

    /**
     * @return bool
     */
    public function isApiAllowed()
    {
        return $this->apiAllowed;
    }

    /**
     * @param bool $apiAllowed
     */
    public function setApiAllowed($apiAllowed)
    {
        $this->apiAllowed = $apiAllowed;
    }

    /**
     * @return bool
     */
    public function isSeparateHouseNumber()
    {
        return $this->separateHouseNumber;
    }

    /**
     * @param bool $separateHouseNumber
     */
    public function setSeparateHouseNumber($separateHouseNumber)
    {
        $this->separateHouseNumber = $separateHouseNumber;
    }

    /**
     * @return bool
     */
    public function isCustomsDeclarations()
    {
        return $this->customsDeclarations;
    }

    /**
     * @param bool $customsDeclarations
     */
    public function setCustomsDeclarations($customsDeclarations)
    {
        $this->customsDeclarations = $customsDeclarations;
    }

    /**
     * @return bool
     */
    public function isRequiresEmail()
    {
        return $this->requiresEmail;
    }

    /**
     * @param bool $requiresEmail
     */
    public function setRequiresEmail($requiresEmail)
    {
        $this->requiresEmail = $requiresEmail;
    }

    /**
     * @return bool
     */
    public function isRequiresPhone()
    {
        return $this->requiresPhone;
    }

    /**
     * @param bool $requiresPhone
     */
    public function setRequiresPhone($requiresPhone)
    {
        $this->requiresPhone = $requiresPhone;
    }

    /**
     * @return bool
     */
    public function isRequiresSize()
    {
        return $this->requiresSize;
    }

    /**
     * @param bool $requiresSize
     */
    public function setRequiresSize($requiresSize)
    {
        $this->requiresSize = $requiresSize;
    }

    /**
     * @return bool
     */
    public function isDisallowsCod()
    {
        return $this->disallowsCod;
    }

    /**
     * @param bool $disallowsCod
     */
    public function setDisallowsCod($disallowsCod)
    {
        $this->disallowsCod = $disallowsCod;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @return float
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * @param float $maxWeight
     */
    public function setMaxWeight($maxWeight)
    {
        $this->maxWeight = $maxWeight;
    }

    /**
     * @param /stdClass $object
     * @return ApiCarrier
     */
    public static function fromJsonObject($object)
    {
        if (self::isJsonObjectValidCarrier($object) === false) {
            return null;
        }
        $carrier = new self();
        $carrier->setId((int)$object->id);
        $carrier->setName($object->name);
        $carrier->setPickupPoints(filter_var($object->pickupPoints, FILTER_VALIDATE_BOOLEAN));
        $carrier->setApiAllowed(filter_var($object->apiAllowed, FILTER_VALIDATE_BOOLEAN));
        $carrier->setSeparateHouseNumber(filter_var($object->separateHouseNumber, FILTER_VALIDATE_BOOLEAN));
        $carrier->setCustomsDeclarations(filter_var($object->customsDeclarations, FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresEmail(filter_var($object->requiresEmail, FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresPhone(filter_var($object->requiresPhone, FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresSize(filter_var($object->requiresSize, FILTER_VALIDATE_BOOLEAN));
        $carrier->setDisallowsCod(filter_var($object->disallowsCod, FILTER_VALIDATE_BOOLEAN));
        $carrier->setCountry($object->country);
        $carrier->setCurrency($object->currency);
        $carrier->setMaxWeight((float)$object->maxWeight);

        return $carrier;
    }

    public static function fromArray($array)
    {
        $carrier = new self();
        $carrier->setId((int)$array['id']);
        $carrier->setName($array['name']);
        $carrier->setPickupPoints(filter_var($array['pickupPoints'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setApiAllowed(filter_var($array['apiAllowed'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setSeparateHouseNumber(filter_var($array['separateHouseNumber'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setCustomsDeclarations(filter_var($array['customsDeclarations'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresEmail(filter_var($array['requiresEmail'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresPhone(filter_var($array['requiresPhone'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setRequiresSize(filter_var($array['requiresSize'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setDisallowsCod(filter_var($array['disallowsCod'], FILTER_VALIDATE_BOOLEAN));
        $carrier->setCountry($array['country']);
        $carrier->setCurrency($array['currency']);
        $carrier->setMaxWeight((float)$array['maxWeight']);

        return $carrier;
    }
    /**
     * @param /stdClass $object
     * @return bool
     */
    public static function isJsonObjectValidCarrier($object)
    {
        if (!isset($object->id)
            || !is_numeric($object->id)
            || !isset($object->name)
            || !isset($object->pickupPoints)
            || !isset($object->apiAllowed)
            || !isset($object->separateHouseNumber)
            || !isset($object->customsDeclarations)
            || !isset($object->requiresEmail)
            || !isset($object->requiresPhone)
            || !isset($object->requiresSize)
            || !isset($object->disallowsCod)
            || !isset($object->country)
            || !isset($object->currency)
            || !isset($object->maxWeight)
            || !is_numeric($object->maxWeight)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function mapToDbArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'is_pickup_points' => $this->isPickupPoints(),
            'has_carrier_direct_label' => $this->isApiAllowed(),
            'separate_house_number' => $this->isSeparateHouseNumber(),
            'customs_declarations' => $this->isCustomsDeclarations(),
            'requires_email' => $this->isRequiresEmail(),
            'requires_phone' => $this->isRequiresPhone(),
            'requires_size' => $this->isRequiresSize(),
            'disallows_cod' => $this->isDisallowsCod(),
            'country' => $this->getCountry(),
            'currency' => $this->getCurrency(),
            'max_weight' => $this->getMaxWeight(),
        ];
    }

}
