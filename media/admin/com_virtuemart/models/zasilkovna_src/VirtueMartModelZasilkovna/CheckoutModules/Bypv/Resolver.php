<?php

namespace VirtueMartModelZasilkovna\CheckoutModules\Bypv;

use VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver;

class Resolver extends AbstractResolver
{
    /**
     * @inheritDoc
     */
    public function isDefault() {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isActive() {
        return $this->isModuleEnabled('plugin', 'opc_for_vm_bypv');
    }
}
