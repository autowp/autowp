<?php

class Project_Spec_Table_Value_EnginePlacement
{
    protected $_placement;
    protected $_orientation;

    public function __construct(array $options)
    {
        $this->_placement = $options['placement'];
        $this->_orientation = $options['orientation'];
    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $placement = isset($values[$this->_placement]) ? $values[$this->_placement] : null;
        $orientation = isset($values[$this->_orientation]) ? $values[$this->_orientation] : null;

        $array = array();
        if ($placement) {
            $array[] = $placement;
        }
        if ($orientation) {
            $array[] = $orientation;
        }

        return implode(', ', $array);
    }
}