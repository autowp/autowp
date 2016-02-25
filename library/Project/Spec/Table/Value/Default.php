<?php

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
}