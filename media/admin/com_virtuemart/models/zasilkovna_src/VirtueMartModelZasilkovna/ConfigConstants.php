<?php

namespace VirtueMartModelZasilkovna;

class ConfigConstants
{
    public const KEY_API_PASS = 'zasilkovna_api_pass';
    public const KEY_ESHOP_LABEL = 'zasilkovna_eshop_label';
    public const KEY_USE_DEFAULT_WEIGHT = 'zasilkovna_use_default_weight';
    public const KEY_DEFAULT_WEIGHT = 'zasilkovna_default_weight';
    public const KEY_USE_DEFAULT_DIMENSIONS = 'zasilkovna_use_default_dimensions';
    public const KEY_DEFAULT_LENGTH = 'zasilkovna_default_length';
    public const KEY_DEFAULT_WIDTH = 'zasilkovna_default_width';
    public const KEY_DEFAULT_HEIGHT = 'zasilkovna_default_height';
    public const KEY_PAYMENT_METHOD_PREFIX = 'zasilkovna_payment_method_';

    public const CONFIG_DEFAULTS = [
        self::KEY_USE_DEFAULT_WEIGHT => false,
        self::KEY_DEFAULT_WEIGHT => '',
        self::KEY_USE_DEFAULT_DIMENSIONS => false,
        self::KEY_DEFAULT_LENGTH => '',
        self::KEY_DEFAULT_WIDTH => '',
        self::KEY_DEFAULT_HEIGHT => '',
        self::KEY_API_PASS => '',
        self::KEY_ESHOP_LABEL => '',
    ];
}
