<?php

use Zend\View\Renderer\PhpRenderer;

class Project_Spec_Table_Value_BootVolume
{
    protected $_min;
    protected $_max;

    public function __construct(array $options)
    {
        $this->_min = $options['min'];
        $this->_max = $options['max'];
    }

    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        $min = isset($values[$this->_min]) ? $values[$this->_min] : null;
        $max = isset($values[$this->_max]) ? $values[$this->_max] : null;

        if ($min && $max && ($min != $max)) {
            $html = $view->escape($min) . '&ndash;' . $max;
        } elseif ($min) {
            $html = $view->escape($min);
        } else {
            $html = '';
        }

        if ($html && isset($attribute['unit']) && $attribute['unit']) {
            $html .=
                ' <span class="unit" title="' . $view->escape($attribute['unit']['name']) . '">' .
                    $view->escape($attribute['unit']['abbr']) .
                '</span>';
        }

        return $html;
    }

    public function render2(PhpRenderer $view, $attribute, $value, $values)
    {
        $min = isset($values[$this->_min]) ? $values[$this->_min] : null;
        $max = isset($values[$this->_max]) ? $values[$this->_max] : null;

        if ($min && $max && ($min != $max)) {
            $html = $view->escapeHtml($min) . '&ndash;' . $max;
        } elseif ($min) {
            $html = $view->escapeHtml($min);
        } else {
            $html = '';
        }

        if ($html && isset($attribute['unit']) && $attribute['unit']) {
            $html .=
            ' <span class="unit" title="' . $view->escapeHtmlAttr($attribute['unit']['name']) . '">' .
                $view->escapeHtml($attribute['unit']['abbr']) .
            '</span>';
        }

        return $html;
    }
}