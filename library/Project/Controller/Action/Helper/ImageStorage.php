<?php

class Project_Controller_Action_Helper_ImageStorage
    extends Zend_Controller_Action_Helper_Abstract
{
    private $_imageStorage;

    public function __construct()
    {
        $front = Zend_Controller_Front::getInstance();

        $this->_imageStorage = $front
            ->getParam('bootstrap')
            ->getResource('imagestorage');

        $this->_imageStorage->setForceHttps($front->getRequest()->isSecure());
    }

    public function direct()
    {
        return $this->_imageStorage;
    }
}