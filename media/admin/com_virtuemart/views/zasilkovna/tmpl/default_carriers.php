<?php defined('_JEXEC') or die('Restricted access');
    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');
    $updateCarriersToken = $model->getConfig('cron_token');

    $params['task'] = 'updateCarriers';
    $params['option'] = 'com_virtuemart';
    $params['view'] = 'plugin';
    $params['type'] = 'vmshipment';
    $params['token'] = $updateCarriersToken;
    $params['name'] = VirtueMartModelZasilkovna::PLG_NAME;
    $publicUpdateUrl = JUri::root() . 'index.php?' . http_build_query($params);
    $updateUrl = Juri::base(true) . '/index.php?option=com_virtuemart&view=zasilkovna&task=updateCarriers';

    $lastUpdated = $model->getLastCarriersUpdateTimeFormatted();
    $carriersCount = $model->getTotalUsableCarriersCount();
?>
<div>
    <fieldset>
        <legend><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_LEGEND'); ?></legend>
        <table class="admintable">
            <tbody>
                <tr>
                    <td class="key"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_TOTAL'); ?></td>
                    <td><?php echo htmlentities($carriersCount); ?></td>
                </tr>
                <tr>
                    <td class="key"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_LAST_UPDATE'); ?></td>
                    <td><?php echo htmlentities($lastUpdated); ?></td>
                </tr>
                <tr>
                    <td class="key"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE'); ?></td>
                    <td><a class="btn btn-small btn-success" href="<?php echo htmlentities($updateUrl); ?>"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_BUTTON'); ?></a></td>
                </tr>
                <tr>
                    <td class="key"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE_URL'); ?></td>
                    <td><?php echo htmlentities($publicUpdateUrl); ?></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</div>
