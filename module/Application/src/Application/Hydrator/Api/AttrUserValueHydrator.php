<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Router\Http\TreeRouteStack;
use Zend\Stdlib\ArrayUtils;

use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrUserValueHydrator extends RestHydrator
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

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('update_date', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('item', $strategy);
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
     * @return AttrUserValueHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        $this->getStrategy('user')->setUserId($userId);
        $this->getStrategy('item')->setUserId($userId);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'update_date'  => $this->extractValue('update_date', $object['update_date']),
            'item_id'      => (int)$object['item_id'],
            'attribute_id' => (int)$object['attribute_id'],
            'user_id'      => (int)$object['user_id'],
        ];

        if ($this->filterComposite->filter('value')) {
            $value = $this->specService->getUserValue2(
                $object['attribute_id'],
                $object['item_id'],
                $object['user_id']
            );
            $result['value'] = $value['value'];
            $result['empty'] = $value['empty'];
        }

        if ($this->filterComposite->filter('value_text')) {
            $result['value_text'] = $this->specService->getUserValueText(
                $object['attribute_id'],
                $object['item_id'],
                $object['user_id'],
                $this->language
            );
        }

        if ($this->filterComposite->filter('unit')) {
            $result['unit'] = $this->specService->getUnit($object['attribute_id']);
        }

        if ($this->filterComposite->filter('path')) {
            $attributeTable = $this->specService->getAttributeTable();

            $path = [];
            $attribute = $attributeTable->select(['id' => $object['attribute_id']])->current();
            if ($attribute) {
                $parents = [];
                $parent = $attribute;
                do {
                    $parents[] = $parent['name'];
                    $parent = $attributeTable->select(['id' => $parent['parent_id']])->current();
                } while ($parent);

                $path = array_reverse($parents);
            }

            $result['path'] = $path;
        }

        if ($this->filterComposite->filter('user')) {
            $user = null;
            if ($object['user_id']) {
                $userRow = $this->userModel->getRow((int) $object['user_id']);
                if ($userRow) {
                    $user = $this->extractValue('user', $userRow);
                }
            }

            $result['user'] = $user;
        }

        if ($this->filterComposite->filter('item')) {
            $user = null;
            if ($object['item_id']) {
                $itemRow = $this->item->getRow([
                    'id' => (int) $object['item_id']
                ]);
                if ($itemRow) {
                    $user = $this->extractValue('item', $itemRow);
                }
            }

            $result['item'] = $user;
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
