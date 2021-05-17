// loop all shipment options and toggle zasilkovna box visibility
function toggleZasilkovnaBox(){
	jQuery('input[name="virtuemart_shipmentmethod_id"]').each(function(){
		var $target = jQuery(this);
		var $box = $target.closest(".zasilkovna_box");
		if($box.length === 0) {
			return;
		}

		if($target.is(':checked'))
			$box.find(".zas-box").show();
		else
			$box.find(".zas-box").hide();
	});
}

function getSelectedPacketeryBox() {
	return jQuery("input[name=virtuemart_shipmentmethod_id]:checked").closest('.zasilkovna_box');
}

function isPacketeryShippingSelected() {
	var zasilkovnaBox = getSelectedPacketeryBox();
	return zasilkovnaBox.length === 1;
}

function isPacketeryShippingPointSelected() {
	var selectedPoint = getSelectedPacketeryBox().find('.picked-delivery-place');
	return !!selectedPoint.text();
}

jQuery(function() {
	jQuery('body').off('click.packeteryOpenWidget').on('click.packeteryOpenWidget', '.zasilkovna_box .open-packeta-widget', function () {
		var packetery = window.packetery;

		var opts = {
			appIdentity: packetery.version,
			country: packetery.country,
			language: packetery.language
		};

		Packeta.Widget.pick(packetery.apiKey, function(pickupPoint){
			if (pickupPoint === null)
				return;
			if (!pickupPoint.id)
				return;

			jQuery(".picked-delivery-place").html(pickupPoint.nameStreet + ", " + pickupPoint.zip);

			Virtuemart.startVmLoading({data: {msg: ''}});
			jQuery.ajax({
				type: "POST",
				url: packetery.savePickupPointUrl,
				data: {
					branch_id: pickupPoint.id,
					branch_currency: pickupPoint.currency,
					branch_name_street: pickupPoint.nameStreet + ", " + pickupPoint.zip,
					branch_country: pickupPoint.country,
					branch_carrier_id: pickupPoint.carrierId ? pickupPoint.carrierId : '',
					branch_carrier_pickup_point: pickupPoint.carrierPickupPointId ? pickupPoint.carrierPickupPointId : '',
				},
				complete: function() {
					Virtuemart.stopVmLoading();
				},
			});

		}, opts);
	}).on('change', 'input[name=virtuemart_shipmentmethod_id]', function(e) {
		toggleZasilkovnaBox();
	});
});
