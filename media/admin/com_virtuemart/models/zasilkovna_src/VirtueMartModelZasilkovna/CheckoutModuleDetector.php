<?php

namespace VirtueMartModelZasilkovna;

class CheckoutModuleDetector
{
    public function getCheckoutModulesDir() {
        return __DIR__ . '/CheckoutModules';
    }

    /**
     * @return \VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver
     */
    public function getActiveCheckout() {
        $defaultCheckout = null;
        $activeCheckout = null;

        $baseDir = $this->getCheckoutModulesDir();
        $result = scandir($baseDir);
        foreach ($result as $basename) {
            $dir = $baseDir . '/' . $basename;
            if (!is_dir($dir) || $basename === '.' || $basename === '..') {
                continue;
            }

            $className = '\\VirtueMartModelZasilkovna\\CheckoutModules\\' . $basename. '\\Resolver';
            /** @var \VirtueMartModelZasilkovna\CheckoutModules\AbstractResolver $checkoutResolver */
            $checkoutResolver = new $className();

            $isDefault = $checkoutResolver->isDefault();
            $isActive = $checkoutResolver->isActive();

            if ($isDefault && $isActive) {
                $defaultCheckout = $checkoutResolver;
                continue;
            }

            if (!$isDefault && $isActive) {
                $activeCheckout = $checkoutResolver;
                break;
            }
        }

        if ($activeCheckout) {
            return $activeCheckout;
        }

        return $defaultCheckout;
    }
}
