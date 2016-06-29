<?php

use Zend\View\Renderer\PhpRenderer;

class Project_Spec_Table_Value_Default
{
    public function render(Zend_View_Abstract $view, $attribute, $value, $values)
    {
        if ($value === null) {
            return '';
        }

        $html = $view->escape($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            $html .=
                ' <span class="unit" title="' . $view->escape($attribute['unit']['name']) . '">' .
                    $view->escape($attribute['unit']['abbr']) .
                '</span>';
        }

        return $html;
    }

    public function render2(PhpRenderer $view, $attribute, $value, $values)
    {
        if ($value === null) {
            return '';
        }

        $html = $view->escapeHtml($value);
        if (isset($attribute['unit']) && $attribute['unit']) {
            $html .=
                ' <span class="unit" title="' . $view->escapeHtmlAttr($attribute['unit']['name']) . '">' .
                    $view->escapeHtml($attribute['unit']['abbr']) .
                '</span>';
        }

        return $html;
    }
}