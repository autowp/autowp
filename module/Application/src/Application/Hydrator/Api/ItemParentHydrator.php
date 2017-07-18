<?php

namespace Application\Hydrator\Api;

use Application\Model\DbTable;
use Application\Model\ItemParent;

class ItemParentHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $router;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var ItemParent
     */
    private $itemParent;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->router = $serviceManager->get('HttpRouter');
        $this->itemParent = $serviceManager->get(ItemParent::class);

        $this->itemTable = new DbTable\Item();

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('item', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('parent', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('duplicate_parent', $strategy);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('duplicate_child', $strategy);
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

        $this->getStrategy('content')->setUser($user);
        $this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'item_id'   => (int)$object['item_id'],
            'parent_id' => (int)$object['parent_id'],
            'type_id'   => (int)$object['type'],
            'catname'   => $object['catname'],
        ];

        if ($this->filterComposite->filter('item')) {
            $item = $this->itemTable->find($object['item_id'])->current();
            $result['item'] = $item ? $this->extractValue('item', $item->toArray()) : null;
        }

        if ($this->filterComposite->filter('parent')) {
            $item = $this->itemTable->find($object['parent_id'])->current();
            $result['parent'] = $item ? $this->extractValue('parent', $item->toArray()) : null;
        }

        if ($this->filterComposite->filter('name')) {
            $result['name'] = $this->itemParent->getNamePreferLanguage(
                $object['parent_id'],
                $object['item_id'],
                $this->language
            );
        }

        if ($this->filterComposite->filter('duplicate_parent')) {
            $select = $this->itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.parent_id', null)
                ->where('item_parent.item_id = ?', $object['item_id'])
                ->where('item_parent.parent_id <> ?', $object['parent_id'])
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $object['parent_id'])
                ->where('not item_parent_cache.tuning')
                ->where('not item_parent_cache.sport')
                ->where('item_parent.type = ?', ItemParent::TYPE_DEFAULT);

            $duplicateRow = $this->itemTable->fetchRow($select);

            $result['duplicate_parent'] = $duplicateRow
                ? $this->extractValue('duplicate_parent', $duplicateRow->toArray()) : null;
        }

        if ($this->filterComposite->filter('duplicate_child')) {
            $select = $this->itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $object['item_id'])
                ->where('item_parent.parent_id = ?', $object['parent_id'])
                ->where('item_parent.item_id <> ?', $object['item_id'])
                ->where('item_parent.type = ?', $object['type']);

            $duplicateRow = $this->itemTable->fetchRow($select);

            $result['duplicate_child'] = $duplicateRow
                ? $this->extractValue('duplicate_child', $duplicateRow->toArray()) : null;
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
