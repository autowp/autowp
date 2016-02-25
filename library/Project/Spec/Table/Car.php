<?php

class Project_Spec_Table_Car extends Project_Spec_Table_Abstract
{
    protected $_cars;

    public function __construct($cars, $attributes, array $options = array())
    {
        $this->_cars = $cars;
        $this->_attributes = $attributes;
    }

    public function render(Zend_View_Abstract $view)
    {
        return $view->partial('specs.phtml', array(
            'table' => $this,
        ));
    }

    public function getCars()
    {
        return $this->_cars;
    }
}