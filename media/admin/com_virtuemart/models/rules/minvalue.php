<?php
defined('_JEXEC') or die;

class JFormRuleMinvalue extends JFormRule
{
    protected $regex = '^-?[0-9]+(\.[0-9]+)?$';

    public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
    {
        // Check if the value is empty and the field is not required
        $isRequired = (string) $element['required'] === 'true';
        if (!$isRequired && empty($value)) {
            return true;
        }
        
        // Check if the value matches the regular expression
        if (!preg_match('/' . $this->regex . '/', $value)) {
            $errorMessage = sprintf(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_FIELD_INVALID_FORMAT'),
                JText::_($element['label'])
            );
            $element->addAttribute('message', $errorMessage);

            return false;
        }

        $min = (float) $element['min'];

        // Check if the value is less than the minimum value
        if ($value < $min) {
            $errorMessage = sprintf(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_FIELD_MUST_BE_MINIMAL'),
                JText::_($element['label']),
                $min
            );
            $element->addAttribute('message', $errorMessage);

            return false;
        }

        return true;
    }
}
