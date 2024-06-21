<?php

use VirtueMartModelZasilkovna\ShipmentMethod;

defined('_JEXEC') or die('Restricted access');

jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaShowVendors extends JFormFieldHidden
{

    protected $type = 'ShowVendors';

    /**
     * @param SimpleXMLElement $element
     * @param mixed $value
     * @param string|null $group
     * @return bool
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);
        $this->value = (int) $this->shouldShowVendors();

        return $result;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function shouldShowVendors()
    {
        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        return $model->getPacketeryShipmentMethod(ShipmentMethod::getShipmentMethodIdFromGet())->needsVendors();
    }
}
