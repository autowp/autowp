<?php

namespace Application\Spec\Table\Value;

use Laminas\View\Renderer\PhpRenderer;

class Gearbox
{
    protected $type;
    protected $gears;
    protected $name;

    public function __construct(array $options)
    {
        $this->type  = $options['type'];
        $this->gears = $options['gears'];
        $this->name  = $options['name'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $attribute
     * @param $value
     * @param $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): string
    {
        $type  = $values[$this->type] ?? null;
        $gears = $values[$this->gears] ?? null;
        $name  = $values[$this->name] ?? null;

        $result = '';
        if ($type) {
            $result .= $type;
        }
        if ($gears) {
            if ($result) {
                $result .= ' ' . $gears;
            } else {
                $result = $gears;
            }
        }
        if ($name) {
            if ($result) {
                $result .= ' (' . $name . ')';
            } else {
                $result = $name;
            }
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $view->escapeHtml($result);
    }
}
