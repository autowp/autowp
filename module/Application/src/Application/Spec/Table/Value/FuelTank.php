<?php

namespace Application\Spec\Table\Value;

use Laminas\View\Renderer\PhpRenderer;

class FuelTank
{
    protected $primary;
    protected $secondary;

    public function __construct(array $options)
    {
        $this->primary   = $options['primary'];
        $this->secondary = $options['secondary'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $attribute
     * @param $value
     * @param $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): string
    {
        $primary   = $values[$this->primary] ?? null;
        $secondary = $values[$this->secondary] ?? null;

        $html = $primary;
        if ($secondary) {
            $html .= '+' . $secondary;
        }

        if ($html) {
            $html .= ' <span class="unit" title="">'
                         . /* @phan-suppress-next-line PhanUndeclaredMethod */
                         $view->escapeHtml($view->translate('specs/unit/12/abbr'))
                     . '</span>';
        }

        return $html;
    }
}
