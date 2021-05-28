<?php

namespace VirtueMartModelZasilkovna;

class ShipmentMethodStorage
{
    /** @var string */
    private $namespace = 'packeteryShipmentMethods';

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
     * @param string|int $methodId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($methodId, $key, $default = null)
    {
        return $this->session->get($methodId . '.' . $key, $default, $this->namespace);
    }

    /**
     * @param string|int $methodId
     * @param string $key
     * @param mixed $value
     */
    public function set($methodId, $key, $value)
    {
        $this->session->set($methodId . '.' . $key, $value, $this->namespace);
    }

    /**
     * @param string|int $methodId
     * @param string $key
     */
    public function clear($methodId, $key)
    {
        $this->session->clear($methodId . '.' . $key, $this->namespace);
    }
}
