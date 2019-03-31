<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;

use Zend\Db\TableGateway\TableGateway;
use Zend\Hydrator\Exception\InvalidArgumentException;
use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Permissions\Acl\Acl;
use Zend\Stdlib\ArrayUtils;

use Autowp\Commons\Db\Table\Row;
use Autowp\Forums\Forums;
use Autowp\User\Model\User;

use Application\Comments;

class ForumTopicHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var int|null
     */
    private $userId = null;

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

    /**
     * @var Forums
     */
    private $forums;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->comments = $serviceManager->get(Comments::class);

        $this->userModel = $serviceManager->get(User::class);

        $this->userId = null;

        $this->acl = $serviceManager->get(Acl::class);

        $tables = $serviceManager->get('TableManager');
        $this->themeTable = $tables->get('forums_themes');
        $this->topicTable = $tables->get('forums_topics');

        $this->forums = $serviceManager->get(Forums::class);

        $strategy = new Strategy\ForumThemes($serviceManager);
        $this->addStrategy('themes', $strategy);

        $strategy = new Strategy\ForumTopics($serviceManager);
        $this->addStrategy('topic', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('add_datetime', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new Strategy\Comment($serviceManager);
        $this->addStrategy('last_message', $strategy);

        $strategy = new Strategy\ForumTheme($serviceManager);
        $this->addStrategy('theme', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws InvalidArgumentException
     */
    public function setOptions($options)
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

        return $this;
    }

    /**
     * @param int|null $userId
     * @return ForumTopicHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        $this->getStrategy('theme')->setUserId($userId);
        $this->getStrategy('themes')->setUserId($userId);
        $this->getStrategy('last_message')->setUserId($userId);

        return $this;
    }

    public function extract($object)
    {
        $date = Row::getDateTimeByColumnType('timestamp', $object['add_datetime']);

        $result = [
            'id'           => (int)$object['id'],
            'name'         => $object['name'],
            'add_datetime' => $this->extractValue('add_datetime', $date),
            'status'       => $object['status'],
            'theme_id'     => (int)$object['theme_id']
        ];

        if ($this->filterComposite->filter('last_message')) {
            $lastMessageRow = $this->comments->service()->getLastMessageRow(
                Comments::FORUMS_TYPE_ID,
                $object['id']
            );
            $lastMessage = false;
            if ($lastMessageRow) {
                $lastMessage = $lastMessageRow ? $this->extractValue('last_message', $lastMessageRow) : null;
            }

            $result['last_message'] = $lastMessage;
        }

        if ($this->filterComposite->filter('author')) {
            $author = null;
            if ($object['author_id']) {
                $author = $this->userModel->getRow($object['author_id']);
            }

            $result['author'] = $author ? $this->extractValue('author', $author) : null;
        }

        if ($this->filterComposite->filter('messages')) {
            if ($this->userId) {
                $stat = $this->comments->service()->getTopicStatForUser(
                    Comments::FORUMS_TYPE_ID,
                    $object['id'],
                    $this->userId
                );
                $messages = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat = $this->comments->service()->getTopicStat(
                    Comments::FORUMS_TYPE_ID,
                    $object['id']
                );
                $messages = $stat['messages'];
                $newMessages = 0;
            }

            $oldMessages = $messages - $newMessages;

            $result['messages'] = $messages;
            $result['old_messages'] = $oldMessages;
            $result['new_messages'] = $newMessages;
        }

        if ($this->filterComposite->filter('theme')) {
            $row = $this->themeTable->select(['id' => (int)$object['theme_id']])->current();
            $result['theme'] = $this->extractValue('theme', $row);
        }

        if ($this->filterComposite->filter('subscription')) {
            $subscribed = false;
            if ($this->userId) {
                $subscribed = $this->forums->userSubscribed($object['id'], $this->userId);
            }

            $result['subscription'] = $subscribed;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array $data
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
