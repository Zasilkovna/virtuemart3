<?php

namespace VirtueMartModelZasilkovna\Order;

use JFactory;
use JUri;
use VirtueMartModelZasilkovna\Label\Format;

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

        $detailFormHtml = '';
        $printLabelFormHtml = '';

        if ($order->hasPacketId()) {
            $printLabelFormHtml = $this->renderPrintLabelForm($order);
        } else {
            $this->renderer->setVariables(['order' => $order]);
            $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_detail_form.php');
            $detailFormHtml = $this->renderer->renderToString();
        }
        $document = JFactory::getDocument();
            $document->addScript(
                sprintf('%smedia/com_zasilkovna/media/js/order-detail.js?v=%s',
                    JUri::root(),
                    filemtime(JPATH_ROOT . '/media/com_zasilkovna/media/js/order-detail.js')
                )
            );

        return $detailsHtml . $detailFormHtml . $printLabelFormHtml;
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
    
    /**
     * @param Order|null $order
     * @return string
     */
    public function renderPrintLabelForm(Order $order = null)
    {
        if (!$order) {
            return '';
        }

        $this->renderer->setVariables([
            'order' => $order,
            'defaultLabelFormat' => $this->getDefaultLabelFormat(),
            'labelFormatType' => $this->getLabelFormatType($order),
        ]);

        $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_print_label_form.php');

        return $this->renderer->renderToString();
    }

    /**
     * @return string
     */
    private function getDefaultLabelFormat()
    {
        return \VmModel::getModel('zasilkovna')->getConfig('zasilkovna_last_label_format',
            \VirtueMartModelZasilkovna\Label\Format::DEFAULT_LABEL_FORMAT);
    }

    /**
     * @return string
     */
    private function getLabelFormatType(Order $order)
    {
        $type = Format::TYPE_INTERNAL;
        if ($order->getIsCarrier()) {
            $carrierRepository = new \VirtueMartModelZasilkovna\Carrier\Repository();
            $carrier = $carrierRepository->getCarrierById($order->getBranchId());
            if ($carrier && $carrier->has_carrier_direct_label) {
                $type = Format::TYPE_CARRIER;
            }
        }

        return $type;
    }
}
