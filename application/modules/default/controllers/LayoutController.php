<?php

use Application\Model\Message;

class LayoutController extends Zend_Controller_Action
{
    public function flashMessagesAction()
    {
        $this->view->flashMessages = $this->_helper->flashMessenger->getMessages();
    }

    public function sidebarRightAction()
    {
        if ($this->_helper->user()->logedIn()) {
            $mModel = new Message();
            $count = $mModel->getNewCount($this->_helper->user()->get()->id);

            $this->view->newPersonalMessages = $count;
        }
    }
}