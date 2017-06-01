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

        $user = $this->user()->get();

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from('item');

        $group = false;

        if ($data['last_item']) {
            $namespace = new \Zend\Session\Container('Moder_Car');
            if (isset($namespace->lastCarId)) {
                $select->where('item.id = ?', (int)$namespace->lastCarId);
            } else {
                $select->where(new Zend_Db_Expr('0'));
            }
        }

        switch ($data['order']) {
            case 'id_asc':
                $select->order('item.id ASC');
                break;
            case 'id_desc':
                $select->order('item.id DESC');
                break;
            case 'childs_count':
                $group = true;
                $select
                    ->columns(['childs_count' => new Zend_Db_Expr('count(item_parent.item_id)')])
                    ->join('item_parent', 'item_parent.parent_id = item.id', null)
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

        if ($data['name']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->where('item_language.name like ?', $data['name']);
        }

        if ($data['name_exclude']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->where('item_language.name not like ?', $data['name_exclude']);
        }

        $id = (int)$this->params()->fromQuery('id');
        if ($id) {
            $select->where('item.id = ?', $id);
        }

        if ($data['descendant']) {
            $group = true;
            $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $data['descendant']);
        }

        if ($data['type_id']) {
            $select->where('item.item_type_id = ?', $data['type_id']);
        }

        if ($data['vehicle_type_id']) {
            if ($data['vehicle_type_id'] == 'empty') {
                $select
                    ->joinLeft('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_id is null');
            } else {
                $select
                    ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_type_id = ?', $data['vehicle_type_id']);
            }
        }

        if ($data['vehicle_childs_type_id']) {
            $group = true;
            $select
                ->join(
                    ['cpc_childs' => 'item_parent_cache'],
                    'item.id = cpc_childs.parent_id',
                    null
                )
                ->join(
                    ['vvt_child' => 'vehicle_vehicle_type'],
                    'cpc_childs.item_id = vvt_child.vehicle_id',
                    null
                )
                ->join('car_types_parents', 'vvt_child.vehicle_type_id = car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $data['vehicle_childs_type_id']);
        }

        if ($data['spec']) {
            $select->where('item.spec_id = ?', $data['spec']);
        }

        if ($data['parent_id']) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $data['parent_id'])
                ->where('item_parent_cache.item_id <> item_parent_cache.parent_id');
        }

        if ($data['from_year']) {
            $select->where('item.begin_year = ?', $data['from_year']);
        }

        if ($data['to_year']) {
            $select->where('item.end_year = ?', $data['to_year']);
        }

        if ($data['no_parent']) {
            $select
                ->joinLeft(
                    ['np_ip' => 'item_parent'],
                    'item.id = np_ip.item_id',
                    null
                )
                ->where('np_ip.item_id IS NULL');
        }

        if ($data['text']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->join('textstorage_text', 'item_language.text_id = textstorage_text.id', null)
                ->where('textstorage_text.text like ?', '%' . $data['text'] . '%');
        }

        if ($group) {
            $select->group('item.id');
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
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null
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
