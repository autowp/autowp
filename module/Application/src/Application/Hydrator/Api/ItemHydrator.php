<?php

namespace Application\Hydrator\Api;

use Zend\Permissions\Acl\Acl;

use Application\Model\DbTable;
use Application\ItemNameFormatter;

use Zend_Db_Expr;

class ItemHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;
    
    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;
    
    private $router;
    
    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
        
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
            'id' => (int)$object['id'],
            'name' => $this->itemNameFormatter->format(
                $object->getNameData($this->language), 
                $this->language
            ),
            'moderUrl' => $this->router->assemble([
                'action'  => 'car',
                'item_id' => $object->id
            ], [
                'name' => 'moder/cars/params'
            ])
        ];

        return $result;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}