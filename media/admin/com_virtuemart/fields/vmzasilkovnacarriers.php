<?php
defined('_JEXEC') or die();

JFormHelper::loadFieldClass('list');
jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaCarriers extends JFormFieldList {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'vmZasilkovnaCarriers';

    /** @var \VirtueMartModelZasilkovna\Carrier\Repository */
    private $carrierRepository;

    /** @var \VirtueMartModelZasilkovna */
    private $model;

    public function __construct($form = null) {
        parent::__construct($form);
        $this->carrierRepository = new \VirtueMartModelZasilkovna\Carrier\Repository();
        $this->model = \VmModel::getModel('zasilkovna');
    }

    public function getFirstCarrierId($options) {
        $carrierOptionsKeys = array_keys($options);
        $carrierOptionsKey = array_shift($carrierOptionsKeys);
        return (string) $carrierOptionsKey;
    }

    public function getOptionsForShipment($shipmentMethodId) {
        $zasMethod = $this->model->getPacketeryShipmentMethod($shipmentMethodId);
        return $this->getOptionsForPacketeryShipmentMethod($zasMethod);
    }

    public function getOptionsForPacketeryShipmentMethod($zasMethod) {
        $allowedCountryCodes = $this->model->getAllowedCountryCodes($zasMethod);
        $blockedCountryCodes = $this->model->getBlockedCountryCodes($zasMethod);
        $carriers = $this->carrierRepository->getAllActiveCarriers($allowedCountryCodes, $blockedCountryCodes);

        $fields = [];

        if ($this->model->hasPacketaPickupPointCountryCode($allowedCountryCodes, $blockedCountryCodes)) {
            $fields[\VirtueMartModelZasilkovna\Carrier\Repository::FORM_FIELD_PACKETA_PICKUP_POINTS] = JHtml::_('select.option', \VirtueMartModelZasilkovna\Carrier\Repository::FORM_FIELD_PACKETA_PICKUP_POINTS, JText::_('PLG_VMSHIPMENT_PACKETERY_PICKUP_POINT_OPTION_LABEL'));
        }

        foreach ($carriers as $carrier) {
            $fields[$carrier['id']] = JHtml::_('select.option', $carrier['id'], $carrier['name']);
        }

        return $fields;
    }

    /**
     * @return array
     */
    protected function getOptions() {
        $shipmentMethodId = null;
        $input = JFactory::getApplication()->input;
        if ($input->getString('view') === 'shipmentmethod') {
            $shipmentIdArray = $input->get('cid', null, 'array');
            if ($shipmentIdArray && count($shipmentIdArray) === 1) {
                $shipmentMethodId = (int) array_pop($shipmentIdArray);
            }
        }

        return $this->getOptionsForShipment($shipmentMethodId);
    }
}
