<?php

use Joomla\CMS\Installer\Adapter\PluginAdapter;

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
 * @param string $dir Folder or file.
 * @param bool $ignore Ignores warnings for non existing folders and files.
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
    const VM_MENU_ENTRY = 'Packeta';
    const PLUGIN_ALIAS = 'com-zasilkovna';

    /**
     * @var string|null
     */
    private $fromVersion;

    public function __construct() {
        if (!defined('JPATH_VM_PLUGINS')) {
            if (!class_exists('VmConfig')) {
                require(JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php');
            }

            VmConfig::loadConfig();
        }

        if(!class_exists('GenericTableUpdater')) require(VMPATH_ADMIN .'/helpers/tableupdater.php');
    }

	/**
	 * Called before any type of action
	 *
	 * @param   string $route Which action is happening (install|uninstall|discover_install)
	 * @param   JAdapterInstance|PluginAdapter $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function preflight($route, $adapter) {
	    $this->fromVersion = $this->getExtensionVersion();

        if (in_array($route, ['install', 'update'])) {
            // Any schema migrations must not use plugin classes, because files are not migrated yet at this point.
            // If upgrade fails fromVersion does not change due to preflight. So it allows all schema migrations to be executed in case another install attempt happens.
            $this->upgradeSchema();
        }

        if ($route === 'update') {
            $media_path = JPATH_ROOT . DS . 'media' . DS . 'com_zasilkovna';
            recurse_delete($media_path);

            $this->removeAdministratorFiles();
        }
	}

    /**
     * @return string|null
     */
    public function getExtensionVersion()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true)
            ->select('manifest_cache')
            ->from('#__extensions AS e')
            ->where('e.element = ' . $db->quote('zasilkovna'))
            ->order($db->quoteName('extension_id') . ' DESC')
        ;

        $db->setQuery($query);
        $result = $db->loadResult();

        if ($result) {
            $cache = new \Joomla\Registry\Registry($result);
            return $cache->get('version');
        }

        return null;
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
     * @param   JAdapterInstance|PluginAdapter $adapter The object responsible for running this script
     *
     * @return  boolean  True on success
     */
    public function postflight($route, $adapter)
    {
        if ($route === 'uninstall') {
            return true;
        }

        $vm_admin_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart';
        $media_path = JPATH_ROOT . DS . 'media' . DS . 'com_zasilkovna' . DS;
        if ($route === "install") {
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

            $db = JFactory::getDBO();
            $q = sprintf("SELECT COUNT(*) FROM #__virtuemart_adminmenuentries WHERE `name` = '%s';", self::VM_MENU_ENTRY);
            $db->setQuery($q);
            $packetaEntryExists = (int) $db->loadResult();

            if ($packetaEntryExists === 0) {
                $q = sprintf("INSERT INTO #__virtuemart_adminmenuentries SET
                    `module_id` = 5,
                    `parent_id` = 0,
                    `name` = '%s',
                    `link` = '',
                    `depends` = '',
                    `icon_class` = 'vmicon vmicon-16-lorry',
                    `uikit_icon` = 'shipment',
                    `ordering` = 1,
                    `published` = 1,
                    `tooltip` = '',
                    `view` = 'zasilkovna',
                    `task` = '';",
                    self::VM_MENU_ENTRY);

                $db->setQuery($q);
                $db->execute();
            }

        }

        if (!class_exists('plgVmShipmentZasilkovna')) {
            require_once VMPATH_ROOT . '/plugins/vmshipment/zasilkovna/zasilkovna.php';
        }

        $this->createCronToken();
        if ($route === 'update' && $this->fromVersion && version_compare($this->fromVersion, '1.2.0', '<')) {
            $this->migratePricingRules();
        }

        $this->addBackendJoomlaMenuEntry();
	}

    /**
     * @return void
     */
    private function addBackendJoomlaMenuEntry()
    {
        if (version_compare(JVERSION, '4.0.0', '<')) {
            return;
        }

        JTable::addIncludePath(VMPATH_ROOT . '/administrator/components/com_menus/tables');
        JModelLegacy::addIncludePath(VMPATH_ROOT . '/administrator/components/com_menus/models', 'MenusModel');

        $db = JFactory::getDbo();
        $db->setQuery('SELECT extension_id FROM #__extensions WHERE `type` = "component" AND `element` = "com_virtuemart"');
        $virtueMartExtensionId = $db->loadResult();

        $parentId = $this->getJoomlaMenuItem('main', 'com-virtuemart', $virtueMartExtensionId, 1);
        if ($parentId === 0) {
            return;
        }

        $data = [];

        $data['menutype'] = 'main';
        $data['title'] = self::VM_MENU_ENTRY;
        $data['alias'] = self::PLUGIN_ALIAS;
        $data['path'] = 'com-zasilkovna';
        $data['link'] = 'index.php?option=com_virtuemart&view=zasilkovna';
        $data['img'] = '';
        $data['params'] = '{}';
        $data['note'] = '';
        $data['type'] = 'component';
        $data['published'] = 1;
        $data['parent_id'] = $parentId;
        $data['level'] = 2;
        $data['component_id'] = $virtueMartExtensionId;
        $data['checked_out'] = 0;
        $data['checked_out_time'] = '0000-00-00 00:00:00';
        $data['browserNav'] = 0;
        $data['access'] = 1;
        $data['template_style_id'] = 0;
        $data['home'] = 0;
        $data['language'] = '*';
        $data['client_id'] = 1;

        $menuModel = JModelLegacy::getInstance('Item', 'MenusModel', []);
        $data['id'] = $this->getJoomlaMenuItem($data['menutype'], $data['alias'], $data['component_id'],
            $data['client_id']);
        $menuModel->setState('item.id', $data['id']);
        $data['menuordering'] = $data['id'];
        $menuModel->save($data);

        $data['id'] = $this->getJoomlaMenuItem($data['menutype'], $data['alias'], $data['component_id'], 0);
        if ($data['id'] > 0) {
            $menuModel->setState('item.id', $data['id']);
            $menuModel->save($data);
        }
    }

    /**
     * @param string $menutType
     * @param string $alias
     * @param int $componentId
     * @param int $clientId
     *
     * @return int
     */
    private function getJoomlaMenuItem($menutType, $alias, $componentId, $clientId)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('menutype') . ' = :menutype',
                $db->quoteName('alias') . ' = :alias',
                $db->quoteName('component_id') . ' = :component_id',
                $db->quoteName('client_id') . ' = :client_id',
                $db->quoteName('published') . ' = 1',
            ])
            ->bind(':menutype', $menutType)
            ->bind(':alias', $alias)
            ->bind(':component_id', $componentId)
            ->bind(':client_id', $clientId);

        $db->setQuery($query);

        return (int)$db->loadResult();
    }

    /**
     * @param string $tableLike
     * @return bool
     */
    public function pluginTableExists($tableLike) {
        $db = JFactory::getDBO();
        $db->setQuery('SHOW TABLES LIKE ' . $db->quote($tableLike));
        $row = $db->loadColumn();
        return !empty($row);
    }

    public function upgradeSchema() {
        $oldColumns = [];
        if ($this->pluginTableExists('%_virtuemart_shipment_plg_zasilkovna')) {
            $db = JFactory::getDBO();
            $db->setQuery('SHOW FULL COLUMNS FROM `#__virtuemart_shipment_plg_zasilkovna`');
            $oldColumns = $db->loadColumn();
        }

        if ($this->pluginTableExists('%_virtuemart_zasilkovna_branches')) {
            $db = JFactory::getDBO();
            $db->setQuery('DROP TABLE `#__virtuemart_zasilkovna_branches`');
            $db->execute();
        }

        $updater = new GenericTableUpdater();
        $updater->updateMyVmTables(__DIR__ . '/install.sql');
        $updater->updateMyVmTables(__DIR__ . '/install.sql'); // tables are created with MyISAM engine, to use InnoDB, we call the method for second time
        echo 'Database schema installed/upgraded.<br>';

        // If user uninstalls plugin version 1.1.7 the tables with data will likely still be there.
        // Then when user installs 1.3.1 the fromVersion variable will be empty.

        if (!empty($oldColumns) && !in_array('packet_cod', $oldColumns)) {
            $db = JFactory::getDBO();
            $db->setQuery('UPDATE `#__virtuemart_shipment_plg_zasilkovna` SET packet_cod = zasilkovna_packet_price WHERE is_cod = 1 AND packet_cod = 0.00');
            $db->execute();
            echo 'Column packet_cod was filled with zasilkovna_packet_price.<br>';
        }
	}

	/**
	 * Called on installation
	 *
	 * @param   JAdapterInstance|PluginAdapter $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function install($adapter) {
	}

	/**
	 * Called on update
	 *
	 * @param   JAdapterInstance|PluginAdapter $adapter The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function update($adapter) {
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
	}

    /**
     *  migrates price rules
     */
    private function migratePricingRules() {
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

        $db = JFactory::getDBO();
        $q = "UPDATE #__virtuemart_shipmentmethods SET shipment_params='" . $db->escape($data['shipment_params']) . "' WHERE virtuemart_shipmentmethod_id=" . (int)$id;
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
     * @param   JAdapterInstance|PluginAdapter $adapter The object responsible for running this script
     */
    public function uninstall($adapter) {
        $db = JFactory::getDBO();

        // Table dropping was added in 1.3.1. Before that tables existed after plugin uninstall.
        $db->setQuery("DROP TABLE IF EXISTS #__virtuemart_shipment_plg_zasilkovna_backup;");
        $db->execute();

        // Beware of database constraints. You may want to turn off foreign key constraint check before dropping related tables.
        $db->setQuery("RENAME TABLE #__virtuemart_shipment_plg_zasilkovna TO #__virtuemart_shipment_plg_zasilkovna_backup;");
        $db->execute();

        $db->setQuery("DROP TABLE IF EXISTS #__virtuemart_zasilkovna_carriers;");
        $db->execute();

        $this->removeAdministratorFiles();
        $this->removeBackendMenuEntries();
    }

    private function removeAdministratorFiles() {
        $vm_admin_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart';
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna.php');
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna_orders.php');
        recurse_delete($vm_admin_path . DS . 'models' . DS . 'zasilkovna_src' . DS);
        recurse_delete($vm_admin_path . DS . 'views' . DS . 'zasilkovna' . DS);
        recurse_delete($vm_admin_path . DS . 'controllers' . DS . 'zasilkovna.php');
        recurse_delete($vm_admin_path . DS . 'fields' . DS . 'vmzasilkovnacountries.php');
        recurse_delete($vm_admin_path . DS . 'fields' . DS . 'vmzasilkovnahdcarriers.php');
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'en-GB' . DS . 'en-GB.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'cs-CZ' . DS . 'cs-CZ.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'sk-SK' . DS . 'sk-SK.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'pl-PL' . DS . 'pl-PL.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'hu-HU' . DS . 'hu-HU.plg_vmshipment_zasilkovna.ini', true);
        recurse_delete(JPATH_ADMINISTRATOR . DS . 'language' . DS . 'ro-RO' . DS . 'ro-RO.plg_vmshipment_zasilkovna.ini', true);
    }

    /**
     * Creates update carriers token.
     *
     * @return void
     */
    private function createCronToken() {
        /** @var \VirtueMartModelZasilkovna $model */
        $model = VmModel::getModel('zasilkovna');
        $config = $model->loadConfig();

        if (!isset($config['cron_token'])) {
            $config['cron_token'] = substr(sha1(rand()), 0, 16);
        }

        $model->updateConfig($config);
    }

    /**
     * @return void
     */
    private function removeBackendMenuEntries()
    {
        $db = JFactory::getDbo();
        $q = sprintf("DELETE FROM #__virtuemart_adminmenuentries WHERE `name` = '%s';", self::VM_MENU_ENTRY);
        $db->setQuery($q);
        $db->execute();

        if (version_compare(JVERSION, '4.0.0', '<')) {
            return;
        }

        $alias = self::PLUGIN_ALIAS;
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('alias') . ' = :alias',
                $db->quoteName('client_id') . ' = 1',
            ])
            ->bind(':alias', $alias);

        $db->setQuery($query);
        $db->execute();
    }
}

