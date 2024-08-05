/**
 *
 * @param controlCheckbox
 */
function zasilkovnaCheckAll(controlCheckbox) {
	var className = 'js-' + jQuery(controlCheckbox).attr('id');
	var isChecked = jQuery(controlCheckbox).is(':checked');
	jQuery('input.' + className).each(function () {
		if (jQuery(this).prop('disabled')) return;
		jQuery(this).prop('checked', isChecked);
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
				'<strong>' + Joomla.JText._('PLG_VMSHIPMENT_PACKETERY_CONFIG_VALIDATION_GLOBAL_ERROR') + '</strong> <br />'
				+ errorMessages.join('</br>')
			);
		}
		else
		{
			jQuery("#zasilkovna-messages")
				.html('<div class="alert alert-error">'
					+ '<strong>' + Joomla.JText._('PLG_VMSHIPMENT_PACKETERY_CONFIG_VALIDATION_GLOBAL_ERROR') + '</strong> <br />'
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
