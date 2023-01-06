<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\Forums\Forums;
use Autowp\User\Controller\Plugin\User as UserModel;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function Autowp\Commons\currentFromResultSetInterface;

/**
 * @method UserModel user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ForumController extends AbstractRestfulController
{
    private Forums $forums;

    private AbstractRestHydrator $themeHydrator;

    private AbstractRestHydrator $topicHydrator;

    private InputFilter $themeListInputFilter;

    private InputFilter $themeInputFilter;

    private InputFilter $topicListInputFilter;

    private InputFilter $topicGetInputFilter;

    public function __construct(
        Forums $forums,
        AbstractRestHydrator $themeHydrator,
        AbstractRestHydrator $topicHydrator,
        InputFilter $themeListInputFilter,
        InputFilter $themeInputFilter,
        InputFilter $topicListInputFilter,
        InputFilter $topicGetInputFilter
    ) {
        $this->forums               = $forums;
        $this->themeHydrator        = $themeHydrator;
        $this->topicHydrator        = $topicHydrator;
        $this->themeListInputFilter = $themeListInputFilter;
        $this->themeInputFilter     = $themeInputFilter;
        $this->topicListInputFilter = $topicListInputFilter;
        $this->topicGetInputFilter  = $topicGetInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getThemesAction()
    {
        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->enforce('global', 'moderate');

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
        $isModerator = $this->user()->enforce('global', 'moderate');

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

        $row = currentFromResultSetInterface($this->forums->getThemeTable()->selectWith($select));
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

        $isModerator = $this->user()->enforce('global', 'moderate');

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

        /** @var Adapter $adapter */
        $adapter   = $this->forums->getTopicTable()->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
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
    public function getTopicAction()
    {
        $this->topicGetInputFilter->setData($this->params()->fromQuery());

        if (! $this->topicGetInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicGetInputFilter);
        }

        $data = $this->topicGetInputFilter->getValues();

        $user   = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->enforce('global', 'moderate');

        $select = $this->forums->getTopicTable()->getSql()->select();
        $select->where(['forums_topics.id' => (int) $this->params('id')]);

        if (! $isModerator) {
            $select
                ->join('forums_themes', 'forums_topics.theme_id = forums_themes.id', [])
                ->where(['not forums_themes.is_moderator']);
        }

        $row = currentFromResultSetInterface($this->forums->getTopicTable()->selectWith($select));

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
