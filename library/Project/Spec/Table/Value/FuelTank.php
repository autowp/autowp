<?php

use Zend\View\Renderer\PhpRenderer;

class Project_Spec_Table_Value_FuelTank
{
    protected $_primary;
    protected $_secondary;

    public function __construct(array $options)
    {
        $this->_primary = $options['primary'];
        $this->_secondary = $options['secondary'];
    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $primary = isset($values[$this->_primary]) ? $values[$this->_primary] : null;
        $secondary = isset($values[$this->_secondary]) ? $values[$this->_secondary] : null;

        $html = $primary;
        if ($secondary) {
            $html .= '+' . $secondary;
        }

        if ($html) {
            $html .= ' <span class="unit" title="">л</span>';
        }

        return $html;
    }

    public function render2(PhpRenderer $view, $attribute, $value, $values)
    {
        $primary = isset($values[$this->_primary]) ? $values[$this->_primary] : null;
        $secondary = isset($values[$this->_secondary]) ? $values[$this->_secondary] : null;

        $html = $primary;
        if ($secondary) {
            $html .= '+' . $secondary;
        }

        if ($html) {
            $html .= ' <span class="unit" title="">л</span>';
        }

        return $html;
    }
}