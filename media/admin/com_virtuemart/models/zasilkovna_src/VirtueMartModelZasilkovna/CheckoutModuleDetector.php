<?php

namespace VirtueMartModelZasilkovna;

class CheckoutModuleDetector
{
    /** @var array */
    private $checkoutConfig;

    public function __construct() {
        $this->checkoutConfig = [
            'rupostel' => [ // checkout module custom name
                'isDefault' => false,
                'isActive' => function () {
                    return $this->isModuleEnabled('opc') && $this->isRupostelOnepageEnabled();
                },
                'template' => null, // null => rupostel
                'tail-block' => null, // null => rupostel
                'tail-block-js' => null, // null => rupostel. JS file is never resolved to default.
            ],
            null => [
                'isDefault' => true,
                'template' => 'default',
                'tail-block' => 'default',
                'tail-block-js' => 'vm3',
            ],
        ];
    }

    private function isRupostelOnepageEnabled() {
        $q = "SELECT 1 FROM #__onepage_config WHERE config_name='opc_vm_config' AND config_subname = 'disable_op'";
        $db = \JFactory::getDBO();
        $db->setQuery($q);
        $obj = $db->loadObject();
        if (!empty($obj)) {
            return false; // OPC is disabled
        }

        return true;
    }

    /**
     * @param string $element
     * @return bool
     */
    private function isModuleEnabled($element) {
        $db = \JFactory::getDBO();
        $q = "SELECT enabled FROM #__extensions WHERE element = " . $db->quote($element);
        $db->setQuery($q);
        $obj = $db->loadObject();
        if (empty($obj)) {
            return false; // rupostel is not installed
        }

        return $obj->enabled === '1';
    }

    /**
     * @return string|null
     */
    public function getActiveCheckoutName() {
        foreach ($this->checkoutConfig as $name => $mapping) {
            if (isset($mapping['isActive']) && $mapping['isDefault'] !== true) {
                $checker = $mapping['isActive'];
                $isActiveResult = call_user_func($checker);
                if ($isActiveResult) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * @param string|null $checkoutName
     * @return string
     */
    public function getTemplate($checkoutName) {
        return $this->getConfigValue($checkoutName, 'template', $checkoutName);
    }

    /**
     * @param string|null $checkoutName
     * @return string
     */
    public function getTailBlock($checkoutName) {
        return $this->getConfigValue($checkoutName, 'tail-block', $checkoutName);
    }

    /**
     * @param string|null $checkoutName
     * @return string
     */
    public function getTailBlockJs($checkoutName) {
        return $this->getConfigValue($checkoutName, 'tail-block-js', $checkoutName);
    }

    /**
     * @param string|null $checkoutName
     * @param string $key
     * @param $default
     * @return mixed
     */
    private function getConfigValue($checkoutName, $key, $default = null) {
        if (isset($this->checkoutConfig[$checkoutName][$key])) {
            $template = $this->checkoutConfig[$checkoutName][$key];
        } else {
            $template = $default;
        }

        return $template;
    }
}
