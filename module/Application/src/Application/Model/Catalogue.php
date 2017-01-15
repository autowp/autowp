<?php

namespace Application\Model;

use Autowp\Image\Storage\Request;

use Application\Model\DbTable;

use Exception;

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
     * @param DbTable\Item\Row $car
     * @return array
     */
    public function cataloguePaths(DbTable\Item\Row $car)
    {
        return $this->walkUpUntilBrand($car->id, []);
    }

    /**
     * @param int $id
     * @param array $path
     * @throws Exception
     * @return array
     */
    private function walkUpUntilBrand($id, array $path)
    {
        $urls = [];

        $parentRows = $this->getCarParentTable()->fetchAll([
            'item_id = ?' => $id
        ]);

        foreach ($parentRows as $parentRow) {
            $brand = $this->getItemTable()->fetchRow([
                'id = ?'           => $parentRow->parent_id,
                'item_type_id = ?' => DbTable\Item\Type::BRAND
            ]);
            if ($brand) {
                $urls[] = [
                    'brand_catname' => $brand->catname,
                    'car_catname'   => $parentRow->catname,
                    'path'          => $path
                ];
            }
        }

        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand($parentRow->parent_id, array_merge([$parentRow->catname], $path))
            );
        }

        return $urls;
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
