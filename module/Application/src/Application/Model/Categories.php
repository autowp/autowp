<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Router\Http\TreeRouteStack;

class Categories
{
    public const NEW_DAYS = 7;

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @var TableGateway
     */
    private $itemTable;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(
        TreeRouteStack $router,
        TableGateway $itemTable,
        Item $itemModel
    ) {
        $this->router = $router;

        $this->itemTable = $itemTable;

        $this->itemModel = $itemModel;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param $parentId
     * @param $order
     * @return Sql\Select
     */
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
            ->where(['item.item_type_id = ?' => Item::CATEGORY])
            ->group('item.id')
            ->join(['ipc_cat2cat' => 'item_parent_cache'], 'item.id = ipc_cat2cat.parent_id', [])
            ->join(['low_cat' => 'item'], 'ipc_cat2cat.item_id = low_cat.id', [])
            ->where(['low_cat.item_type_id = ?' => Item::CATEGORY])
            ->join(['ip_cat2car' => 'item_parent'], 'ipc_cat2cat.item_id = ip_cat2car.parent_id', [])
            ->join(['top_car' => 'item'], 'ip_cat2car.item_id = top_car.id', [])
            ->where(new Sql\Predicate\In('top_car.item_type_id', [
                Item::VEHICLE,
                Item::ENGINE
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
        $select = $this->getCategoriesSelect($parentId, $order);

        if ($limit) {
            $select->limit($limit);
        }

        $items = $this->itemTable->selectWith($select);

        $categories = [];
        foreach ($items as $item) {
            $langName = $this->itemModel->getName($item['id'], $language);

            $category = [
                'id'             => (int) $item['id'],
                'catname'        => $item['catname'],
                'name'           => $langName ? $langName : $item['name'],
                'short_name'     => $langName ? $langName : $item['name'],
                'cars_count'     => $item['cars_count'],
                'new_cars_count' => $item['new_cars_count'],
            ];

            $categories[] = $category;
        }

        return $categories;
    }
}
