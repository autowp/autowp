<?php

namespace Application\Spec\Table\Value;

use Laminas\View\Renderer\PhpRenderer;

use function sprintf;

class Wheel
{
    protected $tyrewidth;
    protected $tyreseries;
    protected $radius;
    protected $rimwidth;

    public function __construct(array $options)
    {
        $this->tyrewidth  = $options['tyrewidth'];
        $this->tyreseries = $options['tyreseries'];
        $this->radius     = $options['radius'];
        $this->rimwidth   = $options['rimwidth'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $attribute
     * @param $value
     * @param $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): string
    {
        $tyreWidth  = $values[$this->tyrewidth] ?? null;
        $tyreSeries = $values[$this->tyreseries] ?? null;
        $radius     = $values[$this->radius] ?? null;
        $rimWidth   = $values[$this->rimwidth] ?? null;

        $diskName = null;
        if ($rimWidth || $radius) {
            $diskName = sprintf(
                '%sJ × %s',
                $rimWidth ? $rimWidth : '?',
                $radius ? $radius : '??'
            );
        }

        $tyreName = null;
        if ($tyreWidth || $tyreSeries || $radius) {
            $tyreName = sprintf(
                '%s/%s R%s',
                $tyreWidth ? $tyreWidth : '???',
                $tyreSeries ? $tyreSeries : '??',
                $radius ? $radius : '??'
            );
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $view->escapeHtml($diskName) . '<br />' . $view->escapeHtml($tyreName);
    }
}
