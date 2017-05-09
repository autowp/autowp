<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\RestHydrator;
use Application\ItemNameFormatter;
use Application\Model\DbTable;

use Zend_Db_Expr;

class ItemController extends AbstractRestfulController
{
    /**
     * @var User
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    public function __construct(
        RestHydrator $hydrator,
        ItemNameFormatter $itemNameFormatter,
        InputFilter $listInputFilter
    ) {
        $this->hydrator = $hydrator;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->listInputFilter = $listInputFilter;

        $this->table = new DbTable\Item();
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
            ->from('item');

        if ($data['last_item']) {
            $namespace = new \Zend\Session\Container('Moder_Car');
            if (isset($namespace->lastCarId)) {
                $select->where('item.id = ?', (int)$namespace->lastCarId);
            } else {
                $select->where(new Zend_Db_Expr('0'));
            }
        }

        switch ($data['order']) {
            case 'childs_count':
                $select
                    ->columns(['childs_count' => new Zend_Db_Expr('count(item_parent.item_id)')])
                    ->join('item_parent', 'item_parent.parent_id = item.id', null)
                    ->group('item.id')
                    ->order('childs_count desc');
                break;
            default:
                $select->order([
                    'item.name',
                    'item.body',
                    'item.spec_id',
                    'item.begin_order_cache',
                    'item.end_order_cache'
                ]);
                break;
        }

        if ($data['search']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->where('item_language.name like ?', $data['search'] . '%');
        }

        $id = (int)$this->params()->fromQuery('id');
        if ($id) {
            $select->where('item.id = ?', $id);
        }

        if ($data['descendant']) {
            $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $data['descendant'])
                ->group('item.id');
        }

        if ($data['type_id']) {
            $select->where('item.item_type_id = ?', $data['type_id']);
        }

        if ($data['parent_id']) {
            $select
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $data['parent_id']);
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

    public function alphaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();
        $carAdapter = $itemTable->getAdapter();
        $chars = $carAdapter->fetchCol(
            $carAdapter->select()
                ->distinct()
                ->from('item', ['char' => new Zend_Db_Expr('UPPER(LEFT(name, 1))')])
                ->order('char')
        );

        $groups = [
            'numbers' => [],
            'english' => [],
            'other'   => []
        ];

        foreach ($chars as $char) {
            if (preg_match('|^["0-9-]$|isu', $char)) {
                $groups['numbers'][] = $char;
            } elseif (preg_match('|^[A-Za-z]$|isu', $char)) {
                $groups['english'][] = $char;
            } else {
                $groups['other'][] = $char;
            }
        }

        return new JsonModel([
            'groups' => $groups
        ]);
    }
}
