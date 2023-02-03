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
     */
    protected function getOptions()
    {
        $fields = [];

        /** @var VirtueMartModelZasilkovna $zasilkovnaModel */
        $zasilkovnaModel = VmModel::getModel('zasilkovna');
        $hdCarriers = $zasilkovnaModel->getFilteredHdCarriers($this->getShipmentMethodId());
        $fields[''] = JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_CHOOSE_CARRIER');
        foreach ($hdCarriers as $hdCarrier) {
            $fields[] = JHtml::_('select.option', $hdCarrier['id'], $hdCarrier['name']);
        }
        $fields = array_merge(parent::getOptions(), $fields);

        return $fields;
    }

    public function getShipmentMethodId()
    {
        $shipmentMethodId = null;
        $input = JFactory::getApplication()->input;
        if ($input->getString('view') === 'shipmentmethod') {
            $shipmentIdArray = $input->get('cid', null, 'array');
            if ($shipmentIdArray && count($shipmentIdArray) === 1) {
                $shipmentMethodId = (int)array_pop($shipmentIdArray);
            }
        }

        return $shipmentMethodId;
    }
}