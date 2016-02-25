<?php

class Project_Spec_Table_Engine extends Project_Spec_Table_Abstract
{
    protected $_engines;

    public function __construct($engines, $attributes, array $options = array())
    {
        $this->_engines = $engines;
        $this->_attributes = $attributes;
    }

    public function render(Zend_View_Abstract $view)
    {
        return $view->partial('specs-engine.phtml', array(
            'table' => $this,
        ));
    }

    public function getEngines()
    {
        return $this->_engines;
    }
}