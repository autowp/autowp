<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\BrandItem;
use Application\Model\DbTable\Spec;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\VehicleNameFormatter;

use Exception;

class Car extends AbstractHelper
{
    /**
     * @var VehicleRow
     */
    private $car = null;

    private $monthFormat = '<small class="month">%02d.</small>';

    private $textMonthFormat = '%02d.';

    /**
     * @var BrandItem
     */
    private $brandItemTable;

    /**
     * @var VehicleParent
     */
    private $carParentTable;

    /**
     * @var BrandTable
     */
    private $brandTable;

    /**
     * @var Spec
     */
    private $specTable;

    /**
     * @var VehicleNameFormatter
     */
    private $vehicleNameFormatter;

    public function __construct(VehicleNameFormatter $vehicleNameFormatter)
    {
        $this->vehicleNameFormatter = $vehicleNameFormatter;
    }

    /**
     * @return Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new Spec();
    }

    public function __invoke(VehicleRow $car = null)
    {
        $this->car = $car;

        return $this;
    }

    public function htmlTitle(array $car)
    {
        return $this->vehicleNameFormatter->formatHtml($car, $this->view->language());
    }

    public function textTitle(array $car)
    {
        return $this->vehicleNameFormatter->format($car, $this->view->language());
    }

    public function title()
    {
        if (! $this->car) {
            return false;
        }

        $car = $this->car;

        $spec = null;
        $specFull = null;
        if ($car->spec_id) {
            $specRow = $this->getSpecTable()->find($car->spec_id)->current();
            if ($specRow) {
                $spec = $specRow->short_name;
                $specFull = $specRow->name;
            }
        }

        return $this->htmlTitle([
            'begin_model_year' => $car['begin_model_year'],
            'end_model_year'   => $car['end_model_year'],
            'spec'             => $spec,
            'spec_full'        => $specFull,
            'body'             => $car['body'],
            'name'             => $car['name'],
            'begin_year'       => $car['begin_year'],
            'end_year'         => $car['end_year'],
            'today'            => $car['today'],
            'begin_month'      => $car['begin_month'],
            'end_month'        => $car['end_month']
        ]);
    }

    public function catalogueLinks()
    {
        if (! $this->car) {
            return [];
        }

        $result = [];

        foreach ($this->carPublicUrls($this->car) as $url) {
            $result[] = [
                'url' => $this->view->url([
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-item',
                    'brand_catname' => $url['brand_catname'],
                    'car_catname'   => $url['car_catname'],
                    'path'          => $url['path']
                ], 'catalogue', true)
            ];
        }

        return $result;
    }

    public function cataloguePaths()
    {
        return $this->carPublicUrls($this->car);
    }

    /**
     * @return BrandItem
     */
    private function getBrandItemTable()
    {
        return $this->brandItemTable
            ? $this->brandItemTable
            : $this->brandItemTable = new BrandItem();
    }

    /**
     * @return BrandTable
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new BrandTable();
    }

    /**
     * @return VehicleParent
     */
    private function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new VehicleParent();
    }

    private function carPublicUrls(VehicleRow $car)
    {
        return $this->walkUpUntilBrand($car->id, []);
    }

    private function walkUpUntilBrand($id, array $path)
    {
        $urls = [];

        $brandItemRows = $this->getBrandItemTable()->fetchAll([
            'car_id = ?' => $id
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

        $carParentTable = $this->getCarParentTable();

        $parentRows = $this->getCarParentTable()->fetchAll([
            'car_id = ?' => $id
        ]);
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand($parentRow->parent_id, array_merge([$parentRow->catname], $path))
            );
        }

        return $urls;
    }
}
