<?php

namespace VirtueMartModelZasilkovna\Order;

use JFactory;
use JUri;

/**
 * Class HtmlRenderer
 */
class HtmlRenderer
{
    const TEMPLATES_DIR = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'views' . DS . 'zasilkovna' . DS . 'tmpl';


    /**
     * @var \VirtueMartModelZasilkovna\Box\Renderer $renderer
     */
    private $renderer;


    public function __construct()
    {
        $this->renderer = new \VirtueMartModelZasilkovna\Box\Renderer();
    }

    /**
     * @param ShipmentInfo $shipment
     * @return string
     */
    public function getOrderDetailsHtml(ShipmentInfo $shipment = null)
    {
        if (!$shipment) {
            return '';
        }

        $this->renderer->setVariables(['shipment' => $shipment]);

        $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_extended_detail.php');
        $detailsHtml = $this->renderer->renderToString();

        $trackingHtml = '';
        $formHtml = '';

        if ($shipment->hasPacketId()) {
            $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_tracking_link.php');
            $this->renderer->setVariables([
                'shipment' => $shipment,
                'trackingUrl' => \plgVmShipmentZasilkovna::TRACKING_URL . $shipment->getZasilkovnaPacketId(),
            ]);
            $trackingHtml = $this->renderer->renderToString();
        } else {
            $document = JFactory::getDocument();
            $document->addScript(
                    sprintf('%smedia/com_zasilkovna/media/js/order-detail.js?v=%s',
                        JUri::root(),
                        filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/js/order-detail.js')
                    )
            );
            $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_detail_form.php');
            $formHtml = $this->renderer->renderToString();
        }

        return $detailsHtml . $trackingHtml . $formHtml;
    }

}
