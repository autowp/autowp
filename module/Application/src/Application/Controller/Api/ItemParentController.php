<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\DbTable;

class ItemParentController extends AbstractRestfulController
{
    /**
     * @var User
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    public function __construct(
        RestHydrator $hydrator,
        InputFilter $listInputFilter
    ) {
        $this->hydrator = $hydrator;
        $this->listInputFilter = $listInputFilter;

        $this->table = new DbTable\Item\ParentTable();
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from($this->table->info('name'))
            ->join('item', 'item_parent.item_id = item.id', [])
            ->order([
                'item_parent.type',
                'item.name',
                'item.body',
                'item.spec_id',
                'item.begin_order_cache',
                'item.end_order_cache'
            ]);
            
        if ($data['ancestor_id']) {
            $select
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.item_id', [])
                ->where('item_parent_cache.parent_id = ?', $data['ancestor_id'])
                ->group(['item_parent.item_id']);
        }
        
        if ($data['item_type_id']) {
            $select->where('item.item_type_id = ?', $data['item_type_id']);
        }
        
        if ($data['concept']) {
            $select->where('item.is_concept');
        }
            
        if ($data['parent_id']) {
            $select->where('item_parent.parent_id = ?', $data['parent_id']);
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbSelect($select)
        );

        $limit = $data['limit'] ? $data['limit'] : 1;

        $paginator
            ->setItemCountPerPage($limit)
            ->setCurrentPageNumber($data['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields']
            //'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($this->table->getAdapter()->fetchAll($select) as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items
        ]);
    }
}
