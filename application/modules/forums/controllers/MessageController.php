<?php
class Forums_MessageController extends My_Controller_Action
{
    public function deleteAction()
    {
        if (!$this->user) {
            return $this->_forward('forbidden', 'error');
        }
            
        // определяем является ли пользователь администратором форума
        $forumAdmin = $this->_helper->acl()->isAllowed($this->user->role, 'forums', 'moderate');
        if (!$forumAdmin) {
            return $this->_forward('forbidden', 'error');
        }
        
        $messages = new Forums_Messages();
        
        $message = $messages->find($this->_getParam('message_id'))->current();
        if (!$message) {
            return $this->_forward('notfound', 'error');
        }
            
        $message->status = Forums_Messages::STATUS_DELETED;
        $message->deleted_by = $this->user->id;
        $message->save();
        
        return $this->_redirect($this->_helper->url->url(array(
            'module'     => 'forums',
            'controller' => 'topic',
            'action'     => 'topic',
            'topic_id'   => $message->topic_id
        )));
    }
    
    public function restoreAction()
    {
        if (!$this->user) {
            return $this->_forward('forbidden', 'error');
        }
            
        // определяем является ли пользователь администратором форума
        $forumAdmin = $this->_helper->acl()->isAllowed($this->user->role, 'forums', 'moderate');
        if (!$forumAdmin) {
            return $this->_forward('forbidden', 'error');
        }
        
        $messages = new Forums_Messages();
        
        $message = $messages->find($this->_getParam('message_id'))->current();
        if (!$message) {
            return $this->_forward('notfound', 'error');
        }
            
        $message->status = Forums_Messages::STATUS_NORMAL;
        $message->save();
        
        return $this->_redirect($this->_helper->url->url(array(
            'module'     => 'forums',
            'controller' => 'topic',
            'action'     => 'topic',
            'topic_id'   => $message->topic_id
        )));
    }
}