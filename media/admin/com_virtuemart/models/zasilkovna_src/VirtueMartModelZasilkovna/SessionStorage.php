<?php

namespace VirtueMartModelZasilkovna;

class SessionStorage
{

    private string $namespace = 'packeterySession';

    private \Joomla\CMS\Session\Session $session;

    /**
     * @param \Joomla\CMS\Session\Session $session
     * @param ?string $namespace
     */
    public function __construct(\Joomla\CMS\Session\Session $session, string $namespace = null)
    {
        $this->session = $session;
        if ($namespace) {
            $this->namespace = $namespace;
        }
    }

    /**
     * @param int|string $id
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(int|string $id, string $key, mixed $default = null): mixed
    {
        return $this->session->get($this->composeKey($id, $key), $default, $this->namespace);
    }

    /**
     * @param int|string $id
     * @param string $key
     * @param mixed $value
     */
    public function set(int|string $id, string $key, mixed $value): void
    {
        $this->session->set($this->composeKey($id, $key), $value, $this->namespace);
    }

    /**
     * @param int|string $id
     * @param string $key
     */
    public function clear(int|string $id, string $key): void
    {
        $this->session->clear($this->composeKey($id, $key), $this->namespace);
    }

    /**
     * @param int|string $id
     * @param string $key
     * @return string
     */
    private function composeKey(int|string $id, string $key): string
    {
        if ($id === '' || $key === '') {
            throw new \InvalidArgumentException('Id or key cannot be empty');
        }

        return $id . '.' . $key;
    }
}
