<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class DefaultValue
{
    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        if ($value === null) {
            return '';
        }

        $html = $view->escapeHtml($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            $html .=
                ' <span class="unit" title="' . $view->escapeHtmlAttr($view->translate($attribute['unit']['name'])) . '">' .
                    $view->escapeHtml($view->translate($attribute['unit']['abbr'])) .
                '</span>';
        }

        return $html;
    }
}