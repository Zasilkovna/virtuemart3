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
    $publicUpdateUrl = JUri::root() . 'index.php?' . http_build_query($params);
    $updateUrl = Juri::base(true) . '/index.php?option=com_virtuemart&view=zasilkovna&task=updateCarriers';

    $lastUpdated = $model->getLastCarriersUpdateTimeFormatted();
    $carriersCount = $model->getTotalUsableCarriersCount();
?>
<div>
    <p><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_TOTAL'); ?>: <?php echo htmlentities($carriersCount); ?></p>
    <p><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_LAST_UPDATE'); ?>: <?php echo htmlentities($lastUpdated); ?></p>
    <p>
        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE'); ?>:
        <a class="btn btn-small" href="<?php echo htmlentities($updateUrl); ?>"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_BUTTON'); ?></a>
    </p>
    <p>
        <?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE_URL'); ?>: <?php echo htmlentities($publicUpdateUrl); ?>
    </p>
</div>
