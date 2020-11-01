<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\I18n\View\Helper\Translate;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Renderer\PhpRenderer;

class FuelTank
{
    protected int $primary;
    protected int $secondary;

    public function __construct(array $options)
    {
        $this->primary   = $options['primary'];
        $this->secondary = $options['secondary'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array|ArrayAccess $attribute
     * @param mixed             $value
     * @param mixed             $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): ?string
    {
        $primary   = $values[$this->primary] ?? null;
        $secondary = $values[$this->secondary] ?? null;

        $html = $primary;
        if ($secondary) {
            $html .= '+' . $secondary;
        }

        if ($html) {
            /** @var Translate $translateHelper */
            $translateHelper = $view->getHelperPluginManager()->get('translate');
            /** @var EscapeHtml $escapeHtmlHelper */
            $escapeHtmlHelper = $view->getHelperPluginManager()->get('escapeHtml');

            $html .= ' <span class="unit">'
                         . $escapeHtmlHelper($translateHelper('specs/unit/12/abbr'))
                     . '</span>';
        }

        return $html;
    }
}
