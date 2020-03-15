<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\View\Renderer\PhpRenderer;

use function implode;

class EnginePlacement
{
    protected int $placement;
    protected int $orientation;

    public function __construct(array $options)
    {
        $this->placement   = $options['placement'];
        $this->orientation = $options['orientation'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array|ArrayAccess $attribute
     * @param mixed             $value
     * @param mixed             $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): ?string
    {
        $placement   = $values[$this->placement] ?? null;
        $orientation = $values[$this->orientation] ?? null;

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
