var packetaWidgetInitialized = false;

window.initializePacketaWidget = function(){

	if (packetaWidgetInitialized) {
		return;
	}
	// we define id of packeta widget iframe
	var idPacketaWidget = 'packeta-widget';

	inElement = document.getElementById(idPacketaWidget);

	opts = {
		appIdentity: version,
		country: country,
		language: language,
		address: address,
		version: 4
	};

	document.getElementById('open-packeta-widget').addEventListener('click', function (e) {
		e.preventDefault();
		Packeta.Widget.pick(packetaApiKey, function(pickupPoint){
			if (pickupPoint === null)
				return;
			if (!pickupPoint.id)
				return;

			jQuery("#branch_id").val(pickupPoint.id);
			jQuery("#branch_currency").val(pickupPoint.currency);
			jQuery("#branch_name_street").val(pickupPoint.nameStreet + ", " + pickupPoint.zip);
			jQuery("#branch_country").val(pickupPoint.country);

			jQuery("#picked-delivery-place").html(pickupPoint.nameStreet + ", " + pickupPoint.zip);
			toggleShipmentSaveButton();

			// we let customer know, which branch he picked by filling html inputs
			jQuery("#branch_name_street").html(pickupPoint.name);

			Virtuemart.updForm();
			jQuery("#checkoutForm").submit();

		}, opts);
	});
	packetaWidgetInitialized = true;
};

// togle zasilkovna box visibility

if (jQuery('#shipmentForm').length){
	jQuery(window).load(function(){
		toggleZasilkovnaBox();
	});
} else {
	toggleZasilkovnaBox();
}



// handle shipment change (wait for window load event

jQuery(window).load(function(){

	jQuery('.vm-shipment-plugin-single input[name="virtuemart_shipmentmethod_id"]:radio').click(function (e) {
		if(jQuery(this).closest(".zasilkovna_box").length){
			jQuery(".zas-box").css("display", "block");
			initializePacketaWidget();
		} else {
			jQuery(".zas-box").css("display", "none");
		}
		toggleShipmentSaveButton();
	});
});

// loop all shipment options and toggle zasilkovna box visibility

function toggleZasilkovnaBox(){
	if(countrySelected !== ''){
		jQuery(function(){
			jQuery('input[name="virtuemart_shipmentmethod_id"]:radio').each(function(){
				if(jQuery(this).closest(".zasilkovna_box").length)
					if(jQuery(this).is(':checked'))
						jQuery(".zas-box").css("display", "block");
					else
						jQuery(".zas-box").css("display", "none");
			});
			initializePacketaWidget();
	
			jQuery("#checkoutFormSubmit").on('submit', function(e){
				e.preventDefault();
			});
	
		});
	}else{
			jQuery(".zas-box").css("display", "none");
	}
	toggleShipmentSaveButton();
}

// handle "save" butotn in shipment step

function toggleShipmentSaveButton(){
	if (jQuery('#shipmentForm').length){
		if (jQuery('#shipment_id_2').is(':checked') && !jQuery('#picked-delivery-place').html()){
			jQuery('#shipmentForm button[name="updatecart"]').css("display", "none");
		} else {
			jQuery('#shipmentForm button[name="updatecart"]').css("display", "inline-block");
		}
	}
}

// ***************************************************


jQuery(function(){
	setTimeout(function(){
		Virtuemart.bCheckoutButton = function(e) {
			e.preventDefault();
			// If shipping method is Zasilkovna
			if( jQuery("#zasilkovna_div > input[type='radio']").is(":checked")){
				// Branch must be selected
				if((jQuery("#branch_id").val() != 0)){
					// And user has to agree to the Terms of Service
					if(jQuery("#tos").is(":checked")){
						jQuery(this).vm2front("startVmLoading");
						jQuery(this).attr('disabled', "true");
						jQuery(this).removeClass( "vm-button-correct" );
						jQuery(this).addClass( "vm-button" );
						jQuery(this).fadeIn( 400 );
						var name = jQuery(this).attr("name");
						var div = '<input name="'+name+'" value="1" type="hidden">';
						jQuery("#checkoutForm").append(div);
						jQuery("#checkoutForm").submit();
					}else{
						jQuery(".vm-fieldset-tos").css("background-color","#FFAAAA");
					}
				}else{
					jQuery(".zas-box").css("background-color","#FFAAAA");
				}
			}
			else
			{
				// Shipping method is not Zasilkovna
				shippingSelected = false;
				jQuery(".vm-shipment-plugin-single input[id^=shipment_id]").each(function(index, element){
					if(jQuery(element).is(":checked")){
						shippingSelected = true;
					}
				});
				// If shipping method is selected
				if(shippingSelected)
				{
					// User has to agree to the Terms of Service
					if(jQuery("#tos").is(":checked")){
						var name = jQuery(this).attr("name");
						var div = '<input name="'+name+'" value="1" type="hidden">';
						jQuery("#checkoutForm").append(div);
						jQuery("#checkoutForm").submit();
					}else{
						jQuery(".vm-fieldset-tos").css("background-color","#FFAAAA");
					}

				}else{
					jQuery(".vm-shipment-select").css("background-color","#FFAAAA");
				}
			}
		};
		Virtuemart.stopVmLoading();
		var el = jQuery("#checkoutFormSubmit");
		el.unbind("click dblclick");
		el.on("click dblclick",Virtuemart.bCheckoutButton);
	}, 100);

});