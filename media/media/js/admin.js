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

	// check of global settings values
	var isGlobalSettingInvalid = false;
	var globalMaxWeightInput = jQuery(".global-default-rules [data-name='maximum_weight']");
	var globalDefaultShippingPriceInput = jQuery(".global-default-rules [data-name='default_price']");
	var globalFreeShippingLimitInput = jQuery(".global-default-rules [data-name='free_shipping']");

	var globalMaxWeightVal = globalMaxWeightInput.val().replace(',', '.');
	var globalDefaultShippingPriceVal = globalDefaultShippingPriceInput.val().replace(',', '.');
	var globalFreeShippingLimitVal = globalFreeShippingLimitInput.val().replace(',', '.');

	if (!jQuery.isNumeric(globalMaxWeightVal)) // maximal weight is required value
	{
		globalMaxWeightInput.addClass('invalid');
		isGlobalSettingInvalid = true;
	}

	if (globalDefaultShippingPriceVal !== '' && !jQuery.isNumeric(globalDefaultShippingPriceVal))
	{
		globalDefaultShippingPriceInput.addClass('invalid');
		isGlobalSettingInvalid = true;
	}

	if (globalFreeShippingLimitVal !== '' && !jQuery.isNumeric(globalFreeShippingLimitVal))
	{
		globalFreeShippingLimitInput.addClass('invalid');
		isGlobalSettingInvalid = true;
	}

	// check result of embedded base check of form
	if (!isFormValid && jQuery('[id^="global[values]"]').hasClass('invalid'))
	{
		isGlobalSettingInvalid = true;
	}

	if (isGlobalSettingInvalid)
	{
		errorMessages.push(Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_EMPTY'));
		isFormValid = false;
	}

	// Frontend validation for "Weighting rules" range.
	jQuery(supportedCountries).each(function (index, countryCode)
	{
		var countryRulesSelector = '.validate-' + countryCode + '-ranges';
		var countryRules = Array();
		var isInvalidRule = false;

		// check default shipping price and free shipping limit
		var defaultShippingPriceInput = jQuery(countryRulesSelector).find("[data-name='default_price']");
		var freeShippingLimitInput = jQuery(countryRulesSelector).find("[data-name='free_shipping']");

		var defaultShippingPriceVal = defaultShippingPriceInput.val().replace(',', '.');
		var freeShippingLimitVal = freeShippingLimitInput.val().replace(',', '.');

		if (defaultShippingPriceVal !== '' && !jQuery.isNumeric(defaultShippingPriceVal))
		{
			defaultShippingPriceInput.addClass('invalid');
			isInvalidRule = true;
		}

		if (freeShippingLimitVal !== '' && !jQuery.isNumeric(freeShippingLimitVal))
		{
			freeShippingLimitInput.addClass('invalid');
			isInvalidRule = true;
		}

		// check content of rules for country
		jQuery(countryRulesSelector)
			.find('.items')
			.each(function ()
			{
				var $weightFromInput = jQuery(this).find("[data-name='weight_from']");
				var $weightToInput = jQuery(this).find("[data-name='weight_to']");

				var weightFromVal = $weightFromInput.val().replace(',', '.');
				var weightToVal = $weightToInput.val().replace(',', '.');

				// Check if values are correct or empty
				if ((weightFromVal !== '' && !jQuery.isNumeric(weightFromVal)) || (weightToVal !== '' && !jQuery.isNumeric(weightToVal))
					|| (weightFromVal === '' && weightToVal === ''))
				{
					$weightFromInput.addClass('invalid');
					$weightToInput.addClass('invalid');
					isInvalidRule = true;
				}

				// Parse weight as floating point number.
				var weightFrom = parseFloat(weightFromVal);
				var weightTo = parseFloat(weightToVal);

				// From must be > than to.
				if (weightFrom > weightTo)
				{
					$weightFromInput.addClass('invalid');
					$weightToInput.addClass('invalid');

					errorMessages.push(Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_GLOBAL_INVALID_RANGE'));
					isFormValid = false;
				}

				// Check if "Weighting rules" range is colliding.
				var isRangeColliding = false;

				jQuery.each(countryRules, function (index, range)
				{
					if (weightFrom < range.weightTo && weightTo > range.weightFrom)
					{
						isRangeColliding = true;
					}
				});

				// Push weight range to array.
				countryRules.push({'weightFrom': weightFrom, 'weightTo': weightTo});

				// Weight range is colliding.
				if (isRangeColliding)
				{
					$weightFromInput.addClass('invalid');
					$weightToInput.addClass('invalid');

					errorMessages.push(Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_' + countryCode.toUpperCase() + '_OVERLAP'));
					isFormValid = false;
				}
			});

		// Some rule has invalid value or required part of rule is empty
		if (isInvalidRule)
		{
			errorMessages.push(Joomla.JText._('PLG_VMSHIPMENT_ZASILKOVNA_CONFIG_VALIDATION_' + countryCode.toUpperCase() + '_EMPTY'));
			isFormValid = false;
		}

	});

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

jQuery(document).ready(function()
{
	jQuery(supportedCountries).each(function(index, item)
	{
		jQuery('.'+item+'_repeater').createRepeater({
			showFirstItemToDefault: (jQuery('.'+item+'_repeater .item-empty').length)?false:true
		});
	});
});
