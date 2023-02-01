<?php
defined('_JEXEC') or die();

/**
 *
 * @package VirtueMart
 * @subpackage Plugins  - Elements
 * @author Valérie Isaksen
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2011 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: $
 */

JFormHelper::loadFieldClass('list');
jimport('joomla.form.formfield');

class JFormFieldVmZasilkovnaHdCarriers extends JFormFieldList {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'vmZasilkovnaHdCarriers';


    public function __construct($form = null)
    {
        parent::__construct($form);
    }

    /**
     * @return array
     */
    protected function getOptions() {
        $fields = [];

        /** @var VirtueMartModelZasilkovna $zasilkovnaModel */
        $zasilkovnaModel = VmModel::getModel('zasilkovna');
        $hdCarriers = $zasilkovnaModel->getFilteredHdCarriers($this->getShipmentMethodId());
        $fields[''] = 'Vyberte dopravce';
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
                $shipmentMethodId = (int) array_pop($shipmentIdArray);
            }
        }

        return $shipmentMethodId;
    }
}
