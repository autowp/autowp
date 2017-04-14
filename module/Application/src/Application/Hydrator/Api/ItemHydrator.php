<?php

namespace Application\Hydrator\Api;

use Application\Model\DbTable;
use Application\Model\Item as ItemModel;
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
    
    /**
     * @var DbTable\Spec
     */
    private $specTable;
    
    /**
     * @var DbTable\Item
     */
    private $itemTable;
    
    /**
     * @return DbTable\Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new DbTable\Spec();
    }
    
    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
        
        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
        $this->router = $serviceManager->get('HttpRouter');
        
        $this->itemParentTable = new DbTable\Item\ParentTable();
        $this->itemTable = new DbTable\Item();
        
        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('brands', $strategy);
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
    
    private function getNameData(array $object, $language = 'en')
    {
        if (! is_string($language)) {
            throw new \Exception('`language` is not string');
        }
    
        $itemModel = new ItemModel();
        $name = $itemModel->getName($object['id'], $language);
    
        $spec = null;
        $specFull = null;
        if ($object['spec_id']) {
            $specRow = $this->getSpecTable()->find($object['spec_id'])->current();
            if ($specRow) {
                $spec = $specRow->short_name;
                $specFull = $specRow->name;
            }
        }
    
        $result = [
            'begin_model_year' => $object['begin_model_year'],
            'end_model_year'   => $object['end_model_year'],
            'spec'             => $spec,
            'spec_full'        => $specFull,
            'body'             => $object['body'],
            'name'             => $name,
            'begin_year'       => $object['begin_year'],
            'end_year'         => $object['end_year'],
            'today'            => $object['today'],
            'begin_month'      => $object['begin_month'],
            'end_month'        => $object['end_month']
        ];
        
        return $result;
    }
    
    public function extract($object)
    {
        $nameData = $this->getNameData($object, $this->language);
        
        $result = [
            'id' => (int)$object['id'],
            'name' => $this->itemNameFormatter->format(
                $nameData, 
                $this->language
            ),
            'moder_url' => $this->router->assemble([
                'action'  => 'car',
                'item_id' => $object['id']
            ], [
                'name' => 'moder/cars/params'
            ]),
            'item_type_id' => (int)$object['item_type_id']
        ];
        
        if ($this->filterComposite->filter('childs_count')) {
            if (isset($object['childs_count'])) {
                $result['childs_count'] = (int)$object['childs_count'];
            } else {
                $db = $this->itemParentTable->getAdapter();
                $result['childs_count'] = (int)$db->fetchOne(
                    $db->select()
                        ->from('item_parent', [new Zend_Db_Expr('count(1)')])
                        ->where('parent_id = ?', $object['id'])
                );
            }
        }
        
        if ($this->filterComposite->filter('name_html')) {
            $result['name_html'] = $this->itemNameFormatter->formatHtml(
                $nameData, 
                $this->language
            );
        }
        
        if ($this->filterComposite->filter('brands')) {
            
            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $object['id'])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->group('item.id')
            );
            
            $result['brands'] = $this->extractValue('brands', $rows->toArray());
        }

        return $result;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}