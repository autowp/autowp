<?php

namespace Application\Hydrator\Api;

use Application\Comments;
use ArrayAccess;
use Autowp\Commons\Db\Table\Row;
use Autowp\Forums\Forums;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function Autowp\Commons\currentFromResultSetInterface;
use function is_array;

class ForumTopicHydrator extends AbstractRestHydrator
{
    private Comments $comments;

    private int $userId = 0;

    private User $userModel;

    private TableGateway $themeTable;

    private Forums $forums;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->comments = $serviceManager->get(Comments::class);

        $this->userModel = $serviceManager->get(User::class);

        $this->userId = 0;

        $tables           = $serviceManager->get('TableManager');
        $this->themeTable = $tables->get('forums_themes');

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

        return $this;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId($userId = null): self
    {
        $this->userId = (int) $userId;

        /** @var Strategy\ForumTheme $strategy */
        $strategy = $this->getStrategy('theme');
        $strategy->setUserId($userId);

        /** @var Strategy\ForumThemes $strategy */
        $strategy = $this->getStrategy('themes');
        $strategy->setUserId($userId);

        /** @var Strategy\Comment $strategy */
        $strategy = $this->getStrategy('last_message');
        $strategy->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $date = Row::getDateTimeByColumnType('timestamp', $object['add_datetime']);

        $result = [
            'id'           => (int) $object['id'],
            'name'         => $object['name'],
            'add_datetime' => $this->extractValue('add_datetime', $date),
            'status'       => $object['status'],
            'theme_id'     => (int) $object['theme_id'],
        ];

        if ($this->filterComposite->filter('last_message')) {
            $lastMessageRow = $this->comments->service()->getLastMessageRow(
                Comments::FORUMS_TYPE_ID,
                $object['id']
            );
            $lastMessage    = false;
            if ($lastMessageRow) {
                $lastMessage = $this->extractValue('last_message', $lastMessageRow);
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
                $stat        = $this->comments->service()->getTopicStatForUser(
                    Comments::FORUMS_TYPE_ID,
                    $object['id'],
                    $this->userId
                );
                $messages    = $stat['messages'];
                $newMessages = $stat['newMessages'];
            } else {
                $stat        = $this->comments->service()->getTopicStat(
                    Comments::FORUMS_TYPE_ID,
                    $object['id']
                );
                $messages    = $stat['messages'];
                $newMessages = 0;
            }

            $oldMessages = $messages - $newMessages;

            $result['messages']     = $messages;
            $result['old_messages'] = $oldMessages;
            $result['new_messages'] = $newMessages;
        }

        if ($this->filterComposite->filter('theme')) {
            $row             = currentFromResultSetInterface(
                $this->themeTable->select(['id' => (int) $object['theme_id']])
            );
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
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }
}
