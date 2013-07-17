<?php

class LayoutController extends Zend_Controller_Action
{
    public function flashMessagesAction()
    {
        $this->view->flashMessages = $this->_helper->flashMessenger->getMessages();
    }

    public function sidebarRightAction()
    {
        if ($this->_helper->user()->logedIn()) {
            $pmTable = new Personal_Messages();
            $pmAdapter = $pmTable->getAdapter();

            $count = $pmAdapter->fetchOne(
                $pmAdapter->select()
                    ->from($pmTable->info('name'), array('count(1)'))
                    ->where('to_user_id = ?', $this->_helper->user()->get()->id)
                    ->where('NOT readen')
            );

            $this->view->newPersonalMessages = $count;
        }
    }
}