<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class BootVolume
{
    protected $min;
    protected $max;

    public function __construct(array $options)
    {
        $this->min = $options['min'];
        $this->max = $options['max'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        $min = isset($values[$this->min]) ? $values[$this->min] : null;
        $max = isset($values[$this->max]) ? $values[$this->max] : null;

        if ($min && $max && ($min != $max)) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $html = $view->escapeHtml($min) . '&ndash;' . $max;
        } elseif ($min) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $html = $view->escapeHtml($min);
        } else {
            $html = '';
        }

        if ($html && isset($attribute['unit']) && $attribute['unit']) {
            $html .=
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            ' <span class="unit" title="' . $view->escapeHtmlAttr($attribute['unit']['name']) . '">' .
            /* @phan-suppress-next-line PhanUndeclaredMethod */
                $view->escapeHtml($view->translate($attribute['unit']['abbr'])) .
            '</span>';
        }

        return $html;
    }
}
