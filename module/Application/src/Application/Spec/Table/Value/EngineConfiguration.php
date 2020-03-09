<?php

namespace Application\Spec\Table\Value;

use Laminas\View\Renderer\PhpRenderer;

class EngineConfiguration
{
    protected $cylindersCount;
    protected $cylindersLayout;
    protected $valvesCount;

    public function __construct(array $options)
    {
        $this->cylindersCount  = $options['cylindersCount'];
        $this->cylindersLayout = $options['cylindersLayout'];
        $this->valvesCount     = $options['valvesCount'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $attribute
     * @param $value
     * @param $values
     */
    public function render(PhpRenderer $view, $attribute, $value, $values): string
    {
        $cylinders = $values[$this->cylindersCount] ?? null;
        $layout    = $values[$this->cylindersLayout] ?? null;
        $valves    = $values[$this->valvesCount] ?? null;

        if ($layout) {
            if ($cylinders) {
                $result = $layout . $cylinders;
            } else {
                $result = $layout . '?';
            }
        } else {
            if ($cylinders) {
                $result = $cylinders;
            } else {
                $result = '';
            }
        }
        if ($valves) {
            $result .= '/' . $valves;
        }

        return $result;
    }
}
