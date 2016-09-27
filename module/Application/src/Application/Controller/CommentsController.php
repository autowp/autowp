<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\DbTable\Museum;
use Application\Model\DbTable\Voting;
use Application\Model\Message;

use Articles;
use Comment_Message;
use Comments;
use Twins_Groups;
use Users;

use DateTime;
use Exception;

use Zend_Db_Expr;

class CommentsController extends AbstractRestfulController
{
    private $comments = null;

    private $form = null;

    public function __construct($form)
    {
        $this->form = $form;
        $this->comments = new Comments();
    }

    private function canAddComments()
    {
        return $this->user()->logedIn();
    }

    private function nextMessageTime()
    {
        return $this->user()->get()->nextMessageTime();
    }

    private function needWait()
    {
        if ($nextMessageTime = $this->nextMessageTime()) {
            return $nextMessageTime > new DateTime();
        }

        return false;
    }

    public function confirmAction()
    {
        if (!$this->canAddComments()) {
            return $this->forbiddenAction();
        }

        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        $form = $this->getAddForm([
            'action' => $this->url()->fromRoute('comments/add', [
                'type_id' => $typeId,
                'item_id' => $itemId
            ])
        ]);
        $form->setData($this->params()->fromPost());
        $form->isValid();

        return [
            'form'            => $form,
            'nextMessageTime' => $this->nextMessageTime()
        ];
    }

    private function messageUrl($typeId, $object, $canonical)
    {
        switch ($typeId) {
            case Comment_Message::PICTURES_TYPE_ID:
                $url = $this->pic()->href($object, [
                    'canonical' => $canonical
                ]);
                break;

            case Comment_Message::TWINS_TYPE_ID:
                $url = $this->url()->fromRoute('twins/group', [
                    'id' => $object->id
                ], [
                    'force_canonical' => $canonical
                ]);
                break;

            case Comment_Message::VOTINGS_TYPE_ID:
                $url = $this->url()->fromRoute('votings/voting', [
                    'id' => $object->id
                ], [
                    'force_canonical' => $canonical
                ]);
                break;

            case Comment_Message::ARTICLES_TYPE_ID:
                $url = $this->url()->fromRoute('articles', [
                    'action'          => 'article',
                    'article_catname' => $object->catname
                ], [
                    'force_canonical' => $canonical
                ]);
                break;

            case Comment_Message::MUSEUMS_TYPE_ID:
                $url = $this->url()->fromRoute('museums/museum', [
                    'id' => $object->id
                ], [
                    'force_canonical' => $canonical
                ]);
                break;

            default:
                throw new Exception('Unknown type_id');
        }

        return $url;
    }

    public function addAction()
    {
        if (!$this->canAddComments()) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        if ($this->needWait()) {
            return $this->forward('confirm');
        }

        $form = $this->getAddForm([
            'action' => $this->url()->fromRoute('comments/add', [
                'type_id' => $typeId,
                'item_id' => $itemId
            ])
        ]);

        $form->setData($this->params()->fromPost());
        if ($form->isValid()) {

            $values = $form->getData();

            $object = null;
            switch ($typeId) {
                case Comment_Message::PICTURES_TYPE_ID:
                    $pictures = $this->catalogue()->getPictureTable();
                    $object = $pictures->find($itemId)->current();
                    break;

                case Comment_Message::TWINS_TYPE_ID:
                    $twinsGroups = new Twins_Groups();
                    $object = $twinsGroups->find($itemId)->current();
                    break;

                case Comment_Message::VOTINGS_TYPE_ID:
                    $vTable = new Voting();
                    $object = $vTable->find($itemId)->current();
                    break;

                case Comment_Message::ARTICLES_TYPE_ID:
                    $articles = new Articles();
                    $object = $articles->find($itemId)->current();
                    break;

                case Comment_Message::MUSEUMS_TYPE_ID:
                    $museums = new Museum();
                    $object = $museums->find($itemId)->current();
                    break;

                default:
                    throw new Exception('Unknown type_id');
            }

            if (!$object) {
                return $this->notFoundAction();
            }

            $user = $this->user()->get();

            $moderatorAttention = false;
            if ($this->user()->isAllowed('comment', 'moderator-attention')) {
                $moderatorAttention = (bool)$values['moderator_attention'];
            }

            $messageId = $this->comments->add([
                'typeId'             => $typeId,
                'itemId'             => $itemId,
                'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
                'authorId'           => $user->id,
                'message'            => $values['message'],
                'ip'                 => $request->getServer('REMOTE_ADDR'),
                'moderatorAttention' => $moderatorAttention
            ]);

            if (!$messageId) {
                throw new Exception("Message add fails");
            }

            $user->last_message_time = new Zend_Db_Expr('NOW()');
            $user->save();

            if ($this->user()->inheritsRole('moder')) {
                if ($values['parent_id'] && $values['resolve']) {
                    $this->comments->completeMessage($values['parent_id']);
                }
            }

            if ($values['parent_id']) {
                $authorId = $this->comments->getMessageAuthorId($values['parent_id']);
                if ($authorId && ($authorId != $user->id)) {
                    $userTable = new Users();
                    $parentMessageAuthor = $userTable->find($authorId)->current();
                    if ($parentMessageAuthor && !$parentMessageAuthor->deleted) {
                        $url = $this->messageUrl($typeId, $object, true) . '#msg' . $messageId;
                        $moderUrl = $this->url()->fromRoute('users/user', [
                            'user_id' => $user->identity ? $user->identity : 'user' . $user->id,
                        ], [
                            'force_canonical' => true
                        ]);
                        $message = sprintf(
                            "%s ответил на ваше сообщение\n%s",
                            $moderUrl, $url
                        );
                        $mModel = new Message();
                        $mModel->send(null, $parentMessageAuthor->id, $message);
                    }
                }
            }

            $backUrl = $this->messageUrl($typeId, $object, false);

            return $this->redirect()->toUrl($backUrl);
        }

        return [
            'form' => $form
        ];
    }

    public function commentsAction()
    {
        $type = (int)$this->params('type');
        $item = (int)$this->params('item_id');

        $user = $this->user()->get();

        $comments = $this->comments->get($type, $item, $user);

        if ($user) {
            $this->comments->updateTopicView($type, $item, $user->id);
        }

        $canAddComments = $this->canAddComments();
        $canRemoveComments = $this->user()->isAllowed('comment', 'remove');

        $form = null;
        if ($canAddComments) {
            $form = $this->getAddForm([
                'canModeratorAttention' => $this->user()->isAllowed('comment', 'moderator-attention'),
                'action' => $this->url()->fromRoute('comments/add', [
                    'type_id' => $type,
                    'item_id' => $item
                ])
            ]);
        }

        return [
            'form'              => $form,
            'comments'          => $comments,
            'itemId'            => $item,
            'type'              => $type,
            'canAddComments'    => $canAddComments,
            'canRemoveComments' => $canRemoveComments,
        ];
    }

    public function deleteAction()
    {
        if (!$this->user()->isAllowed('comment', 'remove')) {
            return $this->forbiddenAction();
        }

        $success = $this->comments->deleteMessage(
            $this->params()->fromPost('comment_id'),
            $this->user()->get()->id
        );

        return new JsonModel([
            'ok' => $success,
            'result' => [
                'ok' => $success
            ]
        ]);
    }

    public function restoreAction()
    {
        if (!$this->user()->isAllowed('comment', 'remove')) {
            return $this->forbiddenAction();
        }

        $this->comments->restoreMessage($this->params()->fromPost('comment_id'));

        return new JsonModel([
            'ok' => true,
            'result' => [
                'ok' => true
            ]
        ]);
    }

    public function voteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();
        if (!$user) {
            return $this->forbiddenAction();
        }

        if ($user->votes_left <= 0) {
            return new JsonModel([
                'ok'    => false,
                'error' => $this->translate('comments/vote/no-more-votes')
            ]);
        }

        $result = $this->comments->voteMessage(
            $this->params()->fromPost('id'),
            $user->id,
            $this->params()->fromPost('vote')
        );
        if (!$result['success']) {
            return new JsonModel([
                'ok'    => false,
                'error' => $result['error']
            ]);
        }

        $user->votes_left = new Zend_Db_Expr('votes_left - 1');
        $user->save();

        return new JsonModel([
            'ok'   => true,
            'vote' => $result['vote']
        ]);
    }

    public function votesAction()
    {
        $result = $this->comments->getVotes($this->params()->fromQuery('id'));
        if (!$result) {
            return $this->notFoundAction();
        }

        $viewModel = new ViewModel($result);
        $viewModel->setTerminal($this->getRequest()->isXmlHttpRequest());

        return $viewModel;
    }

    private function getAddForm(array $options)
    {
        $defaults = [
            'canModeratorAttention' => true, // TODO: use that parameter
            'action'                => null
        ];

        $options = array_replace($defaults, $options);

        $this->form->setAttribute('action', $options['action']);

        return $this->form;
    }
}
