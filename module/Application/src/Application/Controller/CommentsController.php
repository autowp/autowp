<?php

namespace Application\Controller;

use DateTime;
use Exception;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\ViewModel;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Autowp\Votings\Votings;
use Application\Comments;
use Application\Controller\Plugin\ForbiddenAction;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;

/**
 * Class CommentsController
 * @package Application\Controller
 *
 * @method \Autowp\User\Controller\Plugin\User user($user = null)
 * @method ForbiddenAction forbiddenAction()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class CommentsController extends AbstractRestfulController
{
    /**
     * @var Comments
     */
    private $comments = null;

    private $form = null;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Votings
     */
    private $votings;

    /**
     * @var TableGateway
     */
    private $articleTable;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        HostManager $hostManager,
        $form,
        MessageService $message,
        Comments $comments,
        Picture $picture,
        Item $item,
        Votings $votings,
        TableGateway $articleTable,
        User $userModel
    ) {
        $this->hostManager = $hostManager;
        $this->form = $form;
        $this->comments = $comments;
        $this->message = $message;
        $this->picture = $picture;
        $this->item = $item;
        $this->votings = $votings;
        $this->articleTable = $articleTable;
        $this->userModel = $userModel;
    }

    private function canAddComments()
    {
        return $this->user()->logedIn();
    }

    private function nextMessageTime()
    {
        $user = $this->user()->get();
        if (! $user) {
            return null;
        }

        return $this->userModel->getNextMessageTime($user['id']);
    }

    private function needWait()
    {
        $nextMessageTime = $this->nextMessageTime();
        if ($nextMessageTime) {
            return $nextMessageTime > new DateTime();
        }

        return false;
    }

    public function confirmAction()
    {
        if (! $this->canAddComments()) {
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

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function addAction()
    {
        if (! $this->canAddComments()) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        if ($this->needWait()) {
            return $this->forward()->dispatch(self::class, [
                'action'  => 'confirm',
                'item_id' => $itemId,
                'type_id' => $typeId
            ]);
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
                case Comments::PICTURES_TYPE_ID:
                    $object = $this->picture->getRow(['id' => $itemId]);
                    break;

                case Comments::ITEM_TYPE_ID:
                    $object = $this->item->getRow(['id' => $itemId]);
                    break;

                case Comments::VOTINGS_TYPE_ID:
                    $object = $this->votings->isVotingExists($itemId);
                    break;

                case Comments::ARTICLES_TYPE_ID:
                    $object = $this->articleTable->select(['id' => $itemId])->current();
                    break;

                default:
                    throw new Exception('Unknown type_id');
            }

            if (! $object) {
                return $this->notFoundAction();
            }

            $user = $this->user()->get();

            $moderatorAttention = false;
            if ($this->user()->isAllowed('comment', 'moderator-attention')) {
                $moderatorAttention = (bool)$values['moderator_attention'];
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $ip = $request->getServer('REMOTE_ADDR');
            if (! $ip) {
                $ip = '127.0.0.1';
            }

            $messageId = $this->comments->service()->add([
                'typeId'             => $typeId,
                'itemId'             => $itemId,
                'parentId'           => $values['parent_id'] ? $values['parent_id'] : null,
                'authorId'           => $user['id'],
                'message'            => $values['message'],
                'ip'                 => $ip,
                'moderatorAttention' => $moderatorAttention
            ]);

            if (! $messageId) {
                throw new Exception("Message add fails");
            }

            $this->userModel->getTable()->update([
                'last_message_time' => new Sql\Expression('NOW()')
            ], [
                'id' => $user['id']
            ]);

            if ($this->user()->inheritsRole('moder')) {
                if ($values['parent_id'] && $values['resolve']) {
                    $this->comments->service()->completeMessage($values['parent_id']);
                }
            }

            if ($values['parent_id']) {
                $authorId = $this->comments->service()->getMessageAuthorId($values['parent_id']);
                if ($authorId && ($authorId != $user['id'])) {
                    $parentMessageAuthor = $this->userModel->getTable()->select(['id' => (int)$authorId])->current();
                    if ($parentMessageAuthor && ! $parentMessageAuthor['deleted']) {
                        $uri = $this->hostManager->getUriByLanguage($parentMessageAuthor['language']);

                        $url = $this->comments->getMessageUrl($messageId, true, $uri) . '#msg' . $messageId;
                        $moderUrl = $this->url()->fromRoute('ng', ['path' => ''], [
                            'force_canonical' => true,
                            'uri'             => $uri
                        ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);
                        $message = sprintf(
                            $this->translate(
                                'pm/user-%s-replies-to-you-%s',
                                'default',
                                $parentMessageAuthor['language']
                            ),
                            $moderUrl,
                            $url
                        );
                        $this->message->send(null, $parentMessageAuthor['id'], $message);
                    }
                }
            }

            $this->comments->notifySubscribers($messageId);

            $backUrl = $this->comments->getMessageUrl($messageId);

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

        $comments = $this->comments->service()->get($type, $item, $user ? $user['id'] : 0);

        if ($user) {
            $this->comments->service()->updateTopicView($type, $item, $user['id']);
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

    public function votesAction()
    {
        $result = $this->comments->service()->getVotes($this->params()->fromQuery('id'));
        if (! $result) {
            return $this->notFoundAction();
        }

        $viewModel = new ViewModel($result);
        $viewModel->setTerminal(true);

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
