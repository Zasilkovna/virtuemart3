<?php

// No direct access to this file
defined('_JEXEC') or die('Restricted access');
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

/**
 * @param $src
 * @param $dst
 */
function recurse_copy($src, $dst) {
	if(is_dir($src)) {
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ($file = readdir($dir))) {
			if(($file != '.') && ($file != '..')) {
				if(is_dir($src . '/' . $file)) {
					recurse_copy($src . '/' . $file, $dst . '/' . $file);
				}
				else {
					echo "<b>adding file:</b> " . $dst . DS . $file . "<br>";
					copy($src . '/' . $file, $dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}
	else {
		echo "<b>adding file:</b> " . $dst . "<br>";
		copy($src, $dst);
	}
}

/**
 * @param $dir
 * @param false $ignore
 */
function recurse_delete($dir, $ignore = false) {
	echo "deleting: " . $dir . "<br>";

	if(is_dir($dir)) {
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				recurse_delete($file);
			else
				unlink($file);
		}
		rmdir($dir);
	}
	else {
        if ($ignore) {
            @unlink($dir);
        } else {
            unlink($dir);
        }
	}
}

/**
 * Class plgVmShipmentZasilkovnaInstallerScript
 */
class plgVmShipmentZasilkovnaInstallerScript {

    private $migratingPricingRules = false;

	/**
	 * Constructor
	 *
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 */
	public function __constructor(JAdapterInstance $adapter) {

	}

	/**
	 * Called before any type of action
	 *
	 * @param   string $route Which action is happening (install|uninstall|discover_install)
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function preflight($route, JAdapterInstance $adapter) {
        if ($route === 'update') {
            $media_path = JPATH_ROOT . DS . 'media' . DS . 'com_zasilkovna';
            recurse_delete($media_path);

            $this->removeAdministratorFiles();
        }
	}

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

	/**
	 * Called after any type of action
	 *
	 * @param   string $route Which action is happening (install|uninstall|discover_install)
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function postflight($route, JAdapterInstance $adapter) {
		$vm_admin_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart';
        $media_path = JPATH_ROOT . DS . 'media' . DS . 'com_zasilkovna' . DS;
		if($route == "install") {
			recurse_copy($media_path . 'admin' . DS . 'com_virtuemart' . DS, $vm_admin_path . DS);

            $files = scandir($media_path . 'admin' . DS);
            foreach ($files as $index => $filename){
                if ($this->endsWith($filename, '.ini')){
                    $locale = explode('.',$filename)[0];
                    if (file_exists(JPATH_ADMINISTRATOR . DS . 'language' . DS . $locale))
                    {
                        recurse_copy($media_path . 'admin' . DS . $filename, JPATH_ADMINISTRATOR . DS . 'language' . DS . $locale . DS . $filename);
                    }
                }
            }

			$db =& JFactory::getDBO();
			$q = "CREATE TABLE IF NOT EXISTS #__virtuemart_zasilkovna_branches (
										`id` int(10) NOT NULL,
										`name_street` varchar(200) NOT NULL,
										`currency` text NOT NULL,
										`country` varchar(10) NOT NULL
										) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
			$db->setQuery($q);
			$db->query();

			$q = "CREATE TABLE IF NOT EXISTS `#__virtuemart_shipment_plg_zasilkovna` (
							  `id` int(1) unsigned NOT NULL AUTO_INCREMENT,
							  `virtuemart_order_id` int(11) unsigned DEFAULT NULL,
							  `virtuemart_shipmentmethod_id` mediumint(1) unsigned DEFAULT NULL,
							  `order_number` char(32) DEFAULT NULL,
							  `zasilkovna_packet_id` decimal(10,0) DEFAULT NULL,
							  `zasilkovna_packet_price` decimal(15,2) DEFAULT NULL,
							  `branch_id` decimal(10,0) DEFAULT NULL,
							  `branch_currency` char(5) DEFAULT NULL,
							  `branch_name_street` varchar(500) DEFAULT NULL,
							  `is_carrier` smallint(1) NOT NULL DEFAULT '0',
							  `carrier_pickup_point` varchar(40) DEFAULT NULL,
							  `email` varchar(255) DEFAULT NULL,
							  `phone` varchar(255) DEFAULT NULL,
							  `first_name` varchar(255) DEFAULT NULL,
							  `last_name` varchar(255) DEFAULT NULL,
							  `address` varchar(255) DEFAULT NULL,
							  `city` varchar(255) DEFAULT NULL,
							  `zip_code` varchar(255) DEFAULT NULL,
							  `virtuemart_country_id` varchar(255) DEFAULT NULL,
							  `adult_content` smallint(1) DEFAULT '0',
							  `is_cod` smallint(1) DEFAULT NULL,
							  `packet_cod` decimal(15,2) DEFAULT '0',
							  `exported` smallint(1) DEFAULT NULL,
							  `printed_label` smallint(1) DEFAULT '0',
							  `shipment_name` varchar(5000) DEFAULT NULL,
							  `shipment_cost` decimal(10,2) DEFAULT NULL,
							  `shipment_package_fee` decimal(10,2) DEFAULT NULL,
							  `tax_id` smallint(1) DEFAULT NULL,
							  `created_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `created_by` int(11) NOT NULL DEFAULT '0',
							  `modified_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `modified_by` int(11) NOT NULL DEFAULT '0',
							  `locked_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `locked_by` int(11) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`id`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='zasilkovna';";
			$db->setQuery($q);
			$db->query();

			$q = "
INSERT INTO #__virtuemart_adminmenuentries (`module_id`, `parent_id`, `name`, `link`, `depends`, `icon_class`, `ordering`, `published`, `tooltip`, `view`, `task`) VALUES
							(5, 0, 'ZASILKOVNA', '', '', 'vmicon vmicon-16-zasilkovna', 1, 1, '', 'zasilkovna', '');";
			$db->setQuery($q);
			$db->query();

		}
	}

	/**
	 * Called on installation
	 *
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function install(JAdapterInstance $adapter) {

	}

	/**
	 * Called on update
	 *
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function update(JAdapterInstance $adapter) {
        // update of admin part of module
        $vm_admin_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart';
        $media_path = JPATH_ROOT . DS . 'media' . DS . 'com_zasilkovna' . DS;
        recurse_copy($media_path . 'admin' . DS . 'com_virtuemart' . DS, $vm_admin_path . DS);

        $files = scandir($media_path . 'admin' . DS);
        foreach ($files as $index => $filename){
            if ($this->endsWith($filename, '.ini')){
                $locale = explode('.',$filename)[0];
                if (file_exists(JPATH_ADMINISTRATOR . DS . 'language' . DS . $locale))
                {
                    recurse_copy($media_path . 'admin' . DS . $filename, JPATH_ADMINISTRATOR . DS . 'language' . DS . $locale . DS . $filename);
                }
            }
        }

        $this->migratePricingRules();
	}

    /**
     *  migrates price rules
     */
    private function migratePricingRules() {
        require_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/install/script.virtuemart.php';
        $vmInstall = new \com_virtuemartInstallerScript();
        $vmInstall->loadVm(false);

        require_once __DIR__ . '/zasilkovna.php';

        /** @var \VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $config = $model->loadConfig();
        if ($this->migratingPricingRules || empty($config) || isset($config['pricing_rules_migration_completed_at'])) {
            return;
        }

        $this->migratingPricingRules = true;
        echo 'Migrating pricing rules.<br>';

        /** @var \VirtueMartModelShipmentmethod $shipmentmethodModel */
        $shipmentmethodModel = VmModel::getModel('shipmentmethod');

        $globalWeightRules = [];
        $globalMaxWeight = $model->getConfig('global/values/maximum_weight', '');
        $globalShipmentCost = $model->getConfig('global/values/default_price', '');
        $globalFreeShipment = $model->getConfig('global/values/free_shipping', '');

        if (!is_numeric($globalShipmentCost)) {
            echo 'Global configuration not found. Migration stopped.<br>';

            $config = $model->loadConfig();
            $config['pricing_rules_migration_completed_at'] = (new \DateTime())->format(\DateTime::ISO8601);
            $model->updateConfig($config);
            return;
        }

        $pricingRules = [];
        $countries = ['cz', 'sk', 'hu', 'ro', 'pl'];
        $countriesWithOther = array_merge(['other'], $countries); // is used to search in config
        $countryIds = [];

        foreach ($countries as $country) {
            /** @var VmTable $countryObject */
            $countryObject = \VirtueMartModelCountry::getCountry(strtoupper($country), 'country_2_code');
            $countryIds[$country] = $countryObject->virtuemart_country_id;
        }

        $pricingRulesCount = 0;
        foreach ($countriesWithOther as $country) {
            $countryDefaultPrice = $model->getConfig($country. '/values/default_price', '');
            $countryFreeShipment = $model->getConfig($country. '/values/free_shipping', '');

            $countryWeightRules = ($model->getConfig($country) ?: []);
            unset($countryWeightRules['values']);

            usort($countryWeightRules, function ($countryWeightRulesA, $countryWeightRuleB) {
                if ($countryWeightRulesA['weight_from'] === $countryWeightRuleB['weight_from']) {
                    return 0;
                }

                return ($countryWeightRulesA['weight_from'] > $countryWeightRuleB['weight_from'] ? 1 : -1);
            });

            $lastCountryWeightRule = null;
            $countryWeightRulesTransformed = [];
            $weightRulesCount = 0;
            foreach ($countryWeightRules as $countryWeightRule) {

                $addWeightRule = function (&$countryWeightRulesTransformed) use ($countryWeightRule, $lastCountryWeightRule, &$weightRulesCount, $countryDefaultPrice, $globalShipmentCost) {
                    if ($lastCountryWeightRule === null && $countryWeightRule['weight_from'] > 0) {
                        $key = 'weightRules' . $weightRulesCount;
                        $countryWeightRulesTransformed[$key] = [
                            'maxWeightKg' => $countryWeightRule['weight_from'],
                            'price' => ($countryDefaultPrice ?: $globalShipmentCost),
                        ];
                        $weightRulesCount++;
                    }

                    if (!empty($countryWeightRule['weight_from']) && $lastCountryWeightRule && $lastCountryWeightRule['weight_to'] != $countryWeightRule['weight_from']) {
                        $key = 'weightRules' . $weightRulesCount;
                        $countryWeightRulesTransformed[$key] = [
                            'maxWeightKg' => $countryWeightRule['weight_from'],
                            'price' => ($countryDefaultPrice ?: $globalShipmentCost),
                        ];
                        $weightRulesCount++;
                    }

                    if (!empty($countryWeightRule['weight_to'])) {
                        $key = 'weightRules' . $weightRulesCount;
                        $countryWeightRulesTransformed[$key] = [
                            'maxWeightKg' => $countryWeightRule['weight_to'],
                            'price' => (($countryWeightRule['price'] ?: $countryDefaultPrice) ?: $globalShipmentCost),
                        ];
                        $weightRulesCount++;
                    }
                };

                if ($country === 'other') {
                    $addWeightRule($globalWeightRules);
                } else {
                    $addWeightRule($countryWeightRulesTransformed);
                }

                $lastCountryWeightRule = $countryWeightRule;
            }

            if (!is_numeric($countryDefaultPrice) && !is_numeric($countryFreeShipment) && empty($countryWeightRulesTransformed)) {
                continue; // no usable data to migrate
            }

            if ($country === 'other') {
                continue; // other country default price and free shipping will be lost
            }

            $pricingRules['pricingRules' . $pricingRulesCount] = [
                'country' => $countryIds[$country],
                'shipment_cost' => $countryDefaultPrice,
                'free_shipment' => $countryFreeShipment,
                'weightRules' => $countryWeightRulesTransformed
            ];

            $pricingRulesCount++;
        }

        $shipmentMethodIds = $model->getShipmentMethodIds();
        $model->publishShipmentMethods($shipmentMethodIds, 0);
        echo 'All packetery methods were unpublished. Please check them and publish them again.<br>';

        $packetaMethod = \VirtueMartModelZasilkovna\ShipmentMethod::fromRandom(
            [
                'globalWeightRules' => $globalWeightRules,
                'pricingRules' => $pricingRules
            ]
        );
        $sortedMethod = $packetaMethod->getResortedClone()->toArray();
        $globalWeightRules = $sortedMethod['globalWeightRules'];
        $pricingRules = $sortedMethod['pricingRules'];

        $pricingRulesEncoded = json_encode($pricingRules);
        $globalWeightRulesEncoded = json_encode($globalWeightRules);
        $params = [
            "maxWeight=\"$globalMaxWeight\"",
            "shipment_cost=\"$globalShipmentCost\"",
            "free_shipment=\"$globalFreeShipment\"",
            "globalWeightRules=$globalWeightRulesEncoded",
            "pricingRules=$pricingRulesEncoded"
        ];

        $data = [
            'shipment_name' => 'Packeta PP',
            'shipment_desc' => '',
            'shipment_element' => VirtueMartModelZasilkovna::PLG_NAME,
            'shipment_jplugin_id' => $model->getExtensionId(),
            'shipment_params' => implode('|', $params),
            'published' => 0,
        ];

        $table = $shipmentmethodModel->getTable('shipmentmethods');
        $table->bindChecknStore($data);

        $xrefTable = $shipmentmethodModel->getTable('shipmentmethod_shoppergroups');
        $xrefTable->bindChecknStore($data);

        $id = $data['virtuemart_shipmentmethod_id'];

        $db =& JFactory::getDBO();
        $q = "UPDATE #__virtuemart_shipmentmethods SET shipment_params='" . $data['shipment_params'] . "' WHERE virtuemart_shipmentmethod_id='{$id}'";
        $db->setQuery($q);
        $db->execute();

        echo 'New shipment method with migrated rules was created.<br>';

        $config = $model->loadConfig();
        $config['pricing_rules_migration_completed_at'] = (new \DateTime())->format(\DateTime::ISO8601);
        $model->updateConfig($config);

        $this->migratingPricingRules = false;
    }

	/**
	 * Called on uninstallation
	 *
	 * @param   JAdapterInstance $adapter The object responsible for running this script
	 */
	public function uninstall(JAdapterInstance $adapter) {
		$db =& JFactory::getDBO();
		$q = "DELETE FROM #__virtuemart_adminmenuentries WHERE `name` = 'zasilkovna';";
		$db->setQuery($q);
		$db->query();

		$this->removeAdministratorFiles();
	}

    private function removeAdministratorFiles() {
        $vm_admin_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart';
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna.php');
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna_orders.php');
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna_src' . DS);
        recurse_delete($vm_admin_path . DS . 'views' . DS . 'zasilkovna' . DS);
        recurse_delete($vm_admin_path . DS . 'controllers' . DS . 'zasilkovna.php');
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'en-GB' . DS . 'en-GB.plg_vmshipment_zasilkovna.ini');
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'cs-CZ' . DS . 'cs-CZ.plg_vmshipment_zasilkovna.ini');
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'sk-SK' . DS . 'sk-SK.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'pl-PL' . DS . 'pl-PL.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'hu-HU' . DS . 'hu-HU.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'ro-RO' . DS . 'ro-RO.plg_vmshipment_zasilkovna.ini', true);
    }

}

