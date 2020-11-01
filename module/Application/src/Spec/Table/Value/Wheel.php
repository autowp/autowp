<?php

namespace Application\Spec\Table\Value;

use ArrayAccess;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Renderer\PhpRenderer;

use function sprintf;

class Wheel
{
    protected int $tyrewidth;
    protected int $tyreseries;
    protected int $radius;
    protected int $rimwidth;

    public function __construct(array $options)
    {
        $this->tyrewidth  = $options['tyrewidth'];
        $this->tyreseries = $options['tyreseries'];
        $this->radius     = $options['radius'];
        $this->rimwidth   = $options['rimwidth'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array|ArrayAccess $attribute
     * @param mixed             $value
     * @param mixed             $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): ?string
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

        /** @var EscapeHtml $escapeHtmlHelper */
        $escapeHtmlHelper = $view->getHelperPluginManager()->get('escapeHtml');

        return $escapeHtmlHelper($diskName) . '<br />' . $escapeHtmlHelper($tyreName);
    }
}
