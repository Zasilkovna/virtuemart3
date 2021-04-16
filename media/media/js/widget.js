var packetaWidgetInitialized = false;

window.initializePacketaWidget = function(){

	if (packetaWidgetInitialized) {
		return;
	}

	opts = {
		appIdentity: version,
		country: country,
		language: language
	};

	jQuery('.zasilkovna_box').on('click', '#open-packeta-widget', function (e) {
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
			jQuery("#branch_carrier_id").val(pickupPoint.carrierId ? pickupPoint.carrierId : '');
			jQuery("#branch_carrier_pickup_point").val(pickupPoint.carrierPickupPointId ? pickupPoint.carrierPickupPointId : '');

			jQuery("#picked-delivery-place").html(pickupPoint.nameStreet + ", " + pickupPoint.zip);

			// we let customer know, which branch he picked by filling html inputs
			jQuery("#branch_name_street").html(pickupPoint.name);

			Virtuemart.updForm();
			jQuery("#checkoutForm").submit();

		}, opts);
	});
	packetaWidgetInitialized = true;
};

// togle zasilkovna box visibility
jQuery(document).ready(function() {
	toggleZasilkovnaBox();
});

// loop all shipment options and toggle zasilkovna box visibility

function toggleZasilkovnaBox(){
	if(countrySelected !== ''){
		jQuery(function(){
			jQuery('input[name="virtuemart_shipmentmethod_id"]:radio').each(function(){
				var $target = jQuery(this);
				var $box = $target.closest(".zasilkovna_box");
				if($box.length === 0) {
					return;
				}

				if($target.is(':checked'))
					$box.find(".zas-box").css("display", "block");
				else
					$box.find(".zas-box").css("display", "none");
			});
			initializePacketaWidget();

			jQuery("#checkoutFormSubmit").on('submit', function(e){
				e.preventDefault();
			});
		});
	}else{
		jQuery(".zas-box").css("display", "none");
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
