<?php

use Application\Model\Message;

use Application\Model\DbTable\Museum;

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

    private function _nextMessageTime()
    {
        return $this->_helper->user()->get()->nextMessageTime();
    }

    private function _needWait()
    {
        if ($nextMessageTime = $this->_nextMessageTime()) {
            return $nextMessageTime->isLater(Zend_Date::now());
        }

        return false;
    }

    public function confirmAction()
    {
        if (!$this->_canAddComments()) {
            return $this->_forward('forbidden', 'error');
        }

        $request = $this->getRequest();
        $item_id = (int)$this->_getParam('item_id');
        $type_id = (int)$this->_getParam('type_id');

        $form = $this->_comments->getAddForm(array(
            'action' => $this->_helper->url->url(array(
                'module'     => 'default',
                'controller' => 'comments',
                'action'     => 'add',
                'type_id'    => $type_id,
                'item_id'    => $item_id
            ), 'default', true)
        ));
        $form->isValid($request->getPost());

        $this->view->form = $form;
        $this->view->nextMessageTime = $this->_nextMessageTime();
    }

    private function _messageUrl($typeId, $object)
    {
        switch ($typeId) {
            case Comment_Message::PICTURES_TYPE_ID:
                $url = $this->view->pic($object)->url();
                break;

            case Comment_Message::TWINS_TYPE_ID:
                $url = $this->_helper->url->url(array(
                    'module'         => 'default',
                    'controller'     => 'twins',
                    'action'         => 'group',
                    'twins_group_id' => $object->id
                ), 'twins', true);
                break;

            case Comment_Message::VOTINGS_TYPE_ID:
                $url = $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'voting',
                    'action'     => 'voting',
                    'id'         => $object->id
                ), 'default', true);
                break;

            case Comment_Message::ARTICLES_TYPE_ID:
                $url = $this->_helper->url->url(array(
                    'module'          => 'default',
                    'controller'      => 'articles',
                    'action'          => 'article',
                    'article_catname' => $object->catname
                ), 'articles', true);
                break;

            case Comment_Message::MUSEUMS_TYPE_ID:
                $url = $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'museums',
                    'action'     => 'museum',
                    'id'         => $object->id
                ), 'default', true);
                break;

            default:
                throw new Exception('Unknown type_id');
        }

        return $url;
    }

    public function addAction()
    {
        if (!$this->_canAddComments()) {
            return $this->_forward('forbidden', 'error');
        }

        $request = $this->getRequest();
        $item_id = (int)$this->_getParam('item_id');
        $type_id = (int)$this->_getParam('type_id');

        if ($this->_needWait()) {
            return $this->_forward('confirm');
        }

        $form = $this->_comments->getAddForm(array(
            'action' => $this->_helper->url->url(array(
                'module'     => 'default',
                'controller' => 'comments',
                'action'     => 'add',
                'type_id'    => $type_id,
                'item_id'    => $item_id
            ), 'default', true)
        ));

        if ($form->isValid($request->getPost())) {

            $values = $form->getValues();


            $object = null;
            switch ($type_id) {
                case Comment_Message::PICTURES_TYPE_ID:
                    $pictures = $this->_helper->catalogue()->getPictureTable();
                    $object = $pictures->find($item_id)->current();
                    break;

                case Comment_Message::TWINS_TYPE_ID:
                    $twinsGroups = new Twins_Groups();
                    $object = $twinsGroups->find($item_id)->current();
                    break;

                case Comment_Message::VOTINGS_TYPE_ID:
                    $vTable = new Voting();
                    $object = $vTable->find($item_id)->current();
                    break;

                case Comment_Message::ARTICLES_TYPE_ID:
                    $articles = new Articles();
                    $object = $articles->find($item_id)->current();
                    break;

                case Comment_Message::MUSEUMS_TYPE_ID:
                    $museums = new Museum();
                    $object = $museums->find($item_id)->current();
                    break;

                default:
                    throw new Exception('Unknown type_id');
            }

            if (!$object) {
                return $this->_forward('notfound', 'error');
            }

            $user = $this->_helper->user()->get();

            $moderatorAttention = false;
            if ($this->_helper->user()->isAllowed('comment', 'moderator-attention')) {
                $moderatorAttention = (bool)$values['moderator_attention'];
            }

            $messageId = $this->_comments->add(array(
                'typeId'             => $type_id,
                'itemId'             => $item_id,
                'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
                'authorId'           => $user->id,
                'message'            => $values['message'],
                'ip'                 => $request->getServer('REMOTE_ADDR'),
                'moderatorAttention' => $moderatorAttention
            ));

            if (!$messageId) {
                throw new Exception("Message add fails");
            }

            $user->last_message_time = new Zend_Db_Expr('NOW()');
            $user->save();

            if ($this->_helper->user()->inheritsRole('moder')) {
                if ($values['parent_id'] && $values['resolve']) {
                    $this->_comments->completeMessage($values['parent_id']);
                }
            }

            if ($values['parent_id']) {
                $authorId = $this->_comments->getMessageAuthorId($values['parent_id']);
                if ($authorId && ($authorId != $user->id)) {
                    $userTable = new Users();
                    $parentMessageAuthor = $userTable->find($authorId)->current();
                    if ($parentMessageAuthor && !$parentMessageAuthor->deleted) {
                        $url = $this->view->serverUrl($this->_messageUrl($type_id, $object) . '#msg' . $messageId);
                        $moderUrl = $this->view->serverUrl($this->_helper->url->url(array(
                            'module'     => 'default',
                            'controller' => 'users',
                            'action'     => 'user',
                            'identity'   => $user->identity,
                            'user_id'    => $user->id
                        ), 'users', true));
                        $message = sprintf(
                            "%s ответил на ваше сообщение\n%s",
                            $moderUrl, $url
                        );
                        $mModel = new Message();
                        $mModel->send(null, $parentMessageAuthor->id, $message);
                    }
                }
            }

            $backUrl = $this->_messageUrl($type_id, $object);

            return $this->_redirect($backUrl);
        }

        $this->view->form = $form;
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

    public function deleteAction()
    {
        if (!$this->_helper->user()->isAllowed('comment', 'remove')) {
            return $this->_forward('forbidden', 'error');
        }

        $success = $this->_comments->deleteMessage(
            $this->_getParam('comment_id'),
            $this->_helper->user()->get()->id
        );

        return $this->_helper->json(array(
            'ok' => $success,
            'result' => array(
                'ok' => $success
            )
        ));
    }

    public function restoreAction()
    {
        if (!$this->_helper->user()->isAllowed('comment', 'remove')) {
            return $this->_forward('forbidden', 'error');
        }

        $comment = $this->_comments->restoreMessage($this->_getParam('comment_id'));

        return $this->_helper->json(array(
            'ok' => true,
            'result' => array(
                'ok' => true
            )
        ));
    }

    public function voteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forward('forbidden', 'error');
        }

        if (!$this->_helper->user()->logedIn()) {
            return $this->_forward('forbidden', 'error');
        }

        $user = $this->_helper->user()->get();

        if ($user->votes_left <= 0) {
            return $this->_helper->json(array(
                'ok'    => false,
                'error' => 'На сегодня у вас не осталось больше голосов'
            ));
        }

        $result = $this->_comments->voteMessage(
            $this->_getParam('id'),
            $user->id,
            $this->_getParam('vote')
        );
        if (!$result['success']) {
            return $this->_helper->json(array(
                'ok'    => false,
                'error' => $result['error']
            ));
        }

        $user->votes_left = new Zend_Db_Expr('votes_left - 1');
        $user->save();

        return $this->_helper->json(array(
            'ok'   => true,
            'vote' => $result['vote']
        ));
    }

    public function votesAction()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();
        }

        $result = $this->_comments->getVotes($this->_getParam('id'));
        if (!$result) {
            return $this->_forward('notfound', 'error');
        }

        $this->view->assign($result);
    }
}