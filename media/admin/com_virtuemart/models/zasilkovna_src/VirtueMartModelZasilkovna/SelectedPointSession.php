<?php

namespace VirtueMartModelZasilkovna;

class SelectedPointSession
{
    const BRANCH_ID = 'branch_id';
    const BRANCH_CURRENCY = 'branch_currency';
    const BRANCH_NAME_STREET = 'branch_name_street';
    const BRANCH_COUNTRY = 'branch_country';
    const BRANCH_CARRIER_ID = 'branch_carrier_id';
    const BRANCH_CARRIER_PICKUP_POINT = 'branch_carrier_pickup_point';

    /** @var \Joomla\CMS\Session\Session */
    private $session;

    /** @var string  */
    private $namespace = 'selectedPacketaPoint';

    /**
     * SelectedPointSession constructor.
     *
     * @param \VirtueMartModelZasilkovna\Joomla\CMS\Session\Session $session
     */
    public function __construct(\Joomla\CMS\Session\Session $session) {
        $this->session = $session;
    }

    /** Has session branch id selected
     * @return bool
     */
    public function hasPointSelected()
    {
        $branchId = $this->get(self::BRANCH_ID, '');

        if (empty($branchId)) {
            return false;
        }

        return true;
    }

    public function clearPickedDeliveryPoint() {
        $this->session->clear(self::BRANCH_ID, $this->namespace);
        $this->session->clear(self::BRANCH_CURRENCY, $this->namespace);
        $this->session->clear(self::BRANCH_NAME_STREET, $this->namespace);
        $this->session->clear(self::BRANCH_COUNTRY, $this->namespace);
        $this->session->clear(self::BRANCH_CARRIER_ID, $this->namespace);
        $this->session->clear(self::BRANCH_CARRIER_PICKUP_POINT, $this->namespace);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return $this->session->get($key, $default, $this->namespace);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($key, $value) {
        return $this->session->set($key, $value, $this->namespace);
    }

    /**
     * @param string $key
     * @return void
     */
    public function clear($key) {
        $this->session->clear($key, $this->namespace);
    }
}
