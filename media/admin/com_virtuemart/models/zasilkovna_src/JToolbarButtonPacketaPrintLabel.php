<?php

class JToolbarButtonPacketaPrintLabel extends \JToolbarButton
{
    protected $_name = 'PacketaPrintLabel';

    /**
     * @return string
     */
    public function fetchButton()
    {
        return sprintf('
            <div class="btn-wrapper">
                <button class="btn btn-small" id="toolbar-packetaPrintLabel" type="button">
                    <span class="icon-print" aria-hidden="true"></span>
                    %s
                </button>
            </div>',
            \JText::_('PLG_VMSHIPMENT_PACKETERY_PRINT_LABEL'));
    }

    /**
     * @return string
     */
    public function fetchId()
    {
        return $this->_parent->getName() . '-packeta-print-label';
    }
}
