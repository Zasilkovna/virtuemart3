<?php

namespace VirtueMartModelZasilkovna\Order;

use JFactory;
use JUri;

/**
 * Class Detail
 */
class Detail
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
     * @param Order $order
     * @return string
     */
    public function renderToString(Order $order = null)
    {
        if (!$order) {
            return '';
        }

        $this->renderer->setVariables(['order' => $order]);

        $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_extended_detail.php');
        $detailsHtml = $this->renderer->renderToString();

        $trackingHtml = '';
        $formHtml = '';

        if ($order->hasPacketId()) {
            $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_tracking_link.php');
            $this->renderer->setVariables([
                'order' => $order,
                'trackingUrl' => sprintf(\plgVmShipmentZasilkovna::TRACKING_URL, $order->getZasilkovnaPacketId()),
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
