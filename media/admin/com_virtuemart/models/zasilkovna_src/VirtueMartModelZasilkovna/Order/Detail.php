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

        $this->renderer->setVariables([
            'order' => $order,
            'trackingLinkHtml' => $this->getTrackingLinkHtml($order),
        ]);
        $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_extended_detail.php');
        $detailsHtml = $this->renderer->renderToString();

        $formHtml = '';
        if (!$order->hasPacketId()) {
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

        return $detailsHtml . $formHtml;
    }

    /**
     * @param array $formData
     * @return \VirtueMartModelZasilkovna\Order\DetailFormValidationReport
     */
    public function validateFormData(array $formData)
    {
        $requiredNumericFields = [
            'weight' => 'PLG_VMSHIPMENT_PACKETERY_WEIGHT',
            'zasilkovna_packet_price' => 'PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE',
            'packet_cod' => 'PLG_VMSHIPMENT_PACKETERY_COD',
        ];
        $validationReport = new \VirtueMartModelZasilkovna\Order\DetailFormValidationReport();

        foreach ($requiredNumericFields as $field => $translationKey) {
            if (!isset($formData[$field]) || !is_numeric($formData[$field])) {
                $validationReport->addError(
                    \JText::sprintf(
                        'PLG_VMSHIPMENT_PACKETERY_ORDER_DETAIL_FORM_ERROR_FIELD_REQUIRED',
                        \JText::_($translationKey)
                    )
                );
            }
        }

        return $validationReport;
    }

    /**
     * @param Order $order
     * @return string
     */
    private function getTrackingLinkHtml(Order $order)
    {
        $html = '';
        if ($order->hasPacketId()) {
            $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_tracking_link.php');
            $this->renderer->setVariables([
                'order' => $order,
                'trackingUrl' => sprintf(\plgVmShipmentZasilkovna::TRACKING_URL, $order->getZasilkovnaPacketId()),
            ]);
            $html = $this->renderer->renderToString();
        }

        return $html;
    }
}
