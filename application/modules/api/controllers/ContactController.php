<?php

use Application\Model\Contact;

class Api_ContactController extends Zend_Controller_Action
{
    public function userAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forward('forbidden', 'error', 'default');
        }
        
        $currentUser = $this->_helper->user()->get();
        
        if (!$currentUser) {
            return $this->_forward('forbidden', 'error', 'default');
        }
        
        $value = (bool)$this->getParam('value');
        $userId =(int)$this->getParam('user_id');
        
        if ($currentUser->id == $userId) {
            return $this->_forward('forbidden', 'error', 'default');
        }
        
        $contact = new Contact();
        
        $userTable = new Users();
        $user = $userTable->fetchRow([
            'id = ?' => $userId,
            'not deleted'
        ]);
        
        if (!$user) {
            return $this->_forward('notfound', 'error', 'default');
        }
        
        if ($value) {
            $contact->create($currentUser->id, $user->id);
        } else {
            $contact->delete($currentUser->id, $user->id);
        }
        return $this->_helper->json(true);
    }
}