<?php

namespace VirtueMartModelZasilkovna\CheckoutModules\RuposTel;

use VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver;

class Resolver extends AbstractResolver
{
    public function isDefault() {
        return false;
    }

    public function isActive() {
        return $this->isModuleEnabled('plugin', 'opc') && $this->isModuleEnabled('component', 'com_onepage') && $this->isRuposTelOnepageEnabled();
    }

    private function isRuposTelOnepageEnabled() {
        $q = "SELECT 1 FROM #__onepage_config WHERE config_name='opc_vm_config' AND config_subname = 'disable_op'";
        $db = \JFactory::getDBO();
        $db->setQuery($q);
        $obj = $db->loadObject();
        if (!empty($obj)) {
            return false; // OPC is disabled
        }

        return true;
    }
}
