<?php

namespace Application\Hydrator\Api;

use Application\Model\Item;
use Application\Model\ItemParent;
use ArrayAccess;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class ItemParentHydrator extends AbstractRestHydrator
{
    private int $userId = 0;

    private ?string $userRole;

    private Item $item;

    private ItemParent $itemParent;

    private Enforcer $acl;

    private User $userModel;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->itemParent = $serviceManager->get(ItemParent::class);

        $this->item = $serviceManager->get(Item::class);

        $this->acl       = $serviceManager->get(Enforcer::class);
        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('item', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('parent', $strategy);
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

        /** @var Strategy\Item $strategy */
        $strategy = $this->getStrategy('item');
        $strategy->setUserId($userId);

        /** @var Strategy\Item $strategy */
        $strategy = $this->getStrategy('parent');
        $strategy->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $result = [
            'item_id'   => (int) $object['item_id'],
            'parent_id' => (int) $object['parent_id'],
            'type_id'   => (int) $object['type'],
            'catname'   => $object['catname'],
        ];

        $isModer = false;
        $role    = $this->getUserRole();
        if ($role) {
            $isModer = $this->acl->enforce($role, 'global', 'moderate');
        }

        if ($this->filterComposite->filter('item')) {
            $item           = $this->item->getRow(['id' => $object['item_id']]);
            $result['item'] = $item ? $this->extractValue('item', $item) : null;
        }

        if ($isModer) {
            if ($this->filterComposite->filter('parent')) {
                $item             = $this->item->getRow(['id' => $object['parent_id']]);
                $result['parent'] = $item ? $this->extractValue('parent', $item) : null;
            }

            if ($this->filterComposite->filter('name')) {
                $result['name'] = $this->itemParent->getNamePreferLanguage(
                    $object['parent_id'],
                    $object['item_id'],
                    $this->language
                );
            }
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

    /**
     * @throws Exception
     */
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
