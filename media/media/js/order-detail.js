function cancelPacketeryUpdateOrderDetail(event) {
    jQuery('#packeteryUpdateOrderDetail').toggle();
    event.preventDefault();
}

function savePacketeryUpdateOrderDetail(event) {
    jQuery('#packeteryUpdateOrderDetailForm').submit();
    event.preventDefault();
}

function cancelPacketeryPrintLabel(event) {
    jQuery('#packeteryPrintLabelModal').toggle();
    event.preventDefault();
}

function submitPrintLabel(event) {
    jQuery('#packeteryPrintLabelForm').submit();
    jQuery(document).off('keyup', escapeToCloseModal);
    event.preventDefault();
}

function escapeToCloseModal(event) {
    if (event.key === 'Escape') {
        jQuery('#packeteryPrintLabelModal').toggle();
        jQuery(document).off('keyup', escapeToCloseModal);
    }
}

jQuery(document).ready(function() {
    jQuery('#showPacketeryUpdateOrderDetail').on('click',
        function(event) {
            jQuery('#packeteryUpdateOrderDetail').toggle();
            event.preventDefault();
        });
    jQuery('#toolbar-packetaPrintLabel').on('click',
        function(event) {
            jQuery('#subhead-container').append(jQuery('#packeteryPrintLabelModal'));
            var position = jQuery(this).position();
            var buttonHeight = jQuery(this).outerHeight();
            jQuery('#packeteryPrintLabelModal').css({
                top: position.top + buttonHeight + 6,
                 left: position.left,
                 width: '540px'
            });

            jQuery('#packeteryPrintLabelModal').toggle();

            if (jQuery('#packeteryPrintLabelModal').is(':visible')) {
                jQuery(document).on('keyup', escapeToCloseModal);
            }
            event.preventDefault();
        });
});
