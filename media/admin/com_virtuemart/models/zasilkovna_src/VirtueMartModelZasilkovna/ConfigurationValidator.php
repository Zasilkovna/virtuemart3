<?php
namespace VirtueMartModelZasilkovna;

class ConfigurationValidator
{
    public const KEY_USE_DEFAULT_WEIGHT = 'zasilkovna_use_default_weight';
    public const KEY_DEFAULT_WEIGHT = 'zasilkovna_default_weight';
    public const KEY_USE_DEFAULT_DIMENSIONS = 'zasilkovna_use_default_dimensions';
    public const KEY_API_PASS = 'zasilkovna_api_pass';
    public const KEY_ESHOP_LABEL = 'zasilkovna_eshop_label';
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


    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    /**
     * @var array <string, string>|array<string, array<string>>
     */
    private array $errors = [];
    
    /**
     * @var array<string, mixed>
     */
    private array $validData = [];

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function validate(): void {
        $this->validateApiPassword();
        $this->validateWeight();
        $this->validateDimensions();
        $this->setValidDataForNotValidatedFields();
    }

private function validateWeight(): void {
    if (
        (
            !empty($this->configuration[self::KEY_DEFAULT_WEIGHT])
            || $this->configuration[self::KEY_DEFAULT_WEIGHT] === '0'
        )
        && (
            !is_numeric($this->configuration[self::KEY_DEFAULT_WEIGHT])
            || (float)$this->configuration[self::KEY_DEFAULT_WEIGHT] < 0.001
        )
    ) {
        $this->errors[self::KEY_DEFAULT_WEIGHT] = [
            'PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_MUST_BE_POSITIVE',
            'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT',
        ];

        return;
    }

        if ($this->configuration[self::KEY_USE_DEFAULT_WEIGHT] === '1' && empty($this->configuration[self::KEY_DEFAULT_WEIGHT])) {
            $this->errors[self::KEY_DEFAULT_WEIGHT] = [
                'PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_IS_REQUIRED',
                'PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT',
            ];
            return;
        }

        $this->validData[self::KEY_DEFAULT_WEIGHT] = is_numeric($this->configuration[self::KEY_DEFAULT_WEIGHT]) ? round((float)$this->configuration[self::KEY_DEFAULT_WEIGHT], 3) : '';
        $this->validData[self::KEY_USE_DEFAULT_WEIGHT] = $this->configuration[self::KEY_USE_DEFAULT_WEIGHT] === '1';
    }

    private function validateDimensions(): void
    {
        $fields = [
            self::KEY_DEFAULT_LENGTH => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_LENGTH',
            self::KEY_DEFAULT_WIDTH => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_WIDTH',
            self::KEY_DEFAULT_HEIGHT => 'PLG_VMSHIPMENT_PACKETERY_DEFAULT_DIMENSIONS_HEIGHT',
        ];

        foreach ($fields as $field => $label) {
            if ((!empty($this->configuration[$field]) || $this->configuration[$field] === '0')
                && (!is_numeric($this->configuration[$field]) || (int)$this->configuration[$field] < 1)
            ) {
                $this->errors[$field] = ['PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_MUST_BE_POSITIVE_INTEGER', $label];
                continue;
            }
            if ($this->configuration[self::KEY_USE_DEFAULT_DIMENSIONS] === '1' && empty($this->configuration[$field])) {
                $this->errors[$field] = ['PLG_VMPSHIPMENT_PACKETERY_DEFAULT_FIELD_IS_REQUIRED', $label];
                continue;
            }

            $this->validData[$field] = is_numeric($this->configuration[$field]) ? (int)$this->configuration[$field] : '';
        }
        $this->validData[self::KEY_USE_DEFAULT_DIMENSIONS] = $this->configuration[self::KEY_USE_DEFAULT_DIMENSIONS] === '1';
    }

    private function validateApiPassword(): void {
        if ($this->configuration[self::KEY_API_PASS] === '') {
            $this->errors[self::KEY_API_PASS] = 'PLG_VMSHIPMENT_PACKETERY_API_PASS_NOT_SET';
            return;
        }
        if (strlen($this->configuration[self::KEY_API_PASS]) !== 32) {
            $this->errors[self::KEY_API_PASS] = 'PLG_VMSHIPMENT_PACKETERY_API_PASS_INVALID';
            return;
        }

        $this->validData[self::KEY_API_PASS] = $this->configuration[self::KEY_API_PASS];
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
    public function getValidData(): array {
        return $this->validData;
    }

    private function setValidDataForNotValidatedFields(): void {
        $this->validData[self::KEY_ESHOP_LABEL] = $this->configuration[self::KEY_ESHOP_LABEL] ?? self::CONFIG_DEFAULTS[self::KEY_ESHOP_LABEL];

        $paymentMethods = array_filter(
            $this->configuration,
            static function ($key) {
                return str_starts_with($key, self::KEY_PAYMENT_METHOD_PREFIX);
            },
            ARRAY_FILTER_USE_KEY
        );
        $this->validData = array_merge($this->validData, $paymentMethods);
    }
}
