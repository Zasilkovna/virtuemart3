<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('checkboxes');

/**
 * Form Field class for the Joomla Platform.
 * Displays options as a list of checkboxes.
 * Multiselect may be forced to be true.
 *
 * @see    JFormFieldCheckbox
 * @since  1.7.0
 */
class JFormFieldVmZasilkovnaPpVendors extends JFormFieldCheckboxes
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.7.0
     */
    protected $type = 'vmZasilkovnaPpVendors';
    protected $layout = 'vmzasilkovnappvendors';

    public function __construct($form = null)
    {
        parent::__construct($form);
        $this->checkedOptions = $this->getCheckedOptions();

    }

    public function getOptions()
    {
        /** @var VirtueMartModelZasilkovna $zasilkovnaModel */
        $zasilkovnaModel = VmModel::getModel('zasilkovna');
        $vendors = $zasilkovnaModel->getVendorsByShipmentId($this->getShipmentMethodId());
        if (empty($vendors)) {
            $this->hidden = true;
        }
//        echo '<pre>';
//        var_dump($vendors);
//        echo '</pre>';
//        $vendors = VirtueMartModelZasilkovna\Vendors::filterOutVendorsByCountryIds();
        $options = [];

        foreach($vendors as $vendorId => $vendor) {

                $options[] = (object) array_merge( (array) JHtml::_('select.option', $vendorId, JText::_($vendor['name'])), ['checked' => false]);

        }
//                echo '<pre>';
//        var_dump($options);
//        echo '</pre>';

        return $options;
    }

    public function getCheckedOptions()
    {
        return '';
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
    public function renderField($options = array())
    {
echo '<pre>';
        var_dump($options);
        echo '</pre>';
        die(1);

        return parent::renderField($options);
    }

}
