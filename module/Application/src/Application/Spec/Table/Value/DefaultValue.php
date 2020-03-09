<?php

namespace Application\Spec\Table\Value;

use Laminas\View\Renderer\PhpRenderer;

class DefaultValue
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $attribute
     * @param $value
     * @param $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): string
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
                ' <span class="unit" title="' . $title . '">'
                    . /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $view->escapeHtml($view->translate($attribute['unit']['abbr']))
                . '</span>';
        }

        return $html;
    }
}
