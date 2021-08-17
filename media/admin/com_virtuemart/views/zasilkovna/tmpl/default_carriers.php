<?php defined('_JEXEC') or die('Restricted access');
    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');
    $updateCarriersToken = $model->getConfig('update_carriers_token');

    $params['task'] = 'updateCarriers';
    $params['option'] = 'com_virtuemart';
    $params['view'] = 'plugin';
    $params['type'] = 'vmshipment';
    $params['token'] = $updateCarriersToken;
    $params['name'] = VirtueMartModelZasilkovna::PLG_NAME;
    $publicUpdateUrl = '/index.php?' . http_build_query($params);
    $updateUrl = 'index.php?option=com_virtuemart&view=zasilkovna&task=updateCarriers';

    $lastUpdated = $model->getLastCarriersUpdateTimeFormatted();
    $carriersCount = $model->getTotalUsableCarriersCount();
?>
<div id="packetery-config-carriers-tab">
    <table>
        <tbody>
        <tr>
            <td><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_TOTAL'); ?>: <?php echo $carriersCount; ?></td>
        </tr>
        <tr>
            <td><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_LAST_UPDATE'); ?>: <?php echo $lastUpdated; ?></td>
        </tr>
        <tr>
            <td>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE'); ?>:
                <a href="<?php echo $updateUrl; ?>"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_BUTTON'); ?></a>
            </td>
        </tr>
        <tr>
            <td>
                <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE_URL'); ?>:
                <a href="<?php echo $publicUpdateUrl; ?>"><?php echo $publicUpdateUrl; ?></a>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<style>
    #packetery-config-carriers-tab {

    }
</style>
