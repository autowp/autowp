<?php

class Project_Controller_Action_Helper_TextStorage
    extends Zend_Controller_Action_Helper_Abstract
{
    private $_textStorage;

    public function __construct()
    {
        $front = Zend_Controller_Front::getInstance();

        $this->_textStorage = $front
            ->getParam('bootstrap')
            ->getResource('textstorage');
    }

    public function direct()
    {
        return $this->_textStorage;
    }
}