<?php

namespace VirtueMartModelZasilkovna\Label;

use JHtml;
use JText;

class Format
{
    const A6_ON_A4 = 'A6 on A4';
    const A6_ON_A6 = 'A6 on A6';
    const A7_ON_A4 = "A7 on A4";
    const A7_ON_A7 = "A7 on A7";
    const A8_ON_A8 = "A8 on A8";
    const SIZE_105x35mm_ON_A4 = "105x35mm on A4";
    const CARRIER_A6_ON_A4 = 'carrier A6 on A4';
    const CARRIER_A6_ON_A6 = 'carrier A6 on A6';
    const DEFAULT_LABEL_FORMAT = 'A6_on_A4';
    const TYPE_INTERNAL = 'internal';
    const TYPE_CARRIER = 'carrier';

    const LABEL_FORMAT_OPTIONS = [
        self::A7_ON_A4 => ['value' =>  'A7_on_A4', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A7_ON_A4', 'type' => self::TYPE_INTERNAL],
        self::A6_ON_A4 => ['value' => 'A6_on_A4', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A6_ON_A4', 'type' => self::TYPE_INTERNAL],
        self::A6_ON_A6 => ['value' => 'A6_on_A6', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A6_ON_A6', 'type' => self::TYPE_INTERNAL],
        self::A7_ON_A7 => ['value' => 'A7_on_A7', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A7_ON_A7', 'type' => self::TYPE_INTERNAL],
        self::A8_ON_A8 => ['value' => 'A8_on_A8', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_A8_ON_A8', 'type' => self::TYPE_INTERNAL],
        self::SIZE_105x35mm_ON_A4 => ['value' => '105x35mm_on_A4', 'label' => 'PLG_VMSHIPMENT_PACKETERY_LABELS_PRINT_105X35MM_ON_A4', 'type' => self::TYPE_INTERNAL],
        self::CARRIER_A6_ON_A4 => ['value' => 'carriers_A6_on_A4', 'label' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_LABELS_PRINT_A6_ON_A4', 'type' => self::TYPE_CARRIER],
        self::CARRIER_A6_ON_A6 => ['value' => 'carriers_A6_on_A6', 'label' => 'PLG_VMSHIPMENT_PACKETERY_CARRIER_LABELS_PRINT_A6_ON_A6', 'type' => self::TYPE_CARRIER],
    ];



    /** @var string */
    private $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException('Unknown label format');
        }

        $this->value = $value;
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function isValid($value)
    {
        if (
            in_array(
                $value,
                [
                    self::A6_ON_A4,
                    self::A6_ON_A6,
                    self::A7_ON_A4,
                    self::A7_ON_A7,
                    self::A8_ON_A8,
                    self::SIZE_105x35mm_ON_A4,
                ],
                true
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param $selected
     * @param string $type
     * @param string $htmlId
     * @param string $htmlName
     * @param $cssClass
     * @return mixed
     */
    public static function getLabelFormatSelectHtml($selected, $type = '', $htmlId = 'print_type', $htmlName = 'print_type', $cssClass = 'packetery-label-format-select') {
        $options = [];
        foreach (self::LABEL_FORMAT_OPTIONS as $key => $option) {
            if ($type !== '' && $option['type'] !== $type) {
                continue;
            }
            $options[] = JHtml::_('select.option', $option['value'], JText::_($option['label']));
        }

        $attributes = sprintf('size="10" class="%s"', $cssClass);
        $html = JHtml::_('select.genericlist', $options, $htmlName, $attributes, 'value', 'text', $selected, $htmlId);

        return $html;
    }

    /**
     * @param $labelFormat
     * @return bool
     */
    public function isLabelTypeInternal($labelFormat) {
        return self::LABEL_FORMAT_OPTIONS[$labelFormat]['type'] === self::TYPE_INTERNAL;
    }
}
