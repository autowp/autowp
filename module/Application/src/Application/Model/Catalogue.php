<?php

namespace Application\Model;

use Autowp\Image\Storage\Request;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\BrandItem;
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
     * @var BrandItem
     */
    private $brandItemTable;

    /**
     * @var Vehicle
     */
    private $carTable;

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
    public function carsOrdering()
    {
        return [
            'cars.begin_order_cache',
            'cars.end_order_cache',
            'cars.name',
            'cars.body',
            'cars.spec_id'
        ];

        /*return [
            new Zend_Db_Expr('cars.begin_year IS NULL'),
            'cars.begin_year',
            new Zend_Db_Expr('cars.begin_month IS NULL'),
            'cars.begin_month',
            new Zend_Db_Expr('cars.end_year=0'),
            'cars.end_year',
            new Zend_Db_Expr('cars.end_month IS NULL'),
            'cars.end_month',
            'cars.name',
            'cars.body'
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
     * @return BrandItem
     */
    public function getBrandItemTable()
    {
        return $this->brandItemTable
            ? $this->brandItemTable
            : $this->brandItemTable = new BrandItem();
    }

    /**
     * @return Vehicle
     */
    public function getCarTable()
    {
        return $this->carTable
            ? $this->carTable
            : $this->carTable = new Vehicle();
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

        $brandItemRows = $this->getBrandItemTable()->fetchAll([
            'item_id = ?' => $id
        ]);

        foreach ($brandItemRows as $brandItemRow) {
            $brand = $this->getBrandTable()->find($brandItemRow->brand_id)->current();
            if (! $brand) {
                throw new Exception("Broken link `{$brandItemRow->brand_id}`");
            }

            $urls[] = [
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandItemRow->catname,
                'path'          => $path
            ];
        }

        $parentRows = $this->getCarParentTable()->fetchAll([
            'item_id = ?' => $id
        ]);
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
