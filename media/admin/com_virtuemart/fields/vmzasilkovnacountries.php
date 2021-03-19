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

class JFormFieldVmZasilkovnaCountries extends JFormFieldList {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'vmZasilkovnaCountries';

    protected function getOptions() {
        $fields = [];

        /** @var VirtueMartModelCountry $countryModel */
        $countryModel = VmModel::getModel('country');
        $countries = $countryModel->getCountries(TRUE, TRUE, FALSE);

        foreach ($countries as $country) {
            $fields[] = JHtml::_('select.option', $country->virtuemart_country_id, $country->country_name);
        }

        $fields = array_merge(parent::getOptions(), $fields);

        return $fields;
    }
}
