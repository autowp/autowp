<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class DefaultValue
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        if ($value === null) {
            return '';
        }

        $html = $view->escapeHtml($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            $title = $view->escapeHtmlAttr($view->translate($attribute['unit']['name']));
            $html .=
                ' <span class="unit" title="' . $title . '">' .
                    $view->escapeHtml($view->translate($attribute['unit']['abbr'])) .
                '</span>';
        }

        return $html;
    }
}
