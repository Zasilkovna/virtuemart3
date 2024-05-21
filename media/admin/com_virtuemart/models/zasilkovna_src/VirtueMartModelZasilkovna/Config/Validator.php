<?php

namespace VirtueMartModelZasilkovna\Config;

use Joomla\CMS\Application\CMSApplicationInterface;
use JText;
use VirtueMartModelZasilkovna\FlashMessage;

class Validator {
    private CMSApplicationInterface $app;

    public function __construct(CMSApplicationInterface $app) {
        $this->app = $app;
    }

    public function validateApiPassword(string $password): void {
        if (strlen($password) !== 32) {
            $this->app->enqueueMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_API_PASS_INVALID'), FlashMessage::TYPE_ERROR);
        }
    }

    public function validateWeight(string $weight): bool {
        if (!is_numeric($weight) || $weight < 0.001) {
            $this->app->enqueueMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT_INVALID'), FlashMessage::TYPE_ERROR);

            return false;
        }

        return true;
    }

    public function mandatoryWeightCheck(string $weight, string $useWeight): void {
        if ($weight === '' && $useWeight === '1') {
            $this->app->enqueueMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_DEFAULT_WEIGHT_INVALID'), FlashMessage::TYPE_ERROR);
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
                $this->app->enqueueMessage(JText::_($errorKey), FlashMessage::TYPE_ERROR);
            }
        }
    }

    public function mandatoryDimensionsCheck(string $length, string $width, string $height, string $useDimensions): void {
        if (
            $useDimensions === '1' &&
            (
                !$this->validateDimensionValue($length) ||
                !$this->validateDimensionValue($width) ||
                !$this->validateDimensionValue($height)
            )
        ) {
            $this->app->enqueueMessage(JText::_('PLG_VMSHIPMENT_PACKETERY_ENTER_ALL_DIMENSIONS'), FlashMessage::TYPE_ERROR);
        }
    }

    private function validateDimensionValue(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }

}
