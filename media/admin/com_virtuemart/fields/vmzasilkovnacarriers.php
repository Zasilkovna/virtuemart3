<?php
use VirtueMartModelZasilkovna\ShipmentMethod;

defined('_JEXEC') or die();

JFormHelper::loadFieldClass('list');
jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaCarriers extends JFormFieldList
{

    /**
     * Element name
     *
     * @access    protected
     * @var        string
     */
    var $type = 'vmZasilkovnaCarriers';

    /**
     * Controls if should display HD or PP carriers
     *
     * @var bool
     */
    protected $pickupPoints = false;

    /**
     * @return array
     * @throws Exception
     */
    protected function getOptions()
    {
        $fields = [];

        /** @var VirtueMartModelZasilkovna $zasilkovnaModel */
        $zasilkovnaModel = VmModel::getModel('zasilkovna');
        $carriers = $zasilkovnaModel->getAvailableCarriersByShipmentId(
            ShipmentMethod::getShipmentMethodIdFromGet(),
            $this->pickupPoints
        );
        $fields[''] = JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_CHOOSE_CARRIER');

        foreach ($carriers as $carrier) {
            $fields[] = JHtml::_('select.option', $carrier['id'], $carrier['name']);
        }

        return array_merge(parent::getOptions(), $fields);
    }

    /**
     * @param SimpleXMLElement $element
     * @param mixed $value
     * @param string $group
     * @return bool
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $this->pickupPoints = (bool)((string) $element['pickup_points']);
        return parent::setup($element, $value, $group);
    }
}
