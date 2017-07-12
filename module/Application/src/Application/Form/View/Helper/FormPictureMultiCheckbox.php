<?php


namespace Application\Form\View\Helper;

use Zend\Form\Element\MultiCheckbox as MultiCheckboxElement;
use Zend\Form\View\Helper\FormMultiCheckbox;
use Zend\Form\LabelAwareInterface;

class FormPictureMultiCheckbox extends FormMultiCheckbox
{
    /**
     * Render options
     *
     * @param  MultiCheckboxElement $element
     * @param  array                $options
     * @param  array                $selectedOptions
     * @param  array                $attributes
     * @return string
     */
    protected function renderOptions(
        MultiCheckboxElement $element,
        array $options,
        array $selectedOptions,
        array $attributes
    ) {
        $labelHelper      = $this->getLabelHelper();
        $labelClose       = $labelHelper->closeTag();
        $globalLabelAttributes = [];
        $closingBracket   = $this->getInlineClosingBracket();

        if ($element instanceof LabelAwareInterface) {
            $globalLabelAttributes = $element->getLabelAttributes();
        }

        if (empty($globalLabelAttributes)) {
            $globalLabelAttributes = $this->labelAttributes;
        }

        $combinedMarkup = [];
        $count          = 0;

        foreach ($options as $key => $optionSpec) {
            $count++;
            if ($count > 1 && array_key_exists('id', $attributes)) {
                unset($attributes['id']);
            }

            $value           = '';
            $label           = '';
            $inputAttributes = $attributes;
            $labelAttributes = $globalLabelAttributes;
            $selected        = (isset($inputAttributes['selected'])
                            && $inputAttributes['type'] != 'radio'
                            && $inputAttributes['selected']);
            $disabled        = (isset($inputAttributes['disabled'])
                            && $inputAttributes['disabled']);

            if (is_scalar($optionSpec)) {
                $optionSpec = [
                    'label' => $optionSpec,
                    'value' => $key
                ];
            }

            if (isset($optionSpec['value'])) {
                $value = $optionSpec['value'];
            }
            if (isset($optionSpec['label'])) {
                $label = $optionSpec['label'];
            }
            if (isset($optionSpec['selected'])) {
                $selected = $optionSpec['selected'];
            }
            if (isset($optionSpec['disabled'])) {
                $disabled = $optionSpec['disabled'];
            }
            if (isset($optionSpec['label_attributes'])) {
                $labelAttributes = (isset($labelAttributes))
                    ? array_merge($labelAttributes, $optionSpec['label_attributes'])
                    : $optionSpec['label_attributes'];
            }
            if (isset($optionSpec['attributes'])) {
                $inputAttributes = array_merge($inputAttributes, $optionSpec['attributes']);
            }

            if (in_array($value, $selectedOptions)) {
                $selected = true;
            }

            $inputAttributes['value']    = $value;
            $inputAttributes['checked']  = $selected;
            $inputAttributes['disabled'] = $disabled;

            $input = sprintf(
                '<input %s%s',
                $this->createAttributesString($inputAttributes),
                $closingBracket
            );

            $labelOpen = $labelHelper->openTag($labelAttributes);
            $markup    = $labelOpen . $input . $this->view->htmlImg($label) . $labelClose;

            $combinedMarkup[] = $markup;
        }

        return
            '<div class="multicheckbox-pictures" data-toggle="buttons">' .
                implode($this->getSeparator(), $combinedMarkup) .
            '</div>';
    }
}
