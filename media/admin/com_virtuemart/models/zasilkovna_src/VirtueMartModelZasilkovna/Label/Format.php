<?php

namespace VirtueMartModelZasilkovna\Label;

class Format
{
    const A6_ON_A4 = 'A6 on A4';
    const A6_ON_A6 = 'A6 on A6';
    const A7_ON_A4 = "A7 on A4";
    const A7_ON_A7 = "A7 on A7";
    const A8_ON_A8 = "A8 on A8";
    const SIZE_105x35mm_ON_A4 = "105x35mm on A4";

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
}
