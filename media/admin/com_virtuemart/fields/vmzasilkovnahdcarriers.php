<?php
defined('_JEXEC') or die();

JFormHelper::loadFieldClass('list');
jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaHdCarriers extends JFormFieldList
{

    /**
     * Element name
     *
     * @access    protected
     * @var        string
     */
    var $type = 'vmZasilkovnaHdCarriers';

    /**
     * @return array
     * @throws Exception
     */
    protected function getOptions()
    {
        $fields = [];

        /** @var VirtueMartModelZasilkovna $zasilkovnaModel */
        $zasilkovnaModel = VmModel::getModel('zasilkovna');
        $hdCarriers = $zasilkovnaModel->getAvailableHdCarriersByShipmentId($this->getShipmentMethodId());
        $fields[''] = JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_CHOOSE_CARRIER');

        foreach ($hdCarriers as $hdCarrier) {
            $fields[] = JHtml::_('select.option', $hdCarrier['id'], $hdCarrier['name']);
        }

        return array_merge(parent::getOptions(), $fields);
    }

    /**
     * @return int|null
     * @throws Exception
     */
    public function getShipmentMethodId()
    {
        $shipmentMethodId = null;
        $input = JFactory::getApplication()->input;

            $shipmentIdArray = $input->get('cid', null, 'array');
            if ($shipmentIdArray && count($shipmentIdArray) === 1) {
                $shipmentMethodId = (int) $shipmentIdArray[0];
            }

        return $shipmentMethodId;
    }
}
