<?php

use Autowp\UserText\Renderer;

class Project_View_Helper_UserText extends Zend_View_Helper_Abstract
{
    public function userText($text)
    {
        $renderer = new Renderer($this->view);
        
        return $renderer->render($text);
    }
}