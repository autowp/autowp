<?php

class Project_View_Helper_Count extends Zend_View_Helper_HtmlElement
{
    public function count($count, $new = null, array $options = array())
    {
        if ($count == 0) {
            $result = $this->view->translate('count 0');
        } else {
            $result = $count-$new;
            if ($new) {
                $result .= '+<span>'.$new.'</span>';
            }
        }

        return '<span class="count">('.$result.')</span>';
    }
}