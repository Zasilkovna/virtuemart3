<?php
namespace VirtueMartModelZasilkovna;

class ConfigurationValidator
{
    private const DIMENSIONS = [
        ConfigConstants::KEY_DEFAULT_LENGTH => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_LENGTH',
        ConfigConstants::KEY_DEFAULT_WIDTH => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_WIDTH',
        ConfigConstants::KEY_DEFAULT_HEIGHT => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_HEIGHT',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $formData;

    /**
     * @var array <string, string>|array<string, array<string>>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $formData
     */
    public function __construct(array $formData)
    {
        $this->formData = $formData;
    }

    public function validate(): void {
        $this->validateApiPassword();
        $this->validateWeight();
        $this->validateDimensions();
    }

private function validateWeight(): void {
    if (
        $this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT] !== ''
        && (
            !is_numeric($this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT])
            || (float)$this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT] < 0.001
        )
    ) {
        $this->errors[ConfigConstants::KEY_DEFAULT_WEIGHT] = [
            'PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_MUST_BE_POSITIVE',
            'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT',
        ];

        return;
    }

        if ($this->formData[ConfigConstants::KEY_USE_DEFAULT_WEIGHT] === '1' && empty($this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT])) {
            $this->errors[ConfigConstants::KEY_DEFAULT_WEIGHT] = [
                'PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_IS_REQUIRED',
                'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT',
            ];
        }
    }

    private function validateDimensions(): void {
        foreach (self::DIMENSIONS as $field => $label) {
            if ((!empty($this->formData[$field]) || $this->formData[$field] === '0')
                && (!is_numeric($this->formData[$field]) || (int)$this->formData[$field] < 1)
            ) {
                $this->errors[$field] = ['PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_MUST_BE_POSITIVE_INTEGER', $label];
                continue;
            }
            if ($this->formData[ConfigConstants::KEY_USE_DEFAULT_DIMENSIONS] === '1' && empty($this->formData[$field])) {
                $this->errors[$field] = ['PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_IS_REQUIRED', $label];
            }
        }
    }

    private function validateApiPassword(): void {
        if ($this->formData[ConfigConstants::KEY_API_PASS] === '') {
            $this->errors[ConfigConstants::KEY_API_PASS] = 'PLG_VMSHIPMENT_PACKETERY_API_PASS_NOT_SET';
            return;
        }
        if (strlen($this->formData[ConfigConstants::KEY_API_PASS]) !== 32) {
            $this->errors[ConfigConstants::KEY_API_PASS] = 'PLG_VMSHIPMENT_PACKETERY_API_PASS_INVALID';
        }
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * @return array<string, string>|array<string, array<string>>
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array {
        
        $normalizedData = [];
        $normalizedData[ConfigConstants::KEY_API_PASS] = $this->formData[ConfigConstants::KEY_API_PASS];
        $normalizedData[ConfigConstants::KEY_ESHOP_LABEL] = $this->formData[ConfigConstants::KEY_ESHOP_LABEL] ?? ConfigConstants::CONFIG_DEFAULTS[ConfigConstants::KEY_ESHOP_LABEL];

        $normalizedData[ConfigConstants::KEY_USE_DEFAULT_WEIGHT] = $this->formData[ConfigConstants::KEY_USE_DEFAULT_WEIGHT] === '1';
        $normalizedData[ConfigConstants::KEY_DEFAULT_WEIGHT] = is_numeric($this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT]) ? round((float)$this->formData[ConfigConstants::KEY_DEFAULT_WEIGHT], 3) : '';

        $normalizedData[ConfigConstants::KEY_USE_DEFAULT_DIMENSIONS] = $this->formData[ConfigConstants::KEY_USE_DEFAULT_DIMENSIONS] === '1';
        foreach (self::DIMENSIONS as $field => $label) {
            $normalizedData[$field] = is_numeric($this->formData[$field]) ? (int)$this->formData[$field] : '';
        }

        $paymentMethods = array_filter(
            $this->formData,
            static function ($key) {
                return str_starts_with($key, ConfigConstants::KEY_PAYMENT_METHOD_PREFIX);
            },
            ARRAY_FILTER_USE_KEY
        );

        return array_merge($normalizedData, $paymentMethods);
    }
}
