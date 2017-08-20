<?php

namespace Application\Hydrator\Api;

use Autowp\User\Model\User;

use Application\Comments;
use Application\Model\Picture;

class CommentHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Picture
     */
    private $picture;

    private $hydratorManager;

    /**
     * @var int|null
     */
    private $userId = null;

    /**
     * @var User
     */
    private $userModel;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->hydratorManager = $serviceManager->get('HydratorManager');
        $this->comments = $serviceManager->get(\Application\Comments::class);
        $this->router = $serviceManager->get('HttpRouter');

        $this->picture = $serviceManager->get(Picture::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $this->userId = null;
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

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($object)
    {
        $status = null;
        if ($object['type_id'] == \Application\Comments::PICTURES_TYPE_ID) {
            $picture = $this->picture->getRow(['id' => (int)$object['item_id']]);
            if ($picture) {
                switch ($picture['status']) {
                    case Picture::STATUS_ACCEPTED:
                        $status = [
                            'class' => 'success',
                            'name'  => 'moder/picture/acceptance/accepted'
                        ];
                        break;
                    case Picture::STATUS_INBOX:
                        $status = [
                            'class' => 'warning',
                            'name'  => 'moder/picture/acceptance/inbox'
                        ];
                        break;
                    case Picture::STATUS_REMOVED:
                        $status = [
                            'class' => 'danger',
                            'name'  => 'moder/picture/acceptance/removed'
                        ];
                        break;
                    case Picture::STATUS_REMOVING:
                        $status = [
                            'class' => 'danger',
                            'name'  => 'moder/picture/acceptance/removing'
                        ];
                        break;
                }
            }
        }

        $user = null;
        if ($object['author_id']) {
            $userRow = $this->userModel->getRow((int) $object['author_id']);
            if ($userRow) {
                $userHydrator = $this->hydratorManager->get(UserHydrator::class);
                $user = $userHydrator->extract($userRow);
            }
        }

        return [
            'url'     => $this->comments->getMessageRowUrl($object),
            'preview' => $this->comments->getMessagePreview($object['message']),
            'user'    => $user,
            'status'  => $status,
            'new'     => $this->comments->service()->isNewMessage($object, $this->userId)
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
