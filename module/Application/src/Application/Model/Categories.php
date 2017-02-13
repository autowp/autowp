<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Router\Http\TreeRouteStack;

use Application\Model\DbTable;

class Categories
{
    const NEW_DAYS = 7;

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @var TableGateway
     */
    private $itemTable;

    /**
     * @var TableGateway
     */
    private $itemLangTable;

    public function __construct(TreeRouteStack $router, Adapter $adapter)
    {
        $this->router = $router;

        $this->itemTable = new TableGateway('item', $adapter);
        $this->itemLangTable = new TableGateway('item_language', $adapter);
    }

    private function getCategoriesSelect($parentId, $order)
    {
        $select = new Sql\Select($this->itemTable->getTable());
        $select
            ->columns([
                'id',
                'name',
                'catname',
                'cars_count'     => new Sql\Expression('COUNT(1)'),
                'new_cars_count' => new Sql\Expression(
                    'COUNT(IF(ip_cat2car.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY), 1, NULL))',
                    self::NEW_DAYS
                )
            ])
            ->where(['item.item_type_id = ?' => DbTable\Item\Type::CATEGORY])
            ->group('item.id')
            ->join(['ipc_cat2cat' => 'item_parent_cache'], 'item.id = ipc_cat2cat.parent_id', [])
            ->join(['low_cat' => 'item'], 'ipc_cat2cat.item_id = low_cat.id', [])
            ->where(['low_cat.item_type_id = ?' => DbTable\Item\Type::CATEGORY])
            ->join(['ip_cat2car' => 'item_parent'], 'ipc_cat2cat.item_id = ip_cat2car.parent_id', [])
            ->join(['top_car' => 'item'], 'ip_cat2car.item_id = top_car.id', [])
            ->where(new Sql\Predicate\In('top_car.item_type_id', [
                DbTable\Item\Type::VEHICLE,
                DbTable\Item\Type::ENGINE
            ]));

        if ($parentId) {
            $select
                ->join(
                    ['category_item_parent' => 'item_parent'],
                    'item.id = category_item_parent.item_id',
                    []
                )
                ->where(['category_item_parent.parent_id = ?' => (int)$parentId]);
        } else {
            $select
                ->join(
                    ['category_item_parent' => 'item_parent'],
                    'item.id = category_item_parent.item_id',
                    [],
                    $select::JOIN_LEFT
                )
                ->where('category_item_parent.parent_id IS NULL');
        }

        switch ($order) {
            case 'count':
                $select->order('new_cars_count DESC');
                break;
            default:
                $select->order(['item.begin_order_cache', 'item.end_order_cache', 'item.name']);
        }

        return $select;
    }

    public function getCategoriesList($parentId, $language, $limit, $order)
    {
        $select = $this->getCategoriesSelect($parentId, 'name');

        if ($limit) {
            $select->limit($limit);
        }

        $items = $this->itemTable->selectWith($select);

        $categories = [];
        foreach ($items as $item) {
            $langRow = $this->itemLangTable->select([
                'language = ?' => $language,
                'item_id = ?'  => $item['id']
            ])->current();

            $category = [
                'id'             => $item['id'],
                'catname'        => $item['catname'],
                'url'            => $this->router->assemble([
                    'action'           => 'category',
                    'category_catname' => $item['catname']
                ], [
                    'name' => 'categories'
                ]),
                'name'           => $langRow && $langRow['name'] ? $langRow['name'] : $item['name'],
                'short_name'     => $langRow && $langRow['name'] ? $langRow['name'] : $item['name'],//$langRow ? $langRow->short_name : $row->short_name,
                'cars_count'     => $item['cars_count'],
                'new_cars_count' => $item['new_cars_count'],
            ];

            $categories[] = $category;
        }

        return $categories;
    }
}
