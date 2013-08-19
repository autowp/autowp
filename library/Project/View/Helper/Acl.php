<?php

class Project_View_Helper_Acl extends Zend_View_Helper_Abstract
{
    /**
     * @return Project_Acl
     */
    public function acl()
    {
        return Zend_Controller_Front::getInstance()
            ->getParam('bootstrap')->getResource('acl');
    }
}
