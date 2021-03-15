/**
 *
 * @param mainCb
 */
function zasilkovnaCheckAll(mainCb) {
	var id = jQuery(mainCb).attr('id');
	jQuery('input#' + id).each(function(index) {
		if (this == mainCb)return;
		if (jQuery(this).attr('disabled')) return;
		if (jQuery(mainCb).attr('checked')) {
			jQuery(this).attr('checked', true);
		} else {
			jQuery(this).attr('checked', false);
		}
	});
}

// Validate "Weighting rules" form.
// https://docs.joomla.org/Form_validation
// https://docs.joomla.org/Client-side_form_validation
function validateForm()
{
	var errorMessages = Array();
	var isFormValid = document.formvalidator.isValid(document.adminForm);

	if (!isFormValid)
	{
		// Append the error messages into the error message bar.
		var $standardErrorMessage = jQuery('#system-message-container .alert-error');

		if ($standardErrorMessage.length > 0)
		{
			$standardErrorMessage.html(
				'<strong>' + Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_ERROR') + '</strong> <br />'
				+ errorMessages.join('</br>')
			);
		}
		else
		{
			jQuery("#zasilkovna-messages")
				.html('<div class="alert alert-error">'
					+ '<strong>' + Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_ERROR') + '</strong> <br />'
					+ errorMessages.join('</br>')
					+ '</div>');
		}

		return false;
	}

	Joomla.submitbutton('apply');

	return true;
}

/** Helper function for selectbox filter including reset of last task name.
 * This reset is required to prevent the previous action from being repeated.
 *
 * @param form reference to native HTML form object
 */
function resetTaskAndSubmitForm(form)
{
	jQuery('form[name=adminForm] input[type=hidden][name=task]').val('');
	form.submit();
}
