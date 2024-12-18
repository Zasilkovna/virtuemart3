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
        $isJoomla4OrNewer = version_compare(JVERSION, '4.0.0', '>=');
        $document->addScriptDeclaration(
            "var isJoomla4OrNewer = " . json_encode($isJoomla4OrNewer) . ";"
        );

        return $detailsHtml . $detailFormHtml . $printLabelFormHtml;
    }

    /**
     * @param array $formData
     * @return DetailFormValidationReport
     */
    public function validateFormData(array $formData)
    {
        $requiredNumericFields = [
            'weight' => 'PLG_VMSHIPMENT_PACKETERY_WEIGHT',
            'zasilkovna_packet_price' => 'PLG_VMSHIPMENT_PACKETERY_PACKET_PRICE',
            'packet_cod' => 'PLG_VMSHIPMENT_PACKETERY_COD',
        ];

        $dimensions = [
            'length' => 'PLG_VMSHIPMENT_PACKETERY_DIMENSIONS_LENGTH',
            'width' => 'PLG_VMSHIPMENT_PACKETERY_DIMENSIONS_WIDTH',
            'height' => 'PLG_VMSHIPMENT_PACKETERY_DIMENSIONS_HEIGHT',
        ];

        $errors = [];
        $this->validateRequiredNumericFields($formData, $requiredNumericFields, $errors);
        $this->validateMinimumValues($formData, array_merge($requiredNumericFields, $dimensions), $errors);

        $validationReport = new DetailFormValidationReport();
        if (count($errors) > 0) {
            $validationReport->addError('<strong>' . \JText::_('PLG_VMSHIPMENT_PACKETERY_ORDER_DETAIL_FORM_GENERAL_ERROR') . '</strong>');
        }

        foreach ($errors as $error) {
            $validationReport->addError($error);
        }

        return $validationReport;
    }

    private function validateRequiredNumericFields(array $formData, array $fields, &$errors)
    {
        foreach ($fields as $field => $translationKey) {
            if (!isset($formData[$field]) || !is_numeric($formData[$field])) {
                $errors[] =
                \JText::sprintf(
                    'PLG_VMSHIPMENT_PACKETERY_ORDER_DETAIL_FORM_ERROR_FIELD_REQUIRED',
                    \JText::_($translationKey)
                );
            }
        }
    }

    private function validateMinimumValues(array $formData, array $fields, &$errors)
    {
        foreach ($fields as $field => $translationKey) {
            if (isset($formData[$field]) && $formData[$field] <= 0) {
                $errors[] =
                \JText::sprintf(
                    'PLG_VMSHIPMENT_PACKETERY_ORDER_DETAIL_FORM_ERROR_MIN_VALUE',
                    \JText::_($translationKey)
                );
            }
        }
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

        $labelFormatType = $this->getLabelFormatType($order);

        $this->renderer->setVariables([
            'order' => $order,
            'defaultLabelFormat' => $this->getDefaultLabelFormat($labelFormatType),
            'labelFormatType' => $labelFormatType,
        ]);

        $this->renderer->setTemplate(self::TEMPLATES_DIR . DS . 'order_print_label_form.php');

        return $this->renderer->renderToString();
    }

    /**
     * @param string $labelType
     * @return string
     */
    private function getDefaultLabelFormat($labelType)
    {
        /** @var \VirtueMartModelZasilkovna $model */
        $model = \VmModel::getModel('zasilkovna');

        $configKey = ($labelType === Format::TYPE_CARRIER)
            ? Format::LAST_CARRIER_LABEL_FORMAT
            : Format::LAST_LABEL_FORMAT;

        $defaultValue = ($labelType === Format::TYPE_CARRIER)
            ? Format::DEFAULT_CARRIER_LABEL_FORMAT
            : Format::DEFAULT_LABEL_FORMAT;

        return $model->getConfig($configKey, $defaultValue);
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
