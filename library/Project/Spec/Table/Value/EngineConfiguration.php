<?php

class Project_Spec_Table_Value_EngineConfiguration
{
    protected $_cylindersCount;
    protected $_cylindersLayout;
    protected $_valvesCount;

    public function __construct(array $options)
    {
        $this->_cylindersCount = $options['cylindersCount'];
        $this->_cylindersLayout = $options['cylindersLayout'];
        $this->_valvesCount = $options['valvesCount'];

    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $cylinders = isset($values[$this->_cylindersCount]) ? $values[$this->_cylindersCount] : null;
        $layout = isset($values[$this->_cylindersLayout]) ? $values[$this->_cylindersLayout] : null;
        $valves = isset($values[$this->_valvesCount]) ? $values[$this->_valvesCount] : null;

        if ($layout) {
            if ($cylinders)
                $result = $layout.$cylinders;
            else
                $result = $layout.'?';
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