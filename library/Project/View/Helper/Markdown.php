<?php

use \Michelf\Markdown;

class Project_View_Helper_Markdown extends Zend_View_Helper_Abstract
{
    public function markdown($text)
    {
        return Markdown::defaultTransform($text);
    }
}