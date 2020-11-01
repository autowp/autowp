<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\I18n\View\Helper\Translate;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
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

        /** @var Translate $translateHelper */
        $translateHelper = $view->getHelperPluginManager()->get('translate');
        /** @var EscapeHtml $escapeHtmlHelper */
        $escapeHtmlHelper = $view->getHelperPluginManager()->get('escapeHtml');
        /** @var EscapeHtmlAttr $escapeHtmlAttrHelper */
        $escapeHtmlAttrHelper = $view->getHelperPluginManager()->get('escapeHtmlAttr');

        $html = $escapeHtmlHelper($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            $title = $escapeHtmlAttrHelper($translateHelper($attribute['unit']['name']));
            $html .=
                ' <span class="unit" title="' . $title . '">'
                    . $escapeHtmlHelper($translateHelper($attribute['unit']['abbr']))
                . '</span>';
        }

        return $html;
    }
}
