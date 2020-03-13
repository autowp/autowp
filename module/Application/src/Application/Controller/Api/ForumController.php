<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\Forums\Forums;
use Autowp\User\Controller\Plugin\User as UserModel;
use Autowp\User\Model\User;
use DateTime;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;

/**
 * @method UserModel user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ForumController extends AbstractRestfulController
{
    /** @var Forums */
    private Forums $forums;

    /** @var User */
    private User $userModel;

    /** @var AbstractRestHydrator */
    private AbstractRestHydrator $themeHydrator;

    /** @var AbstractRestHydrator */
    private AbstractRestHydrator $topicHydrator;

    /** @var InputFilter */
    private InputFilter $themeListInputFilter;

    /** @var InputFilter */
    private InputFilter $themeInputFilter;

    /** @var InputFilter */
    private InputFilter $topicListInputFilter;

    /** @var InputFilter */
    private InputFilter $topicGetInputFilter;

    /** @var InputFilter */
    private InputFilter $topicPutInputFilter;

    /** @var InputFilter */
    private InputFilter $topicPostInputFilter;

    public function __construct(
        Forums $forums,
        User $userModel,
        AbstractRestHydrator $themeHydrator,
        AbstractRestHydrator $topicHydrator,
        InputFilter $themeListInputFilter,
        InputFilter $themeInputFilter,
        InputFilter $topicListInputFilter,
        InputFilter $topicGetInputFilter,
        InputFilter $topicPutInputFilter,
        InputFilter $topicPostInputFilter
    ) {
        $this->forums               = $forums;
        $this->userModel            = $userModel;
        $this->themeHydrator        = $themeHydrator;
        $this->topicHydrator        = $topicHydrator;
        $this->themeListInputFilter = $themeListInputFilter;
        $this->themeInputFilter     = $themeInputFilter;
        $this->topicListInputFilter = $topicListInputFilter;
        $this->topicGetInputFilter  = $topicGetInputFilter;
        $this->topicPutInputFilter  = $topicPutInputFilter;
        $this->topicPostInputFilter = $topicPostInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function userSummaryAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'subscriptionsCount' => $this->forums->getSubscribedTopicsCount($user['id']),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getThemesAction()
    {
        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->inheritsRole('moder');

        $this->themeListInputFilter->setData($this->params()->fromQuery());

        if (! $this->themeListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->themeListInputFilter);
        }

        $data = $this->themeListInputFilter->getValues();

        $select = $this->forums->getThemeTable()->getSql()->select();
        $select->order('position');

        if ($data['theme_id']) {
            $select->where(['parent_id' => $data['theme_id']]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $this->themeHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
        ]);

        $items = [];
        foreach ($this->forums->getThemeTable()->selectWith($select) as $row) {
            $items[] = $this->themeHydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getThemeAction()
    {
        $isModerator = $this->user()->inheritsRole('moder');

        $this->themeInputFilter->setData($this->params()->fromQuery());

        if (! $this->themeInputFilter->isValid()) {
            return $this->inputFilterResponse($this->themeInputFilter);
        }

        $data = $this->themeInputFilter->getValues();

        $select = $this->forums->getThemeTable()->getSql()->select()
            ->where(['id' => (int) $this->params('id')]);

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $row = $this->forums->getThemeTable()->selectWith($select)->current();
        if (! $row) {
            return $this->notFoundAction();
        }

        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $this->themeHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
            'topics'   => $data['topics'],
        ]);

        return new JsonModel($this->themeHydrator->extract($row));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getTopicsAction()
    {
        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->inheritsRole('moder');

        $this->topicListInputFilter->setData($this->params()->fromQuery());

        if (! $this->topicListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicListInputFilter);
        }

        $data = $this->topicListInputFilter->getValues();

        $select = $this->forums->getTopicTable()->getSql()->select();
        $select
            ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
            ->where([
                'comment_topic.type_id' => Comments::FORUMS_TYPE_ID,
            ])
            ->order('comment_topic.last_update DESC');

        if (! $isModerator) {
            $select
                ->join('forums_themes', 'forums_topics.theme_id = forums_themes.id', [])
                ->where(['not forums_themes.is_moderator']);
        }

        if ($data['theme_id']) {
            $select->where(['forums_topics.theme_id' => (int) $data['theme_id']]);
        }

        if ($data['subscription']) {
            if (! $userId) {
                return $this->forbiddenAction();
            }
            $select
                ->join(
                    'comment_topic_subscribe',
                    'forums_topics.id = comment_topic_subscribe.item_id',
                    []
                )
                ->where([
                    'comment_topic_subscribe.user_id' => $userId,
                    'comment_topic_subscribe.type_id' => Comments::FORUMS_TYPE_ID,
                ]);
        }

        $this->topicHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
        ]);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->forums->getTopicTable()->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($data['page']);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->topicHydrator->extract($row);
        }

        return new JsonModel([
            'items'     => $items,
            'paginator' => $paginator->getPages(),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function putTopicAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');

        $row = $this->forums->getTopic((int) $this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        $data    = (array) $this->processBodyContent($request);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->topicPutInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'No fields provided'));
        }

        $this->topicPutInputFilter->setValidationGroup($fields);

        $this->topicPutInputFilter->setData($data);
        if (! $this->topicPutInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicPutInputFilter);
        }

        $values = $this->topicPutInputFilter->getValues();

        if (array_key_exists('status', $values) && $forumAdmin) {
            switch ($values['status']) {
                case Forums::STATUS_CLOSED:
                    $this->forums->close($row['id']);
                    break;
                case Forums::STATUS_DELETED:
                    $success = $this->forums->delete($row['id']);
                    if (! $success) {
                        return $this->forbiddenAction();
                    }
                    break;
                case Forums::STATUS_NORMAL:
                    $this->forums->open($row['id']);
                    break;
            }
        }

        if (array_key_exists('subscription', $values)) {
            if ($values['subscription']) {
                if ($this->forums->canSubscribe($row['id'], $user['id'])) {
                    $this->forums->subscribe($row['id'], $user['id']);
                }
            } else {
                if ($this->forums->canUnSubscribe($row['id'], $user['id'])) {
                    $this->forums->unsubscribe($row['id'], $user['id']);
                }
            }
        }

        if (array_key_exists('theme_id', $values) && $forumAdmin) {
            $theme = $this->forums->getTheme($values['theme_id']);

            if ($theme) {
                $this->forums->moveTopic($row['id'], $theme['id']);
            }
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }

    private function needWait(): bool
    {
        $user = $this->user()->get();
        if ($user) {
            $nextMessageTime = $this->userModel->getNextMessageTime($user['id']);
            if ($nextMessageTime) {
                return $nextMessageTime > new DateTime();
            }
        }

        return false;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @return ViewModel|ResponseInterface|array
     */
    public function postTopicAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        $this->topicPostInputFilter->setData($data);

        if (! $this->topicPostInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicPostInputFilter);
        }

        $data = $this->topicPostInputFilter->getValues();

        $theme = $this->forums->getTheme($data['theme_id']);

        if (! $theme || $theme['disable_topics']) {
            return $this->notFoundAction();
        }

        $needWait = $this->needWait();

        if ($needWait) {
            return new ApiProblemResponse(
                new ApiProblem(
                    400,
                    'Data is invalid. Check `detail`.',
                    null,
                    'Validation error',
                    [
                        'invalid_params' => [
                            'text' => [
                                'invalid' => $this->translate('forums/need-wait-to-post'),
                            ],
                        ],
                    ]
                )
            );
        }

        $data['user_id']  = $user['id'];
        $data['theme_id'] = $theme['id'];
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $data['ip'] = $request->getServer('REMOTE_ADDR');

        $topicId = $this->forums->addTopic($data);

        $this->userModel->getTable()->update([
            'forums_topics'     => new Sql\Expression('forums_topics + 1'),
            'forums_messages'   => new Sql\Expression('forums_messages + 1'),
            'last_message_time' => new Sql\Expression('NOW()'),
        ], [
            'id' => $user['id'],
        ]);

        $url = $this->url()->fromRoute('api/forum/topic/item/get', [
            'id' => $topicId,
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getTopicAction()
    {
        $this->topicGetInputFilter->setData($this->params()->fromQuery());

        if (! $this->topicGetInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicGetInputFilter);
        }

        $data = $this->topicGetInputFilter->getValues();

        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->inheritsRole('moder');

        $select = $this->forums->getTopicTable()->getSql()->select();
        $select->where(['forums_topics.id' => (int) $this->params('id')]);

        if (! $isModerator) {
            $select
                ->join('forums_themes', 'forums_topics.theme_id = forums_themes.id', [])
                ->where(['not forums_themes.is_moderator']);
        }

        $row = $this->forums->getTopicTable()->selectWith($select)->current();

        if (! $row) {
            return $this->notFoundAction();
        }

        $this->topicHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
        ]);

        return new JsonModel($this->topicHydrator->extract($row));
    }
}
