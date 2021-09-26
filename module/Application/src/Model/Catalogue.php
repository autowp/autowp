<?php

namespace Application\Model;

use Exception;
use InvalidArgumentException;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_merge;
use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function is_bool;
use function usort;

class Catalogue
{
    private int $picturesPerPage = 20;

    private int $carsPerPage = 7;

    private TableGateway $itemTable;

    private ItemParent $itemParent;

    public function __construct(ItemParent $itemParent, TableGateway $itemTable)
    {
        $this->itemTable  = $itemTable;
        $this->itemParent = $itemParent;
    }

    public function itemOrdering(): array
    {
        return [
            'item.begin_order_cache',
            'item.end_order_cache',
            'item.name',
            'item.body',
            'item.spec_id',
        ];
    }

    public function picturesOrdering(): array
    {
        return [
            'pictures.width DESC',
            'pictures.height DESC',
            'pictures.add_date DESC',
            'pictures.id DESC',
        ];
    }

    public function getCarsPerPage(): int
    {
        return $this->carsPerPage;
    }

    public function getPicturesPerPage(): int
    {
        return $this->picturesPerPage;
    }

    /**
     * @throws Exception
     */
    public function getCataloguePaths(int $id, array $options = []): array
    {
        if (! $id) {
            throw new InvalidArgumentException("Unexpected `id`");
        }

        $defaults = [
            'breakOnFirst' => false,
            'toBrand'      => null,
            'stockFirst'   => false,
        ];
        $options  = array_replace($defaults, $options);

        $breakOnFirst = (bool) $options['breakOnFirst'];
        $stockFirst   = (bool) $options['stockFirst'];
        if (isset($options['toBrand'])) {
            $toBrand   = ! is_bool($options['toBrand']) || $options['toBrand'];
            $toBrandId = is_bool($options['toBrand']) ? null : (int) $options['toBrand'];
        } else {
            $toBrand   = true;
            $toBrandId = null;
        }

        $result = [];

        if (! $toBrandId || $id === $toBrandId) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select
                ->columns(['catname'])
                ->where([
                    'id'           => $id,
                    'item_type_id' => Item::BRAND,
                ]);

            $brand = currentFromResultSetInterface($this->itemTable->selectWith($select));

            if ($brand) {
                $result[] = [
                    'type'          => 'brand',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => null,
                    'path'          => [],
                    'stock'         => true,
                ];

                if ($breakOnFirst) {
                    return $result;
                }
            }
        }

        if ($toBrand === false) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select
                ->columns(['id', 'catname', 'item_type_id'])
                ->where([
                    'id' => $id,
                    new Sql\Predicate\In('item_type_id', [Item::CATEGORY, Item::PERSON]),
                ]);

            $category = currentFromResultSetInterface($this->itemTable->selectWith($select));

            if ($category) {
                switch ($category['item_type_id']) {
                    case Item::CATEGORY:
                        $result[] = [
                            'type'             => 'category',
                            'category_catname' => $category['catname'],
                        ];

                        if ($breakOnFirst) {
                            return $result;
                        }
                        break;

                    case Item::PERSON:
                        $result[] = [
                            'type' => 'person',
                            'id'   => $category['id'],
                        ];

                        if ($breakOnFirst) {
                            return $result;
                        }
                        break;
                }
            }
        }

        $parentRows = $this->itemParent->getParentRows($id, $stockFirst);

        foreach ($parentRows as $parentRow) {
            $paths = $this->getCataloguePaths($parentRow['parent_id'], $options);
            foreach ($paths as $path) {
                switch ($path['type']) {
                    case 'brand':
                        $result[] = [
                            'type'          => 'brand-item',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $parentRow['catname'],
                            'path'          => [],
                            'stock'         => (int) $parentRow['type'] === ItemParent::TYPE_DEFAULT,
                        ];
                        break;
                    case 'brand-item':
                        $isStock  = $path['stock'] && ((int) $parentRow['type'] === ItemParent::TYPE_DEFAULT);
                        $result[] = [
                            'type'          => $path['type'],
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => array_merge($path['path'], [$parentRow['catname']]),
                            'stock'         => $isStock,
                        ];
                        break;
                }
            }

            if ($stockFirst) {
                usort($result, function ($a, $b) {
                    if ($a['stock']) {
                        return $b['stock'] ? 0 : -1;
                    }
                    return $b['stock'] ? 1 : 0;
                });
            }

            if ($breakOnFirst && count($result)) {
                $result = [$result[0]]; // truncate to first
                if ($stockFirst) {
                    if ($result[0]['stock']) {
                        return $result;
                    }
                } else {
                    return [$result[0]];
                }
            }
        }

        if ($breakOnFirst && count($result) > 1) {
            $result = [$result[0]]; // truncate to first
        }

        return $result;
    }
}
