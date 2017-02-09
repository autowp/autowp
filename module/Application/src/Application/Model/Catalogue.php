<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
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
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    /**
     * @var DbTable\Vehicle\Type
     */
    private $carTypeTable;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @var TableGateway
     */
    private $itemTable2;

    /**
     * @var TableGateway
     */
    private $itemParentTable2;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->itemTable2 = new TableGateway('item', $adapter);
        $this->itemParentTable2 = new TableGateway('item_parent', $adapter);
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
     * @return DbTable\Item\ParentTable
     */
    public function getCarParentTable()
    {
        return $this->itemParentTable
            ? $this->itemParentTable
            : $this->itemParentTable = new DbTable\Item\ParentTable();
    }

    /**
     * @return DbTable\Vehicle\Type
     */
    public function getCarTypeTable()
    {
        return $this->carTypeTable
            ? $this->carTypeTable
            : $this->carTypeTable = new DbTable\Vehicle\Type();
    }

    /**
     * @return DbTable\Picture
     */
    public function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new DbTable\Picture();
    }

    /**
     * @param int $id
     * @param array $options
     * @return array
     */
    public function getCataloguePaths($id, array $options = [])
    {
        $defaults = [
            'breakOnFirst' => false,
            'toBrand'      => null
        ];
        $options = array_replace($defaults, $options);

        $breakOnFirst = (bool)$options['breakOnFirst'];
        $toBrand = (int)$options['toBrand'];

        $result = [];

        $brand = null;

        if (! $toBrand || $id == $toBrand) {
            $select = new Sql\Select($this->itemTable2->getTable());
            $select
                ->columns(['catname'])
                ->where([
                    'id'           => $id,
                    'item_type_id' => DbTable\Item\Type::BRAND
                ]);

            $brand = $this->itemTable2->selectWith($select)->current();
        }

        if ($brand) {
            $result[] = [
                'type'          => 'brand',
                'brand_catname' => $brand['catname'],
                'car_catname'   => null,
                'path'          => []
            ];

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }


        $select = new Sql\Select($this->itemParentTable2->getTable());
        $select
            ->columns(['parent_id', 'catname'])
            ->where(['item_id' => $id])
            ->order([
                new Sql\Expression('type = ? desc', DbTable\Item\ParentTable::TYPE_DEFAULT)
            ]);

        $parentRows = $this->itemParentTable2->selectWith($select);

        foreach ($parentRows as $parentRow) {
            $paths = $this->getCataloguePaths($parentRow['parent_id'], $options);
            foreach ($paths as $path) {
                switch ($path['type']) {
                    case 'brand':
                        $result[] = [
                            'type'          => 'brand-item',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $parentRow['catname'],
                            'path'          => []
                        ];
                        break;
                    case 'brand-item':
                        $result[] = [
                            'type'          => $path['type'],
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => array_merge($path['path'], [$parentRow['catname']])
                        ];
                        break;
                }
            }
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
