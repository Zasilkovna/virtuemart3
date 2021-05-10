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

    /** @var array */
    private $options;

    /** @var string */
    private $expiresAtKey = 'expiresAt';

    /**
     * SelectedPointSession constructor.
     *
     * @param \Joomla\CMS\Session\Session $session
     * @param array $options
     */
    public function __construct(\Joomla\CMS\Session\Session $session, array $options) {
        $this->session = $session;
        $this->options = $options;
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
        $this->clear(self::BRANCH_ID);
        $this->clear(self::BRANCH_CURRENCY);
        $this->clear(self::BRANCH_NAME_STREET);
        $this->clear(self::BRANCH_COUNTRY);
        $this->clear(self::BRANCH_CARRIER_ID);
        $this->clear(self::BRANCH_CARRIER_PICKUP_POINT);
        $this->clear($this->expiresAtKey);
    }

    private function checkTimers() {
        $expiresAt = $this->session->get($this->expiresAtKey, null, $this->namespace);
        if ($expiresAt !== null && $expiresAt <= time()) {
            $this->clearPickedDeliveryPoint();
        }
    }

    public function resetTimers() {
        $this->clear($this->expiresAtKey);

        $expire = $this->getExpire();
        if (is_numeric($expire)) {
            $expiresAt = time() + $expire;
            $this->set($this->expiresAtKey, $expiresAt);
        }
    }

    /**
     * @return int duration in seconds
     */
    private function getExpire() {
        return $this->options['expire'];
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null) {
        $this->checkTimers();
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
