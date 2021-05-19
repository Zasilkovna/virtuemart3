<?php

namespace VirtueMartModelZasilkovna\CheckoutModules;

abstract class AbstractResolver
{
    /** @var string */
    private $dir;

    /** Is related to default checkout.
     * @return bool
     */
    abstract public function isDefault();

    /** Is checkout currently active?
     * @return bool
     */
    abstract public function isActive();

    /** __DIR__ of Resolver
     * @return string
     */
    protected function getDir() {
        if ($this->dir === null) {
            $reflector = new \ReflectionClass(get_class($this));
            $this->dir = dirname($reflector->getFileName());
        }

        return $this->dir;
    }

    /** Returns absolute path to PHTML file representing Packeta box in checkout in delivery method selection section
     * @return string
     */
    public function getTemplate() {
        return $this->getDir() . '/template.phtml';
    }

    /** Returns absolute path to PHTML file representing HTML ending piece after all Packeta boxes in checkout in delivery method selection section
     * @return string
     */
    public function getTailBlock() {
        return $this->getDir() . '/tail-block.phtml';
    }

    /** Returns absolute path to Javascript file representing ending piece after all Packeta boxes in checkout in delivery method selection section
     * @return string|null
     */
    public function getTailBlockJs() {
        $path = $this->getDir() . '/tail-block.js';
        if (is_file($path)) {
            return $path;
        }

        return null;
    }

    /**
     * @param string $type
     * @param string $element
     * @return bool
     */
    protected function isModuleEnabled($type, $element) {
        $db = \JFactory::getDBO();
        $q = "SELECT enabled FROM #__extensions WHERE element = " . $db->quote($element) . " AND type = " . $db->quote($type);
        $db->setQuery($q);
        $obj = $db->loadObject();
        if (empty($obj)) {
            return false;
        }

        return $obj->enabled === '1';
    }
}
