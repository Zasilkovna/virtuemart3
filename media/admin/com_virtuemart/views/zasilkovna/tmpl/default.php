<?php
/**
 *
 * Description
 *
 * @package	VirtueMart
 * @subpackage Config
 * @author RickG
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id: default.php 6053 2012-06-05 12:36:21Z Milbo $
 */

// Check to ensure this file is included in Joomla!
defined ( '_JEXEC' ) or die ( 'Restricted access' );
AdminUIHelper::startAdminArea($this);
$zas_model=VmModel::getModel('zasilkovna');
$zas_model->loadLanguage();

?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

<?php // Loading Templates in Tabs


AdminUIHelper::buildTabs ( $this,  array (
									'config' 			=> 	JText::_('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_TAB'),
									'export' 			=> 	JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDERS_TAB'),
                                    //'export_other' 			=> 	JText::_('PLG_VMSHIPMENT_ZASILKOVNA_ORDERS_TAB').' - ostatní'
									));

?>

<!-- Hidden Fields --> <input type="hidden" name="task" value="" /> <input
	type="hidden" name="option" value="com_virtuemart" /> <input
	type="hidden" name="view" value="zasilkovna" />
<?php
echo JHTML::_ ( 'form.token' );
?>
</form>
<?php

AdminUIHelper::endAdminArea ();


?>
<script>
$("a.toolbar").each(function(){
	var onClickStr = $(this).attr("onclick");
	if(onClickStr.indexOf('printLabels') >=0 || onClickStr.indexOf('updateAndExportZasilkovnaOrders') >= 0){
		$(this).click(function(){
			window.setTimeout(function(){
				document.adminForm.task.value="";
			}, 1000);			
		})
	}
});
</script>