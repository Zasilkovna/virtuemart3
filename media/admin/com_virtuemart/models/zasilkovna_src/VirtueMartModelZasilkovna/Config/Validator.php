<?php

namespace VirtueMartModelZasilkovna\Config;

class Validator {

    private array $errors = [];

    public function validateApiPassword(string $password): void {
        if (strlen($password) !== 32) {
            $this->errors[] = 'PLG_VMSHIPMENT_PACKETERY_API_PASS_INVALID';
        }
    }

    public function validateWeight(string $weight): bool {
        if (is_numeric($weight) === false || $weight < 0.001) {
            $this->errors[] = 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT_INVALID';

            return false;
        }

        return true;
    }

    public function mandatoryWeightCheck(string $weight, string $useWeight): void {
        if ($weight === '' && $useWeight === '1') {
            $this->errors[] = 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT_INVALID';
        }
    }

    public function validateDimensions($postData): void {
        $dimensionsValidationConfig = [
            OptionKey::DEFAULT_LENGTH => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_LENGTH_INVALID',
            OptionKey::DEFAULT_WIDTH  => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WIDTH_INVALID',
            OptionKey::DEFAULT_HEIGHT => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_HEIGHT_INVALID',
        ];
        foreach ($dimensionsValidationConfig as $postKey => $errorKey) {
            if ($postData[$postKey] !== '' && $this->validateDimensionValue($postData[$postKey]) === false) {
                $this->errors[] = $errorKey;
            }
        }
    }

    public function mandatoryDimensionsCheck(string $length, string $width, string $height, string $useDimensions): void {
        if (
            $useDimensions === '1' &&
            (
                $this->validateDimensionValue($length) === false ||
                $this->validateDimensionValue($width) === false ||
                $this->validateDimensionValue($height) === false
            )
        ) {
            $this->errors[] = 'PLG_VMSHIPMENT_PACKETERY_ENTER_ALL_DIMENSIONS';
        }
    }

    private function validateDimensionValue(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function isValid(): bool {
        return ($this->errors === []);
    }

}
