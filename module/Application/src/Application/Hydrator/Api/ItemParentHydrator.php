<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Item as HydratorItemStrategy;
use Application\Model\DbTable;

class ItemParentHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;
    
    private $router;
    
    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
        
        $this->router = $serviceManager->get('HttpRouter');
        
        $this->itemTable = new DbTable\Item();
        
        $strategy = new HydratorItemStrategy($serviceManager);
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
        $result = [
            'item_id'   => (int)$object['item_id'],
            'parent_id' => (int)$object['parent_id'],
            'type'      => (int)$object['type'],
        ];
        
        if ($this->filterComposite->filter('item')) {
            $item = $this->itemTable->find($object['item_id'])->current();
            $result['item'] = $item ? $this->extractValue('item', $item->toArray()) : null;
        }

        return $result;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
