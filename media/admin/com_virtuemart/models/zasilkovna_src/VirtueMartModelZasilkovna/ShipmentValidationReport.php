<?php

namespace VirtueMartModelZasilkovna;

class ShipmentValidationReport
{
    const ERROR_CODE_INVALID_TYPE = 'INVALID_TYPE'; // when number contains characters
    const ERROR_CODE_WEIGHT_PRICE_MISSING = 'WEIGHT_PRICE_MISSING';
    const ERROR_CODE_WEIGHT_MISSING = 'WEIGHT_MISSING';
    const ERROR_CODE_WEIGHT_EXCEEDED = 'WEIGHT_EXCEEDED';
    const ERROR_CODE_GLOBAL_DEFAULT_PRICE_MISSING = 'GLOBAL_DEFAULT_PRICE_MISSING';
    const ERROR_CODE_GLOBAL_MAX_WEIGHT_MISSING = 'GLOBAL_MAX_WEIGHT_MISSING';
    const ERROR_CODE_DUPLICATE_COUNTRIES = 'DUPLICATE_COUNTRIES';
    const ERROR_CODE_ALLOWED_COUNTRIES_ONLY = 'ALLOWED_COUNTRIES_ONLY';
    const ERROR_CODE_NO_BLOCKED_COUNTRY = 'NO_BLOCKED_COUNTRY';
    const ERROR_CODE_NO_HD_CARRIER_SELECTED = 'NO_HD_CARRIER_SELECTED';
    const ERROR_CODE_HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES = 'HD_CARRIER_IS_OUT_OF_ALLOWED_COUNTRIES';
    const ERROR_CODE_HD_CARRIER_IS_IN_BLOCKING_COUNTRIES = 'HD_CARRIER_IS_IN_BLOCKING_COUNTRIES';
    const ERROR_CODE_HD_CARRIER_NO_PUBLISHED_COUNTRY = 'HD_CARRIER_NO_PUBLISHED_COUNTRY';
    const ERROR_CODE_HD_CARRIER_NOT_EXISTS = 'HD_CARRIER_NOT_EXISTS';

    /** @var array */
    private $errors = [];

    /**
     * @return array
     */
    function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * @param $code
     */
    public function addError($code)
    {
        $this->errors[] = (object)[
            'code' => $code,
            'translationCode' => $this->getTranslationCode($code),
        ];
    }

    /**
     * @return string[]
     */
    public function getErrorCodes()
    {
        return array_map(
            function ($error) {
                return $error->code;
            },
            $this->errors
        );
    }

    /**
     * @param $code
     * @return string
     */
    private function getTranslationCode($code)
    {
        return 'PLG_VMSHIPMENT_PACKETERY_SHIPPING_ERROR_' . $code;
    }

    /**
     * @param \VirtueMartModelZasilkovna\ShipmentValidationReport $report
     */
    public function merge(ShipmentValidationReport $report)
    {
        $this->errors = array_merge($this->errors, $report->getErrors());
    }
}
