<?php

namespace Application\Hydrator\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

use Autowp\Forums\Forums;
use Autowp\User\Model\User;

use Application\Comments;

class ForumThemeHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    /**
     * @var User
     */
    private $userModel;

    private $acl;

    /**
     * @var TableGateway
     */
    private $themeTable;

    /**
     * @var TableGateway
     */
    private $topicTable;

    private $topics = [];

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->comments = $serviceManager->get(\Application\Comments::class);

        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $this->userId = null;

        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);

        $tables = $serviceManager->get('TableManager');
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
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
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
     * @return Comment
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        $this->getStrategy('themes')->setUserId($userId);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'id'             => (int)$object['id'],
            'name'           => $object['name'],
            'topics_count'   => (int)$object['topics'],
            'messages_count' => (int)$object['messages'],
            'disable_topics' => (bool)$object['disable_topics']
        ];

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $object['description'];
        }

        $fetchLastTopic = $this->filterComposite->filter('last_topic');
        $fetchLastMessage = $this->filterComposite->filter('last_message');

        if ($fetchLastTopic || $fetchLastMessage) {
            $select = $this->topicTable->getSql()->select()
                ->join('forums_theme_parent', 'forums_topics.theme_id = forums_theme_parent.forum_theme_id', [])
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
                ->where([
                    new Sql\Predicate\In('forums_topics.status', [Forums::STATUS_NORMAL, Forums::STATUS_CLOSED]),
                    'forums_theme_parent.parent_id' => $object['id'],
                    'comment_topic.type_id'         => \Application\Comments::FORUMS_TYPE_ID
                ])
                ->order('comment_topic.last_update DESC')
                ->limit(1);

            $lastTopicRow = $this->topicTable->selectWith($select)->current();
            //var_dump($lastTopicRow);
            $lastMessageRow = null;
            if ($lastTopicRow) {
                /*$lastTopic = [
                    'id'   => $lastTopicRow['id'],
                    'name' => $lastTopicRow['name']
                ];*/

                if ($fetchLastMessage) {
                    $lastMessageRow = $this->comments->service()->getLastMessageRow(
                        \Application\Comments::FORUMS_TYPE_ID,
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
            $role = $this->getUserRole();
            if ($role) {
                $isModerator = $this->acl->inheritsRole($role, 'moder');
            }

            if (! $isModerator) {
                $select->where(['not is_moderator']);
            }

            $rows = $this->themeTable->selectWith($select);
            $result['themes'] = $this->extractValue('themes', $rows); // id, name
        }

        if ($this->filterComposite->filter('topics') && ! $object['disable_topics']) {
            $select = new Sql\Select($this->topicTable->getTable());
            $select
                ->join('comment_topic', 'forums_topics.id = comment_topic.item_id', [])
                ->where([
                    new Sql\Predicate\In('forums_topics.status', [Forums::STATUS_CLOSED, Forums::STATUS_NORMAL]),
                    'comment_topic.type_id = ?' => \Application\Comments::FORUMS_TYPE_ID,
                    'forums_topics.theme_id' => (int)$object['id']
                ])
                ->order('comment_topic.last_update DESC');

            $paginator = new Paginator\Paginator(
                new Paginator\Adapter\DbSelect($select, $this->topicTable->getAdapter())
            );

            $paginator->setItemCountPerPage(Forums::TOPICS_PER_PAGE);

            if (isset($this->topics['page'])) {
                $paginator->setCurrentPageNumber($this->topics['page']);
            }

            $result['topics'] = [
                'items'     => $this->extractValue('topics', $paginator->getCurrentItems()),
                'paginator' => $paginator->getPages()
            ];
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }
}
