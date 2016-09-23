<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class EnginePlacement
{
    protected $placement;
    protected $orientation;

    public function __construct(array $options)
    {
        $this->placement = $options['placement'];
        $this->orientation = $options['orientation'];
    }

    public function render(PhpRenderer $view, $attribute, $value, $values)
    {
        $placement = isset($values[$this->placement]) ? $values[$this->placement] : null;
        $orientation = isset($values[$this->orientation]) ? $values[$this->orientation] : null;

        $array = [];
        if ($placement) {
            $array[] = $placement;
        }
        if ($orientation) {
            $array[] = $orientation;
        }

        return implode(', ', $array);
    }
}