jQuery(document).ready(function($) {
    $('input[name="params[delivery_settings][shipping_type]"]').change(function() {
        let value = $(this).val();
        if (value === 'hdcarriers') {
            // remove attribute checked from all checkboxes in fieldset #params_delivery_settings__vendor_groups
            $('#params_delivery_settings__vendor_groups input[type="checkbox"]').removeAttr('checked');
        }
    });
});
