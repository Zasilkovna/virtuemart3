<?php

namespace VirtueMartModelZasilkovna\Order;

class Order
{
    /* @var int $id */
    private $id;

    /* @var int $virtuemart_order_id */
    private $virtuemart_order_id;

    /* @var int $virtuemart_shipmentmethod_id */
    private $virtuemart_shipmentmethod_id;

    /* @var string $order_number */
    private $order_number;

    /* @var float|null $zasilkovna_packet_id */
    private $zasilkovna_packet_id;

    /* @var float $zasilkovna_packet_price */
    private $zasilkovna_packet_price;

    /* @var float $weight */
    private $weight;

    /* @var int $width */
    private $width;

    /* @var int $length */
    private $length;

    /* @var int $height */
    private $height;

    /* @var float $branch_id */
    private $branch_id;

    /* @var string $branch_currency */
    private $branch_currency;

    /* @var string $branch_name_street */
    private $branch_name_street;

    /* @var bool $is_carrier */
    private $is_carrier;

    /* @var string $carrier_pickup_point */
    private $carrier_pickup_point;

    /* @var string $email */
    private $email;

    /* @var string $phone */
    private $phone;

    /* @var string $first_name */
    private $first_name;

    /* @var string $last_name */
    private $last_name;

    /* @var string $address */
    private $address;

    /* @var string $city */
    private $city;

    /* @var string $zip_code */
    private $zip_code;

    /* @var string $virtuemart_country_id */
    private $virtuemart_country_id;

    /* @var int $adult_content */
    private $adult_content;

    /* @var bool $is_cod */
    private $is_cod;

    /* @var float $packet_cod */
    private $packet_cod;

    /* @var bool $exported */
    private $exported;

    /* @var bool $printed_label */
    private $printed_label;

    /* @var string $shipment_name */
    private $shipment_name;

    /* @var float $shipment_cost */
    private $shipment_cost;

    /* @var float $shipment_package_fee */
    private $shipment_package_fee;

    /* @var int $tax_id */
    private $tax_id;

    /**
     * @param array|null $orderData
     *
     * @throws \InvalidArgumentException
     * @return self
     */
    public static function fromArray($orderData)
    {
        if ($orderData === null) {
            throw new \InvalidArgumentException('Order data is required');
        }
        $order = new self();
        $unusedDefaultVMProperties = ['created_on', 'created_by', 'modified_on', 'modified_by', 'locked_on', 'locked_by'];
        foreach ($orderData as $property => $value) {
            if (in_array($property, $unusedDefaultVMProperties, true)) {
                continue;
            }
            $order->$property = $value;
        }

        return $order;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getVirtuemartOrderId()
    {
        return $this->virtuemart_order_id;
    }

    /**
     * @return int
     */
    public function getVirtuemartShipmentmethodId()
    {
        return $this->virtuemart_shipmentmethod_id;
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->order_number;
    }

    /**
     * @return float
     */
    public function getZasilkovnaPacketId()
    {
        return $this->zasilkovna_packet_id ?: 0;
    }

    /**
     * @return float
     */
    public function getZasilkovnaPacketPrice()
    {
        return $this->zasilkovna_packet_price;
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @return float
     */
    public function getBranchId()
    {
        return $this->branch_id;
    }

    /**
     * @return string
     */
    public function getBranchCurrency()
    {
        return $this->branch_currency;
    }

    /**
     * @return string
     */
    public function getBranchNameStreet()
    {
        return $this->branch_name_street;
    }

    /**
     * @return bool
     */
    public function isIsCarrier()
    {
        return $this->is_carrier;
    }

    /**
     * @return string
     */
    public function getCarrierPickupPoint()
    {
        return $this->carrier_pickup_point;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
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
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->last_name;
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
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getZipCode()
    {
        return $this->zip_code;
    }

    /**
     * @return string
     */
    public function getVirtuemartCountryId()
    {
        return $this->virtuemart_country_id;
    }

    /**
     * @return int
     */
    public function getAdultContent()
    {
        return $this->adult_content;
    }

    /**
     * @return bool
     */
    public function isIsCod()
    {
        return $this->is_cod;
    }

    /**
     * @return float
     */
    public function getPacketCod()
    {
        return $this->packet_cod;
    }

    /**
     * @return bool
     */
    public function isExported()
    {
        return $this->exported;
    }

    /**
     * @return bool
     */
    public function isPrintedLabel()
    {
        return $this->printed_label;
    }

    /**
     * @return string
     */
    public function getShipmentName()
    {
        return $this->shipment_name;
    }

    /**
     * @return float
     */
    public function getShipmentCost()
    {
        return $this->shipment_cost;
    }

    /**
     * @return float
     */
    public function getShipmentPackageFee()
    {
        return $this->shipment_package_fee;
    }

    /**
     * @return int
     */
    public function getTaxId()
    {
        return $this->tax_id;
    }

    /**
     * @return bool
     */
    public function hasPacketId()
    {
        return $this->getZasilkovnaPacketId() !== 0;
    }

    /**
     * @return bool
     */
    public function getIsCarrier()
    {
        return $this->is_carrier;
    }

    /**
     * @return bool
     */
    public function isHomeDelivery()
    {
        return ($this->getIsCarrier() && $this->getCarrierPickupPoint() === '');
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }
}
