<?php

namespace Application\Hydrator\Api;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;

use Autowp\Commons\Db\Table\Row;
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
        $this->addStrategy('topic', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('add_datetime', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new Strategy\Comment($serviceManager);
        $this->addStrategy('last_message', $strategy);
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
        $this->getStrategy('last_message')->setUserId($userId);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'id'           => (int)$object['id'],
            'name'         => $object['name'],
            'add_datetime' => $this->extractValue('add_datetime', Row::getDateTimeByColumnType('timestamp', $object['add_datetime'])),
            'status'       => $object['status']
        ];

        if ($this->filterComposite->filter('last_message')) {
            $lastMessageRow = $this->comments->service()->getLastMessageRow(
                \Application\Comments::FORUMS_TYPE_ID,
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
                    \Application\Comments::FORUMS_TYPE_ID,
                    $object['id'],
                    $this->userId
                );
                $messages = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat = $this->comments->service()->getTopicStat(
                    \Application\Comments::FORUMS_TYPE_ID,
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

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
