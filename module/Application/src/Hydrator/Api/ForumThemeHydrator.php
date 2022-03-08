<?php

namespace Application\Hydrator\Api;

use Application\Comments;
use ArrayAccess;
use Autowp\Forums\Forums;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Paginator;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function Autowp\Commons\currentFromResultSetInterface;
use function is_array;

class ForumThemeHydrator extends AbstractRestHydrator
{
    private Comments $comments;

    private int $userId = 0;

    private ?string $userRole;

    private User $userModel;

    private Enforcer $acl;

    private TableGateway $themeTable;

    private TableGateway $topicTable;

    private array $topics = [];

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->comments = $serviceManager->get(Comments::class);

        $this->userModel = $serviceManager->get(User::class);

        $this->userId = 0;

        $this->acl = $serviceManager->get(Enforcer::class);

        $tables           = $serviceManager->get('TableManager');
        $this->themeTable = $tables->get('forums_themes');
        $this->topicTable = $tables->get('forums_topics');

        $strategy = new Strategy\ForumThemes($serviceManager);
        $this->addStrategy('themes', $strategy);

        $strategy = new Strategy\ForumTopics($serviceManager);
        $this->addStrategy('topics', $strategy);

        $strategy = new Strategy\ForumTopic($serviceManager);
        $this->addStrategy('last_topic', $strategy);

        $strategy = new Strategy\Comment($serviceManager);
        $this->addStrategy('last_message', $strategy);

        /*$strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('datetime', $strategy);

        $strategy = new Strategy\Comments($serviceManager);
        $this->addStrategy('replies', $strategy);*/
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        if (isset($options['topics']) && is_array($options['topics'])) {
            $this->topics = $options['topics'];
        }

        return $this;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId($userId = null): self
    {
        $this->userId = (int) $userId;

        /** @var Strategy\ForumThemes $strategy */
        $strategy = $this->getStrategy('themes');
        $strategy->setUserId($userId);

        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $result = [
            'id'             => (int) $object['id'],
            'name'           => $object['name'],
            'topics_count'   => (int) $object['topics'],
            'messages_count' => (int) $object['messages'],
            'disable_topics' => (bool) $object['disable_topics'],
        ];

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $object['description'];
        }

        $fetchLastTopic   = $this->filterComposite->filter('last_topic');
        $fetchLastMessage = $this->filterComposite->filter('last_message');

        if ($fetchLastTopic || $fetchLastMessage) {
            $select = $this->topicTable->getSql()->select()
                ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
                ->where([
                    new Sql\Predicate\In('forums_topics.status', [Forums::STATUS_NORMAL, Forums::STATUS_CLOSED]),
                    'forums_theme_parent.parent_id' => $object['id'],
                    'comment_topic.type_id'         => Comments::FORUMS_TYPE_ID,
                ])
                ->order('comment_topic.last_update DESC')
                ->limit(1);

            $lastTopicRow = currentFromResultSetInterface($this->topicTable->selectWith($select));

            $lastMessageRow = null;
            if ($lastTopicRow) {
                /*$lastTopic = [
                    'id'   => $lastTopicRow['id'],
                    'name' => $lastTopicRow['name']
                ];*/

                if ($fetchLastMessage) {
                    $lastMessageRow = $this->comments->service()->getLastMessageRow(
                        Comments::FORUMS_TYPE_ID,
                        $lastTopicRow['id']
                    );
                    /*if ($lastMessageRow) {
                        $lastMessage = [
                            'id'     => $lastMessageRow['id'],
                            'date'   => Row::getDateTimeByColumnType('timestamp', $lastMessageRow['datetime']),
                            'author' => $this->userModel->getRow(['id' => (int)$lastMessageRow['author_id']]),
                        ];
                    }*/
                }
            }
            if ($fetchLastTopic) {
                $result['last_topic'] = $lastTopicRow ? $this->extractValue('last_topic', $lastTopicRow) : null;
            }

            if ($fetchLastMessage) {
                $result['last_message'] = $lastMessageRow ? $this->extractValue('last_message', $lastMessageRow) : null;
            }
        }

        if ($this->filterComposite->filter('themes')) {
            $select = $this->themeTable->getSql()->select()
                ->where(['parent_id' => $object['id']])
                ->order('position');

            $isModerator = false;
            $role        = $this->getUserRole();
            if ($role) {
                $isModerator = $this->acl->enforce($role, 'global', 'moderate');
            }

            if (! $isModerator) {
                $select->where(['not is_moderator']);
            }

            $rows             = $this->themeTable->selectWith($select);
            $result['themes'] = $this->extractValue('themes', $rows); // id, name
        }

        if ($this->filterComposite->filter('topics') && ! $object['disable_topics']) {
            $select = new Sql\Select($this->topicTable->getTable());
            $select
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
                ->where([
                    new Sql\Predicate\In('forums_topics.status', [Forums::STATUS_CLOSED, Forums::STATUS_NORMAL]),
                    'comment_topic.type_id = ?' => Comments::FORUMS_TYPE_ID,
                    'forums_topics.theme_id'    => (int) $object['id'],
                ])
                ->order('comment_topic.last_update DESC');

            /** @var Adapter $adapter */
            $adapter   = $this->topicTable->getAdapter();
            $paginator = new Paginator\Paginator(
                new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
            );

            $paginator->setItemCountPerPage(Forums::TOPICS_PER_PAGE);

            if (isset($this->topics['page'])) {
                $paginator->setCurrentPageNumber($this->topics['page']);
            }

            $result['topics'] = [
                'items'     => $this->extractValue('topics', $paginator->getCurrentItems()),
                'paginator' => $paginator->getPages(),
            ];
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }

    private function getUserRole(): ?string
    {
        if (! $this->userId) {
            return null;
        }

        if (! isset($this->userRole)) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }
}
