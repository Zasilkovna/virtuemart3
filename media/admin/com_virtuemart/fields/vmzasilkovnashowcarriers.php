<?php

use VirtueMartModelZasilkovna\ShipmentMethod;

defined('_JEXEC') or die('Restricted access');

jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaShowCarriers extends JFormFieldHidden
{

    protected $type = 'ShowCarriers';

    /**
     * @param SimpleXMLElement $element
     * @param mixed $value
     * @param string|null $group
     * @return bool
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);
        $this->value = (int) $this->shouldShowCarriers();

        return $result;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function shouldShowCarriers()
    {
        /** @var VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $carriers = $model->getAvailableCarriersByShipmentId(ShipmentMethod::getShipmentMethodIdFromGet(), true);

        return !empty($carriers);
    }
}
