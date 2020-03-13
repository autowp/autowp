<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Autowp\Votings\Votings;
use DateTime;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\Request;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function is_numeric;
use function sprintf;
use function strlen;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class CommentController extends AbstractRestfulController
{
    private Comments $comments;

    private AbstractRestHydrator $hydrator;

    private TableGateway $userTable;

    private InputFilter $postInputFilter;

    private User $userModel;

    private HostManager $hostManager;

    private MessageService $message;

    private Picture $picture;

    private Item $item;

    private Votings $votings;

    private TableGateway $articleTable;

    private Forums $forums;

    private InputFilter $listInputFilter;

    private InputFilter $publicListInputFilter;

    private InputFilter $putInputFilter;

    private InputFilter $getInputFilter;

    public function __construct(
        Comments $comments,
        AbstractRestHydrator $hydrator,
        TableGateway $userTable,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        InputFilter $postInputFilter,
        InputFilter $putInputFilter,
        InputFilter $getInputFilter,
        User $userModel,
        HostManager $hostManager,
        MessageService $message,
        Picture $picture,
        Item $item,
        Votings $votings,
        TableGateway $articleTable,
        Forums $forums
    ) {
        $this->comments              = $comments;
        $this->hydrator              = $hydrator;
        $this->userTable             = $userTable;
        $this->listInputFilter       = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->postInputFilter       = $postInputFilter;
        $this->putInputFilter        = $putInputFilter;
        $this->getInputFilter        = $getInputFilter;
        $this->userModel             = $userModel;
        $this->hostManager           = $hostManager;
        $this->message               = $message;
        $this->picture               = $picture;
        $this->item                  = $item;
        $this->votings               = $votings;
        $this->articleTable          = $articleTable;
        $this->forums                = $forums;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function subscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $itemId = (int) $this->params('item_id');
        $typeId = (int) $this->params('type_id');

        switch ($this->getRequest()->getMethod()) {
            case Request::METHOD_POST:
            case Request::METHOD_PUT:
                $this->comments->service()->subscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true,
                ]);

            case Request::METHOD_DELETE:
                $this->comments->service()->unSubscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true,
                ]);
        }

        return $this->notFoundAction();
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        $isModer = $this->user()->inheritsRole('moder');

        $inputFilter = $isModer ? $this->listInputFilter : $this->publicListInputFilter;

        $inputFilter->setData($this->params()->fromQuery());

        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $values = $inputFilter->getValues();

        $options = [
            'order' => 'comment_message.datetime DESC',
        ];

        if ($values['item_id']) {
            $options['item_id'] = $values['item_id'];
        }

        if ($values['type_id']) {
            $options['type'] = $values['type_id'];
        }

        if ($values['parent_id']) {
            $options['parent_id'] = $values['parent_id'];
        }

        if ($values['no_parents']) {
            $options['no_parents'] = $values['no_parents'];
        }

        if ($values['user_id']) {
            $options['user'] = $values['user_id'];
        }

        switch ($values['order']) {
            case 'vote_desc':
                $options['order'] = ['comment_message.vote DESC', 'comment_message.datetime DESC'];
                break;
            case 'vote_asc':
                $options['order'] = ['comment_message.vote ASC', 'comment_message.datetime DESC'];
                break;
            case 'date_desc':
                $options['order'] = 'comment_message.datetime DESC';
                break;
            case 'date_asc':
            default:
                $options['order'] = 'comment_message.datetime ASC';
                break;
        }

        if ($isModer) {
            if ($values['user']) {
                if (! is_numeric($values['user'])) {
                    $userRow = $this->userTable->select([
                        'identity' => $values['user'],
                    ])->current();
                    if ($userRow) {
                        $values['user'] = $userRow['id'];
                    }
                }

                $options['user'] = $values['user'];
            }

            if (strlen($values['moderator_attention'])) {
                $options['attention'] = $values['moderator_attention'];
            }

            if ($values['pictures_of_item_id']) {
                $options['type']     = Comments::PICTURES_TYPE_ID;
                $options['callback'] = function (Sql\Select $select) use ($values) {
                    $select
                        ->join('pictures', 'comment_message.item_id = pictures.id', [])
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                        ->where(['item_parent_cache.parent_id' => $values['pictures_of_item_id']]);
                };
            }
        } else {
            if (! $values['item_id'] && ! $values['user_id']) {
                return new ApiProblemResponse(
                    new ApiProblem(
                        400,
                        'Data is invalid. Check `detail`.',
                        null,
                        'Validation error',
                        [
                            'invalid_params' => [
                                'item_id' => [
                                    'invalid' => 'item_id or user_id is required',
                                ],
                            ],
                        ]
                    )
                );
            }
        }

        $paginator = $this->comments->service()->getMessagesPaginator($options);

        $limit = null;
        if (strlen($values['limit']) > 0) {
            $limit = (int) $values['limit'];
            $limit = $limit >= 0 ? $limit : 0;
        }

        $paginator
            ->setItemCountPerPage($limit ? $limit : 50000)
            ->setCurrentPageNumber($values['page']);

        $result = [
            'paginator' => get_object_vars($paginator->getPages()),
        ];

        if ($limit > 0 || $limit === null) {
            $this->hydrator->setOptions([
                'fields'   => $values['fields'],
                'language' => $this->language(),
                'user_id'  => $user ? $user['id'] : null,
            ]);

            $comments = [];
            foreach ($paginator->getCurrentItems() as $commentRow) {
                $comments[] = $this->hydrator->extract($commentRow);
            }

            if ($user && $values['item_id'] && $values['type_id']) {
                $this->comments->service()->setSubscriptionSent(
                    $values['type_id'],
                    $values['item_id'],
                    $user['id'],
                    false
                );
            }

            $result['items'] = $comments;
        }

        return new JsonModel($result);
    }

    private function nextMessageTime(): ?DateTime
    {
        $user = $this->user()->get();
        if (! $user) {
            return null;
        }

        return $this->userModel->getNextMessageTime($user['id']);
    }

    private function needWait(): bool
    {
        $nextMessageTime = $this->nextMessageTime();
        if ($nextMessageTime) {
            return $nextMessageTime > new DateTime();
        }

        return false;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @return ViewModel|ResponseInterface|array
     */
    public function postAction()
    {
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();

        $itemId = (int) $data['item_id'];
        $typeId = (int) $data['type_id'];

        if ($this->needWait()) {
            return new ApiProblemResponse(
                new ApiProblem(
                    400,
                    'Data is invalid. Check `detail`.',
                    null,
                    'Validation error',
                    [
                        'invalid_params' => [
                            'message' => [
                                'invalid' => 'Too often',
                            ],
                        ],
                    ]
                )
            );
        }

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

            case Comments::FORUMS_TYPE_ID:
                $object = $this->forums->getTopicTable()->select(['id' => $itemId])->current();
                break;

            default:
                throw new Exception('Unknown type_id');
        }

        if (! $object) {
            return $this->notFoundAction();
        }

        $moderatorAttention = false;
        if ($this->user()->isAllowed('comment', 'moderator-attention')) {
            $moderatorAttention = (bool) $data['moderator_attention'];
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $ip = $request->getServer('REMOTE_ADDR');
        if (! $ip) {
            $ip = '127.0.0.1';
        }

        $messageId = $this->comments->service()->add([
            'typeId'             => $typeId,
            'itemId'             => $itemId,
            'parentId'           => $data['parent_id'] ? (int) $data['parent_id'] : null,
            'authorId'           => $currentUser['id'],
            'message'            => $data['message'],
            'ip'                 => $ip,
            'moderatorAttention' => $moderatorAttention,
        ]);

        if (! $messageId) {
            throw new Exception("Message add fails");
        }

        $this->userModel->getTable()->update([
            'last_message_time' => new Sql\Expression('NOW()'),
        ], [
            'id' => $currentUser['id'],
        ]);

        if ($this->user()->inheritsRole('moder')) {
            if ($data['parent_id'] && $data['resolve']) {
                $this->comments->service()->completeMessage($data['parent_id']);
            }
        }

        if ($typeId === Comments::FORUMS_TYPE_ID) {
            $this->userModel->getTable()->update([
                'forums_messages'   => new Sql\Expression('forums_messages + 1'),
                'last_message_time' => new Sql\Expression('NOW()'),
            ], [
                'id' => $currentUser['id'],
            ]);
        }

        if ($data['parent_id']) {
            $authorId = $this->comments->service()->getMessageAuthorId($data['parent_id']);
            if ($authorId && ($authorId !== $currentUser['id'])) {
                $parentMessageAuthor = $this->userModel->getTable()->select(['id' => (int) $authorId])->current();
                if ($parentMessageAuthor && ! $parentMessageAuthor['deleted']) {
                    $uri = $this->hostManager->getUriByLanguage($parentMessageAuthor['language']);

                    $url      = $this->comments->getMessageUrl($messageId, $uri);
                    $path     = '/users/'
                            . ($currentUser['identity'] ? $currentUser['identity'] : 'user' . $currentUser['id']);
                    $moderUrl = $uri->setPath($path)->toString();
                    $message  = sprintf(
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

        $url = $this->url()->fromRoute('api/comment/item/get', [
            'id' => $messageId,
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function putAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        //TODO: prevent load message from admin forum
        $row = $this->comments->service()->getMessageRow((int) $this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        $data    = (array) $this->processBodyContent($request);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'No fields provided'));
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);
        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $values = $this->putInputFilter->getValues();

        if (array_key_exists('user_vote', $values)) {
            if ($user['votes_left'] <= 0) {
                return new ApiProblemResponse(
                    new ApiProblem(
                        400,
                        'Data is invalid. Check `detail`.',
                        null,
                        'Validation error',
                        [
                            'invalid_params' => [
                                'user_vote' => [
                                    'invalid' => $this->translate('comments/vote/no-more-votes'),
                                ],
                            ],
                        ]
                    )
                );
            }

            $result = $this->comments->service()->voteMessage(
                $row['id'],
                $user['id'],
                $values['user_vote']
            );
            if (! $result['success']) {
                return new ApiProblemResponse(
                    new ApiProblem(
                        400,
                        'Data is invalid. Check `detail`.',
                        null,
                        'Validation error',
                        [
                            'invalid_params' => [
                                'user_vote' => [
                                    'invalid' => $result['error'],
                                ],
                            ],
                        ]
                    )
                );
            }

            $this->userModel->decVotes($user['id']);
        }

        if (array_key_exists('deleted', $values)) {
            if ($this->user()->isAllowed('comment', 'remove')) {
                if ($values['deleted']) {
                    $this->comments->service()->queueDeleteMessage($row['id'], $user['id']);
                } else {
                    $this->comments->service()->restoreMessage($row['id']);
                }
            }
        }

        if (array_key_exists('item_id', $values)) {
            $isForum = $row['type_id'] === Comments::FORUMS_TYPE_ID;
            if ($isForum && $this->user()->isAllowed('forums', 'moderate')) {
                $this->comments->service()->moveMessage($row['id'], $row['type_id'], $values['item_id']);
            }
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getAction()
    {
        $user = $this->user()->get();

        $this->getInputFilter->setData($this->params()->fromQuery());

        if (! $this->getInputFilter->isValid()) {
            return $this->inputFilterResponse($this->getInputFilter);
        }

        $values = $this->getInputFilter->getValues();

        //TODO: prevent load message from admin forum
        $row = $this->comments->service()->getMessageRow((int) $this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'limit'    => $values['limit'],
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }
}
