<?php

namespace VirtueMartModelZasilkovna\CheckoutModules\VMDefault;

use VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver;

/**
 * Virtuemart 3 default checkout data provider
 */
class Resolver extends AbstractResolver
{
    public function isDefault() {
        return true;
    }

    public function isActive() {
        return true;
    }
}
