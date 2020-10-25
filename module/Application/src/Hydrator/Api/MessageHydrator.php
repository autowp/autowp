<?php

namespace Application\Hydrator\Api;

use Application\View\Helper\UserText;
use ArrayAccess;
use Autowp\User\Model\User;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class MessageHydrator extends AbstractRestHydrator
{
    private User $userModel;

    private UserText $userText;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $this->userText = $serviceManager->get('ViewHelperManager')->get('userText');

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('date', $strategy);

        $strategy = new Strategy\Pictures($serviceManager);
        $this->addStrategy('pictures', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('items', $strategy);
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
        $this->getStrategy('author')->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $result = [
            'id'                  => (int) $object['id'],
            'text_html'           => $this->userText->__invoke($object['contents']),
            'is_new'              => $object['isNew'],
            'can_delete'          => $object['canDelete'],
            'can_reply'           => $object['canReply'],
            'date'                => $this->extractValue('date', $object['date']),
            'all_messages_link'   => $object['allMessagesLink'],
            'dialog_count'        => $object['dialogCount'],
            'author_id'           => $object['author_id'] ? (int) $object['author_id'] : null,
            'to_user_id'          => $object['to_user_id'] ? (int) $object['to_user_id'] : null,
            'dialog_with_user_id' => $object['dialog_with_user_id'],
        ];

        if ($this->filterComposite->filter('author')) {
            $author = $this->userModel->getRow((int) $object['author_id']);

            $result['author'] = $author ? $this->extractValue('author', $author) : null;
        }

        /*if ($this->filterComposite->filter('pictures')) {
            $result['pictures'] = $this->extractValue('pictures', $object['pictures']);
        }

        if ($this->filterComposite->filter('items')) {
            $result['items'] = $this->extractValue('items', $object['items']);
        }*/

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): void
    {
        throw new Exception("Not supported");
    }
}
