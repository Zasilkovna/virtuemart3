<?php

namespace VirtueMartModelZasilkovna\Label;

class Format
{
    const A6_ON_A4 = 'A6 on A4';
    const A6_ON_A6 = 'A6 on A6';

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
                    self::A6_ON_A6
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
