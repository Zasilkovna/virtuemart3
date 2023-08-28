<?php

use VirtueMartModelZasilkovna\Box\Renderer;
use VirtueMartModelZasilkovna\Order\Detail;

defined('_JEXEC') || die('Restricted access');

/** @var VirtueMartModelZasilkovna $model */
$model = VmModel::getModel('zasilkovna');

$renderer = new Renderer();
$renderer->setTemplate(Detail::TEMPLATES_DIR . DS . 'card.php');

$params['task'] = 'updateCarriers';
$params['option'] = 'com_virtuemart';
$params['view'] = 'plugin';
$params['type'] = 'vmshipment';
$params['token'] = $model->getConfig('cron_token');
$params['name'] = VirtueMartModelZasilkovna::PLG_NAME;
$publicUpdateUrl = JUri::root() . 'index.php?' . http_build_query($params);
$updateUrl = Juri::base(true) . '/index.php?option=com_virtuemart&view=zasilkovna&task=updateCarriers';

$lastUpdated = $model->getLastCarriersUpdateTimeFormatted();
$carriersCount = $model->getTotalUsableCarriersCount();

ob_start();
?>
    <table class="admintable">
        <tbody>
        <tr>
            <th class="key">
                <label><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_TOTAL'); ?></label>
            </th>
            <td><?php echo htmlentities($carriersCount); ?></td>
        </tr>
        <tr>
            <th class="key">
                <label><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_LAST_UPDATE'); ?></label>
            </th>
            <td><?php echo htmlentities($lastUpdated); ?></td>
        </tr>
        <tr>
            <th class="key">
                <label><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE'); ?></label>
            </th>
            <td><a class="btn btn-small btn-success"
                   href="<?php echo htmlentities($updateUrl); ?>"><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_BUTTON'); ?></a>
            </td>
        </tr>
        <tr>
            <th class="key">
                <label><?php echo JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_MANUAL_UPDATE_URL'); ?></label>
            </th>
            <td><?php echo htmlentities($publicUpdateUrl); ?></td>
        </tr>
        </tbody>
    </table>
<?php
$content = ob_get_clean();

$renderer->setVariables([
    'title' => JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_UPDATE_LEGEND'),
    'icon' => 'shipment',
    'content' => $content,
]);
echo $renderer->renderToString();
