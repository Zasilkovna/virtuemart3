<?php defined('_JEXEC') or die ('Restricted access');

    // INCLUDE JOOMLA FORMVALIDATOR
    JHtml::_('behavior.formvalidator');

    $adminTemplate = VMPATH_ROOT . '/administrator/templates/vmadmin/html/com_virtuemart/';
    JLoader::register('vmuikitAdminUIHelper', $adminTemplate . 'helpers/vmuikit_adminuihelper.php');
    if (version_compare(JVERSION, '4.0.0', '>=')) {
        vmuikitAdminUIHelper::startAdminArea($this);
    } else {
        AdminUIHelper::startAdminArea($this);
    }

    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');
    $document = JFactory::getDocument();

    $model->loadLanguage();

    // INCLUDE JS AND CSS
    $document->addStyleSheet(JUri::root().'media/com_zasilkovna/media/css/admin.css?v=' . filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/css/admin.css'));

    $document->addScript(JUri::root()."media/com_zasilkovna/media/js/repeater.js?v=" . filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/js/repeater.js'));
    $document->addScript(JUri::root()."media/com_zasilkovna/media/js/admin.js?v=" . filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/js/admin.js'));
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <?php // Loading Templates in Tabs
    $tabs = [
        'export' => JText::_('PLG_VMSHIPMENT_PACKETERY_ORDERS_TAB'),
        'config' => JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_TAB'),
        'carriers' => JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_TAB'),
    ];
    if (version_compare(JVERSION, '4.0.0', '>=')) {
        vmuikitAdminUIHelper::buildTabs($this, $tabs);
    } else {
        AdminUIHelper::buildTabs($this, $tabs);
    }
    ?>
    <!-- Hidden Fields -->
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="option" value="com_virtuemart" />
    <input type="hidden" name="view" value="zasilkovna" />
    <?php
        echo JHTML::_('form.token');
    ?>
</form>
<?php
if (version_compare(JVERSION, '4.0.0', '>=')) {
    vmuikitAdminUIHelper::endAdminArea();
} else {
    AdminUIHelper::endAdminArea();
}
?>
<script>
    if (jQuery('joomla-toolbar-button'.length > 0))
        jQuery('joomla-toolbar-button').each(function() {
            let taskValue = jQuery(this).attr('task');
            if (taskValue && (taskValue.indexOf('printLabels') >= 0 || taskValue.indexOf('updateAndExportZasilkovnaOrders') >= 0)) {
                jQuery(this).click(function() {
                    window.setTimeout(function() {
                        document.adminForm.task.value = "";
                    }, 1000);
                })
            }
        });
    } else if(jQuery('a.toolbar'.length > 0)) { // for backward compatibility
        jQuery('a.toolbar').each(function() {
            var onClickStr = $(this).attr("onclick");
            if (onClickStr.indexOf('printLabels') >= 0 || onClickStr.indexOf('updateAndExportZasilkovnaOrders') >= 0) {
                $(this).click(function() {
                    window.setTimeout(function() {
                        document.adminForm.task.value = "";
                    }, 1000);
                })
            }
        });
    }
</script>
