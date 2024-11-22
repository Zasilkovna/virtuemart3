<?php
defined('_JEXEC') or die;

/**
 * Class JFormRuleMinvalue
 * rule to validate if the value is greater than or equal to the minimum value
 * with custom error message
 */

class JFormRuleMinvalue extends JFormRule
{

    /**
     * @param SimpleXMLElement $element
     * @param $value
     * @param string|null $group
     * @param JRegistry|null $input
     * @param JForm|null $form
     * @return bool
     */
    public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
    {
        $isRequired = (string)$element['required'] === 'true';
        if (!$isRequired && trim($value) === '') {
            return true;
        }

        if (!is_numeric($value)) {
            $errorMessage = sprintf(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CONFIG_FIELD_INVALID_FORMAT'),
                JText::_($element['label'])
            );
            $element->addAttribute('message', $errorMessage);

            return false;
        }

        $value = (float)$value;
        $min = (float)$element['min'];

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
