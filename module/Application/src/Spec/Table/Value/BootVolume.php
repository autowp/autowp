<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\I18n\View\Helper\Translate;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Renderer\PhpRenderer;

class BootVolume
{
    protected ?int $min;
    protected ?int $max;

    public function __construct(array $options)
    {
        $this->min = $options['min'];
        $this->max = $options['max'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array|ArrayAccess $attribute
     * @param mixed             $value
     */
    public function render(PhpRenderer $view, $attribute, $value, array $values): ?string
    {
        $min = $values[$this->min] ?? null;
        $max = $values[$this->max] ?? null;

        /** @var Translate $translateHelper */
        $translateHelper = $view->getHelperPluginManager()->get('translate');
        /** @var EscapeHtml $escapeHtmlHelper */
        $escapeHtmlHelper = $view->getHelperPluginManager()->get('escapeHtml');
        /** @var EscapeHtmlAttr $escapeHtmlAttrHelper */
        $escapeHtmlAttrHelper = $view->getHelperPluginManager()->get('escapeHtmlAttr');

        if ($min && $max && ($min !== $max)) {
            $html = $escapeHtmlHelper($min) . '&ndash;' . $max;
        } elseif ($min) {
            $html = $escapeHtmlHelper($min);
        } else {
            $html = '';
        }

        if ($html && isset($attribute['unit']) && $attribute['unit']) {
            $html .= ' <span class="unit" title="' . $escapeHtmlAttrHelper($attribute['unit']['name']) . '">'
                . $escapeHtmlHelper($translateHelper($attribute['unit']['abbr']))
            . '</span>';
        }

        return $html;
    }
}
