<?php

class CommentsController extends Zend_Controller_Action
{
    private $_comments = null;

    public function init()
    {
        $this->_comments = new Comments();
    }

    private function _canAddComments()
    {
        return $this->_helper->user()->logedIn();
    }

    public function commentsAction()
    {
        $type = (int)$this->_getParam('type');
        $item = (int)$this->_getParam('item_id');

        $user = $this->_helper->user()->get();

        $comments = $this->_comments->get($type, $item, $user);

        if ($user) {
            $this->_comments->updateTopicView($type, $item, $user->id);
        }

        $canAddComments = $this->_canAddComments();
        $canRemoveComments = $this->_helper->user()->isAllowed('comment', 'remove');

        if ($canAddComments) {
            $this->view->form = $this->_comments->getAddForm(array(
                'canModeratorAttention' => $this->_helper->user()->isAllowed('comment', 'moderator-attention'),
                'action' => $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'comments',
                    'action'     => 'add',
                    'type_id'    => $type,
                    'item_id'    => $item
                ), 'default', true)
            ));
        }

        $this->view->assign(array(
            'comments'          => $comments,
            'itemId'            => $item,
            'type'              => $type,
            'canAddComments'    => $canAddComments,
            'canRemoveComments' => $canRemoveComments,
        ));
    }
}