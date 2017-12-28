<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Router\Http\TreeRouteStack;
use Zend\Stdlib\ArrayUtils;

use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrConflictHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var SpecificationsService
     */
    private $specService;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var TreeRouteStack
     */
    private $router;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->userId = null;

        $this->item = $serviceManager->get(Item::class);
        $this->userModel = $serviceManager->get(User::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);
        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
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
     * @return AttrConflictHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'item_id'   => (int)$object['item_id'],
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

        $result['url'] = '/ng/cars/specifications-editor?item_id=' . $object['item_id'];

        if ($this->filterComposite->filter('values')) {
            $userValueTable = $this->specService->getUserValueTable();

            // other users values
            $userValueRows = $userValueTable->select([
                'attribute_id' => $object['attribute_id'],
                'item_id'      => $object['item_id']
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
                    'user_id' => (int)$userValueRow['user_id']
                ];
            }

            $result['values'] = $values;
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
