<?php

namespace VirtueMartModelZasilkovna\Order;

class AddressProvider
{
    /** @var string|null */
    private $firstName;

    /** @var string|null */
    private $lastName;

    /** @var string|null */
    private $address;

    /** @var string|null */
    private $street;

    /** @var string|null */
    private $houseNumber;

    /** @var string|null */
    private $city;

    /** @var string|null */
    private $zip;

    /** @var string|null */
    private $phone;

    /** @var string|null */
    private $email;

    /**
     * Private constructor to force usage of factory methods
     *
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $address
     * @param string|null $city
     * @param string|null $zip
     * @param string|null $phone
     * @param string|null $email
     */
    private function __construct($firstName, $lastName, $address, $city, $zip, $phone, $email)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->address = $address;
        $this->city = $city;
        $this->zip = $zip;
        $this->phone = $phone;
        $this->email = $email;

        $extracted = self::extractStreetAndHouseNumber($address);
        $this->street = $extracted['street'];
        $this->houseNumber = $extracted['houseNumber'];
    }

    /**
     * @param \stdClass $userInfo
     * @return self
     */
    public static function fromUserInfo(\stdClass $userInfo)
    {
        $address1 = property_exists($userInfo, 'address_1') ? $userInfo->address_1 : '';
        $address2 = property_exists($userInfo, 'address_2') ? $userInfo->address_2 : '';
        $address = $address2 ? $address1 . ' ' . $address2 : $address1;

        $phone1 = property_exists($userInfo, 'phone_1') ? $userInfo->phone_1 : '';
        $phone2 = property_exists($userInfo, 'phone_2') ? $userInfo->phone_2 : '';
        $phone = $phone1 ?: $phone2;

        return new self(
            property_exists($userInfo, 'first_name') ? $userInfo->first_name : null,
            property_exists($userInfo, 'last_name') ? $userInfo->last_name : null,
            $address,
            property_exists($userInfo, 'city') ? $userInfo->city : null,
            property_exists($userInfo, 'zip') ? $userInfo->zip : null,
            $phone,
            property_exists($userInfo, 'email') ? $userInfo->email : null
        );
    }

    /**
     * @param array<string, string|null> $userInfo
     * @return self
     */
    public static function fromUserInfoArray($userInfo)
    {
        $userInfo = json_decode(json_encode($userInfo), false);

        return self::fromUserInfo($userInfo);
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return (string)$this->firstName;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return (string)$this->lastName;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getHouseNumber()
    {
        return (string)$this->houseNumber;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return (string)$this->city;
    }

    /**
     * @return string
     */
    public function getZip()
    {
        return (string)$this->zip;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return (string)$this->email;
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * Validates phone number and returns NULL if not valid
     * @return string|null
     */
    public function getNormalizedPhone()
    {
        $phone = $this->phone;
        if (is_null($phone) || $phone === '') {
            return null;
        }

        $phone = str_replace(' ', '', trim($phone));

        // only + and numbers are allowed
        if (preg_match('/^\+?\d+$/', $phone) !== 1) {
            $phone = '';
        }

        return ($phone ?: null);
    }

    /**
     * Extracts the street and house number from an address string.
     *
     * @param string $address The address string to parse.
     * @return array{street: string, houseNumber: string|null} The extracted street and house number.
     */
    public static function extractStreetAndHouseNumber($address)
    {
        $streetMatches = array();

        // Match the address pattern
        $match = preg_match('/^(.*[^0-9]+) (([1-9][0-9]*)\/)?([1-9][0-9]*[a-cA-C]?)$/', $address, $streetMatches);

        if (!$match) {
            // If no match, set houseNumber to null and street to the original address
            $houseNumber = null;
            $street = $address;
        } elseif (!isset($streetMatches[4])) {
            // If match but no house number part, set houseNumber to null and street to the matched street name
            $houseNumber = null;
            $street = $streetMatches[1];
        } else {
            // If match and house number part is set, construct houseNumber and set street to the matched street name
            $houseNumber = (!empty($streetMatches[3])) ? $streetMatches[3] . "/" . $streetMatches[4] : $streetMatches[4];
            $street = $streetMatches[1];
        }

        return [
            'street' => $street,
            'houseNumber' => $houseNumber
        ];
    }
}
