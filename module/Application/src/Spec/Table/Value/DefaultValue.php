<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\View\Renderer\PhpRenderer;

class DefaultValue
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array|ArrayAccess $attribute
     * @param mixed|null        $value
     * @param mixed|null        $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): ?string
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
