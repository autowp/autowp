<?php

namespace Application\Spec\Table\Value;

use Zend\View\Renderer\PhpRenderer;

class EngineConfiguration
{
    protected $cylindersCount;
    protected $cylindersLayout;
    protected $valvesCount;

    public function __construct(array $options)
    {
        $this->cylindersCount = $options['cylindersCount'];
        $this->cylindersLayout = $options['cylindersLayout'];
        $this->valvesCount = $options['valvesCount'];
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
        $cylinders = isset($values[$this->cylindersCount]) ? $values[$this->cylindersCount] : null;
        $layout = isset($values[$this->cylindersLayout]) ? $values[$this->cylindersLayout] : null;
        $valves = isset($values[$this->valvesCount]) ? $values[$this->valvesCount] : null;

        if ($layout) {
            if ($cylinders) {
                $result = $layout.$cylinders;
            } else {
                $result = $layout.'?';
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
