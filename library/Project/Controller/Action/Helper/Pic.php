<?php

class Project_Controller_Action_Helper_Pic extends Zend_Controller_Action_Helper_Abstract
{

    public function url($id, $identity)
    {
        $urlHelper = $this->getActionController()->getHelper('Url');

        return $urlHelper->url(array(
            'module'     => 'default',
            'controller' => 'picture',
            'action'     => 'index',
            'picture_id' => $identity ? $identity : $id
        ), 'picture', true);
    }
}