<?php

namespace VirtueMartModelZasilkovna;

class ShipmentMethodStorage
{
    const SHIPMENT_METHOD_STORAGE_PREFIX = 'shipmentMethod-';
    const SESSION_NAMESPACE = 'packetery';

    /** @var \Joomla\CMS\Session\Session */
    private $session;

    /**
     * @param \Joomla\CMS\Session\Session $session
     */
    public function __construct(\Joomla\CMS\Session\Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param int $methodId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($methodId, $key, $default = null)
    {
        $methodStorageId = self::SHIPMENT_METHOD_STORAGE_PREFIX . $methodId;
        $stored = $this->session->get($methodStorageId, null, self::SESSION_NAMESPACE);
        if (!is_array($stored) || !isset($stored[$key])) {

            return $default;
        }

        return $stored[$key];
    }

    /**
     * @param int $methodId
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($methodId, $key, $value)
    {
        $methodStorageId = self::SHIPMENT_METHOD_STORAGE_PREFIX . $methodId;
        $stored = $this->session->get($methodStorageId, null, self::SESSION_NAMESPACE);
        if (!is_array($stored)) {
            $stored = [];
        }
        $stored[$key] = $value;

        return $this->session->set($methodStorageId, $stored, self::SESSION_NAMESPACE);
    }

    /**
     * @param int $methodId
     * @param string $key
     * @return mixed
     */
    public function clear($methodId, $key)
    {
        return $this->set($methodId, $key, null);
    }
}
