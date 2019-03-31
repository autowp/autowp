<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class FuelTank
{
    protected $primary;
    protected $secondary;

    public function __construct(array $options)
    {
        $this->primary = $options['primary'];
        $this->secondary = $options['secondary'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param PhpRenderer $view
     * @param $attribute
     * @param $value
     * @param $values
     * @return mixed|string|null
     */
    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        $primary = isset($values[$this->primary]) ? $values[$this->primary] : null;
        $secondary = isset($values[$this->secondary]) ? $values[$this->secondary] : null;

        $html = $primary;
        if ($secondary) {
            $html .= '+' . $secondary;
        }

        if ($html) {
            $html .= ' <span class="unit" title="">' .
                         /* @phan-suppress-next-line PhanUndeclaredMethod */
                         $view->escapeHtml($view->translate('specs/unit/12/abbr')) .
                     '</span>';
        }

        return $html;
    }
}
