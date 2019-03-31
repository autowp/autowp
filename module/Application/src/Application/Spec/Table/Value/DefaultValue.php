<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class DefaultValue
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param PhpRenderer $view
     * @param $attribute
     * @param $value
     * @param $values
     * @return mixed|string
     */
    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        if ($value === null) {
            return '';
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $html = $view->escapeHtml($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $title = $view->escapeHtmlAttr($view->translate($attribute['unit']['name']));
            $html .=
                ' <span class="unit" title="' . $title . '">' .
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $view->escapeHtml($view->translate($attribute['unit']['abbr'])) .
                '</span>';
        }

        return $html;
    }
}
