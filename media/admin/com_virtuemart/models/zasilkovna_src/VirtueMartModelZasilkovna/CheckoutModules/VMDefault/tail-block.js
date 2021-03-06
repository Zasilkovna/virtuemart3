// TOS and Packeta point validation works in OPC from VM3
jQuery(function(){
    setTimeout(function(){
        Virtuemart.bCheckoutButton = function(e) {
            e.preventDefault();
            // If shipping method is Zasilkovna
            if(isPacketeryShippingSelected()){
                // Branch must be selected
                if(isPacketeryShippingPointSelected()){
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
