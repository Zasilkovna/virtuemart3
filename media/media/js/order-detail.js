function cancelPacketeryUpdateOrderDetail(event) {
    jQuery('#packeteryUpdateOrderDetail').toggle();
    event.preventDefault();
}

function savePacketeryUpdateOrderDetail(event) {
    jQuery('#packeteryUpdateOrderDetailForm').submit();
    event.preventDefault();
}

jQuery(document).ready(function() {
    jQuery('#showPacketeryUpdateOrderDetail').on('click',
        function(event) {
            jQuery('#packeteryUpdateOrderDetail').toggle();
            event.preventDefault();
        });
});
