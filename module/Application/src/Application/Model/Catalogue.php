<?php

namespace Application\Model;

use Autowp\Image\Storage\Request;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Model\DbTable\Vehicle\Type as VehicleType;

use Exception;

class Catalogue
{
    private $picturesPerPage = 20;

    private $carsPerPage = 7;

    /**
     * @var BrandTable
     */
    private $brandTable;

    /**
     * @var Vehicle
     */
    private $itemTable;

    /**
     * @var VehicleParent
     */
    private $carParentTable;

    /**
     * @var VehicleType
     */
    private $carTypeTable;

    /**
     * @var Picture
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
     * @return BrandTable
     */
    public function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new BrandTable();
    }

    /**
     * @return Vehicle
     */
    public function getItemTable()
    {
        return $this->itemTable
            ? $this->itemTable
            : $this->itemTable = new Vehicle();
    }

    /**
     * @return VehicleParent
     */
    public function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new VehicleParent();
    }

    /**
     * @return VehicleType
     */
    public function getCarTypeTable()
    {
        return $this->carTypeTable
        ? $this->carTypeTable
        : $this->carTypeTable = new VehicleType();
    }

    /**
     * @return Picture
     */
    public function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new Picture();
    }

    /**
     * @param VehicleRow $car
     * @return array
     */
    public function cataloguePaths(VehicleRow $car)
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
