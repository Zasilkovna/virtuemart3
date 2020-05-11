<?php defined('_JEXEC') or die ('Restricted access');

    // INCLUDE JOOMLA FORMVALIDATOR
    JHtml::_('behavior.formvalidator');

    AdminUIHelper::startAdminArea($this);

    /** @var VirtueMartModelZasilkovna $model */
    $model = VmModel::getModel('zasilkovna');
    $document = JFactory::getDocument();

    $model->loadLanguage();

    // PASS COUNTRY LIST TO JAVASCRIPT
    $countries = [];
    foreach ($model->getCountries(true) as $key => $value)
        $countries[] = "'".$key."'";

    $document->addScriptDeclaration("
        var supportedCountries = [".implode(',', $countries)."];
    ");


    // INCLUDE JS AND CSS
    $document->addStyleSheet(JUri::root().'media/com_zasilkovna/media/css/admin.css?v=' . filemtime(__DIR__ . '/../../../media/com_zasilkovna/media/css/admin.css'));
    $document->addScript(JUri::root()."media/com_zasilkovna/media/js/repeater.js?v=" . filemtime(__DIR__ . '/../../../media/com_zasilkovna/media/js/repeater.js'));
    $document->addScript(JUri::root()."media/com_zasilkovna/media/js/admin.js?v=" . filemtime(__DIR__ . '/../../../media/com_zasilkovna/media/js/admin.js'));
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <?php // Loading Templates in Tabs
        AdminUIHelper::buildTabs($this, array(
            'export' => JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDERS_TAB'),
            'config' => JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_TAB'),
        ));
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
    AdminUIHelper::endAdminArea();
?>
<script>
    jQuery("a.toolbar").each(function() {
        var onClickStr = $(this).attr("onclick");
        if (onClickStr.indexOf('printLabels') >= 0 || onClickStr.indexOf('updateAndExportZasilkovnaOrders') >= 0) {
            $(this).click(function() {
                window.setTimeout(function() {
                    document.adminForm.task.value = "";
                }, 1000);
            })
        }
    });
</script>
