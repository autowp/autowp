<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;
use Zend\Hydrator\Exception\InvalidArgumentException;
use Zend\Permissions\Acl\Acl;
use Zend\Stdlib\ArrayUtils;
use Autowp\User\Model\User;
use Application\Model\Item;
use Application\Model\Picture;

class PictureItemHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Picture
     */
    private $picture;

    private $acl;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->item = $serviceManager->get(Item::class);
        $this->picture = $serviceManager->get(Picture::class);
        $this->userModel = $serviceManager->get(User::class);

        $this->acl = $serviceManager->get(Acl::class);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('item', $strategy);

        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('picture', $strategy);
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
     * @return PictureItemHydrator
     */
    public function setUserId($userId = null)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
            $this->userRole = null;

            $this->getStrategy('item')->setUserId($userId);
        }

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'picture_id'     => (int)$object['picture_id'],
            'item_id'        => (int)$object['item_id'],
            'type'           => (int)$object['type'],
            'perspective_id' => (int)$object['perspective_id'],
        ];

        $isModer = false;
        $role = $this->getUserRole();
        if ($role) {
            $isModer = $this->acl->inheritsRole($role, 'moder');
        }

        if ($this->filterComposite->filter('item')) {
            $row = $this->item->getRow(['id' => (int)$object['item_id']]);

            $result['item'] = $row ? $this->extractValue('item', $row) : null;
        }

        if ($isModer) {
            if ($this->filterComposite->filter('picture')) {
                $row = $this->picture->getRow(['id' => (int)$object['picture_id']]);

                $result['picture'] = $row ? $this->extractValue('picture', $row) : null;
            }

            if ($this->filterComposite->filter('area')) {
                $hasArea = $object['crop_width'] && $object['crop_height'];
                $result['area'] = null;
                if ($hasArea) {
                    $result['area'] = [
                        'left'   => (int)$object['crop_left'],
                        'top'    => (int)$object['crop_top'],
                        'width'  => (int)$object['crop_width'],
                        'height' => (int)$object['crop_height'],
                    ];
                }
            }
        }

        return $result;
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
