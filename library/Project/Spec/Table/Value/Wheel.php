<?php

class Project_Spec_Table_Value_Wheel
{
    protected $_tyrewidth;
    protected $_tyreseries;
    protected $_radius;
    protected $_rimwidth;

    public function __construct(array $options)
    {
        $this->_tyrewidth = $options['tyrewidth'];
        $this->_tyreseries = $options['tyreseries'];
        $this->_radius = $options['radius'];
        $this->_rimwidth = $options['rimwidth'];
    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $tyreWidth = isset($values[$this->_tyrewidth]) ? $values[$this->_tyrewidth] : null;
        $tyreSeries = isset($values[$this->_tyreseries]) ? $values[$this->_tyreseries] : null;
        $radius = isset($values[$this->_radius]) ? $values[$this->_radius] : null;
        $rimWidth = isset($values[$this->_rimwidth]) ? $values[$this->_rimwidth] : null;

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

        return $view->escape($diskName) . '<br />' . $view->escape($tyreName);
    }
}