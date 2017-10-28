<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\User\Model\User;

use Application\View\Helper\UserText;

class MessageHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var UserText
     */
    private $userText;

    private $router;

    public function __construct(
        $serviceManager
    ) {
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

        $this->router = $serviceManager->get('HttpRouter');
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
     * @return MessageHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        $this->getStrategy('author')->setUserId($userId);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'id'                => (int)$object['id'],
            'text_html'         => $this->userText->__invoke($object['contents']),
            'is_new'            => $object['isNew'],
            'can_delete'        => $object['canDelete'],
            'can_reply'         => $object['canReply'],
            'date'              => $this->extractValue('date', $object['date']),
            'all_messages_link' => $object['allMessagesLink'],
            'dialog_count'      => $object['dialogCount'],
            'author_id'         => $object['author_id'] ? (int) $object['author_id'] : null,
            'to_user_id'        => $object['to_user_id'] ? (int) $object['to_user_id'] : null,
            'dialog_with_user_id' => $object['dialog_with_user_id']
        ];

        if ($this->filterComposite->filter('author')) {
            $author = $this->userModel->getRow((int)$object['author_id']);

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
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
