<?php

namespace Application\Model;

use InvalidArgumentException;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\Image\Storage\Request;

use Application\Model\DbTable;

class Catalogue
{
    private $picturesPerPage = 20;

    private $carsPerPage = 7;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var TableGateway
     */
    private $itemTable2;

    /**
     * @var ItemParent
     */
    private $itemParent;

    public function __construct(ItemParent $itemParent, TableGateway $itemTable)
    {
        $this->itemTable2 = $itemTable;
        $this->itemParent = $itemParent;
    }

    /**
     * @return array
     */
    public function itemOrdering()
    {
        return [
            'item.begin_order_cache',
            'item.end_order_cache',
            'item.name',
            'item.body',
            'item.spec_id'
        ];

        /*return [
            new Zend_Db_Expr('item.begin_year IS NULL'),
            'item.begin_year',
            new Zend_Db_Expr('item.begin_month IS NULL'),
            'item.begin_month',
            new Zend_Db_Expr('item.end_year=0'),
            'item.end_year',
            new Zend_Db_Expr('item.end_month IS NULL'),
            'item.end_month',
            'item.name',
            'item.body'
        ];*/
    }

    /**
     * @return array
     */
    public function picturesOrdering()
    {
        return [
            'pictures.width DESC', 'pictures.height DESC',
            'pictures.add_date DESC', 'pictures.id DESC'
        ];
    }

    public function getCarsPerPage()
    {
        return $this->carsPerPage;
    }

    public function getPicturesPerPage()
    {
        return $this->picturesPerPage;
    }

    /**
     * @return DbTable\Item
     */
    public function getItemTable()
    {
        return $this->itemTable
            ? $this->itemTable
            : $this->itemTable = new DbTable\Item();
    }

    /**
     * @param int $id
     * @param array $options
     * @return array
     */
    public function getCataloguePaths($id, array $options = [])
    {
        $id = (int)$id;
        if (! $id) {
            throw new InvalidArgumentException("Unexpected `id`");
        }

        $defaults = [
            'breakOnFirst' => false,
            'toBrand'      => null,
            'stockFirst'   => false
        ];
        $options = array_replace($defaults, $options);

        $breakOnFirst = (bool)$options['breakOnFirst'];
        $stockFirst = (bool)$options['stockFirst'];
        if (isset($options['toBrand'])) {
            $toBrand = is_bool($options['toBrand']) ? $options['toBrand'] : true;
            $toBrandId = is_bool($options['toBrand']) ? null : (int)$options['toBrand'];
        } else {
            $toBrand = true;
            $toBrandId = null;
        }

        $result = [];

        if (! $toBrandId || $id == $toBrandId) {
            $select = new Sql\Select($this->itemTable2->getTable());
            $select
                ->columns(['catname'])
                ->where([
                    'id'           => $id,
                    'item_type_id' => Item::BRAND
                ]);

            $brand = $this->itemTable2->selectWith($select)->current();

            if ($brand) {
                $result[] = [
                    'type'          => 'brand',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => null,
                    'path'          => [],
                    'stock'         => true
                ];

                if ($breakOnFirst && count($result)) {
                    return $result;
                }
            }
        }

        if ($toBrand === false) {
            $select = new Sql\Select($this->itemTable2->getTable());
            $select
                ->columns(['catname'])
                ->where([
                    'id'           => $id,
                    'item_type_id' => Item::CATEGORY
                ]);

            $category = $this->itemTable2->selectWith($select)->current();

            if ($category) {
                $result[] = [
                    'type'             => 'category',
                    'category_catname' => $category['catname']
                ];

                if ($breakOnFirst && count($result)) {
                    return $result;
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
                            'stock'         => $parentRow['type'] == ItemParent::TYPE_DEFAULT
                        ];
                        break;
                    case 'brand-item':
                        $result[] = [
                            'type'          => $path['type'],
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => array_merge($path['path'], [$parentRow['catname']]),
                            'stock'         => $path['stock'] && ($parentRow['type'] == ItemParent::TYPE_DEFAULT)
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

    private static function between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public function cropParametersExists(array $picture)
    {
        // проверяем установлены ли границы обрезания
        // проверяем верные ли значения границ обрезания
        $canCrop = ! is_null($picture['crop_left']) && ! is_null($picture['crop_top']) &&
            ! is_null($picture['crop_width']) && ! is_null($picture['crop_height']) &&
            self::between($picture['crop_left'], 0, $picture['width']) &&
            self::between($picture['crop_width'], 1, $picture['width']) &&
            self::between($picture['crop_top'], 0, $picture['height']) &&
            self::between($picture['crop_height'], 1, $picture['height']);

        return $canCrop;
    }

    /**
     * @return Request
     */
    public function getPictureFormatRequest(array $picture)
    {
        $options = [
            'imageId' => $picture['image_id']
        ];
        if ($this->cropParametersExists($picture)) {
            $options['crop'] = [
                'left'   => $picture['crop_left'],
                'top'    => $picture['crop_top'],
                'width'  => $picture['crop_width'],
                'height' => $picture['crop_height']
            ];
        }

        return new Request($options);
    }
}
