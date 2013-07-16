<?php
class Forums_MessageController extends My_Controller_Action
{
    protected function _isForumModer()
    {
        return $user && $this->_helper->user()->isAllowed('forums', 'moderate');
    }

    public function deleteAction()
    {
        // определяем является ли пользователь администратором форума
        if (!$this->_isForumModer()) {
            return $this->_forward('forbidden', 'error');
        }

        $messages = new Forums_Messages();

        $message = $messages->find($this->_getParam('message_id'))->current();
        if (!$message) {
            return $this->_forward('notfound', 'error');
        }

        $message->status = Forums_Messages::STATUS_DELETED;
        $message->deleted_by = $this->_helper->user()->get()->id;
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
        // определяем является ли пользователь администратором форума
        if (!$this->_isForumModer()) {
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