<?php

use Zend\View\Renderer\PhpRenderer;

class Project_Spec_Table_Value_Gearbox
{
    protected $_type;
    protected $_gears;
    protected $_name;

    public function __construct(array $options)
    {
        $this->_type = $options['type'];
        $this->_gears = $options['gears'];
        $this->_name = $options['name'];
    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $type = isset($values[$this->_type]) ? $values[$this->_type] : null;
        $gears = isset($values[$this->_gears]) ? $values[$this->_gears] : null;
        $name = isset($values[$this->_name]) ? $values[$this->_name] : null;

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

        return $view->escape($result);
    }

    public function render2(PhpRenderer $view, $attribute, $value, $values)
    {
        $type = isset($values[$this->_type]) ? $values[$this->_type] : null;
        $gears = isset($values[$this->_gears]) ? $values[$this->_gears] : null;
        $name = isset($values[$this->_name]) ? $values[$this->_name] : null;

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

        return $view->escapeHtml($result);
    }
}