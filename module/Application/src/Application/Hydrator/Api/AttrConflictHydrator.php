<?php

namespace Application\Hydrator\Api;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use Autowp\User\Model\User;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class AttrConflictHydrator extends RestHydrator
{
    private int $userId;

    private Item $item;

    private User $userModel;

    private SpecificationsService $specService;

    private ItemNameFormatter $itemNameFormatter;

    private TreeRouteStack $router;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->userId = 0;

        $this->item              = $serviceManager->get(Item::class);
        $this->userModel         = $serviceManager->get(User::class);
        $this->specService       = $serviceManager->get(SpecificationsService::class);
        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
        $this->router            = $serviceManager->get('HttpRouter');
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
        $this->userId = $userId;

        return $this;
    }

    public function extract($object): ?array
    {
        $result = [
            'item_id'   => (int) $object['item_id'],
            'attribute' => (string) $object['attribute'],
            'unit'      => $object['unit'],
        ];

        $item = $this->item->getRow(['id' => $object['item_id']]);

        if ($item) {
            $result['object'] = $this->itemNameFormatter->format(
                $this->item->getNameData($item, $this->language),
                $this->language
            );
        }

        if ($this->filterComposite->filter('values')) {
            $userValueTable = $this->specService->getUserValueTable();

            // other users values
            $userValueRows = $userValueTable->select([
                'attribute_id' => $object['attribute_id'],
                'item_id'      => $object['item_id'],
            ]);

            $values = [];
            foreach ($userValueRows as $userValueRow) {
                $values[] = [
                    'value'   => $this->specService->getUserValueText(
                        $userValueRow['attribute_id'],
                        $userValueRow['item_id'],
                        $userValueRow['user_id'],
                        $this->language
                    ),
                    'user_id' => (int) $userValueRow['user_id'],
                ];
            }

            $result['values'] = $values;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
