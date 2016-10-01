<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\BrandCar;
use Application\VehicleNameFormatter;

use Car_Parent;
use Car_Row;
use Spec;

use Exception;

class Car extends AbstractHelper
{
    /**
     * @var Car_Row
     */
    private $_car = null;

    private $monthFormat = '<small class="month">%02d.</small>';

    private $textMonthFormat = '%02d.';

    /**
     * @var BrandCar
     */
    private $brandCarTable;

    /**
     * @var Car_Parent
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

    public function __invoke(Car_Row $car = null)
    {
        $this->_car = $car;

        return $this;
    }

    public function htmlTitle(array $car)
    {
        $defaults = [
            'begin_model_year' => null,
            'end_model_year'   => null,
            'spec'             => null,
            'spec_full'        => null,
            'body'             => null,
            'name'             => null,
            'begin_year'       => null,
            'end_year'         => null,
            'today'            => null,
            'begin_month'      => null,
            'end_month'        => null
        ];
        $car = array_replace($defaults, $car);

        $view = $this->view;

        $result = $view->escapeHtml($car['name']);

        if ($car['spec']) {
            if ($car['spec_full']) {
                $result .= ' <span class="label label-primary" title="'.$view->escapeHtmlAttr($car['spec_full']).'" data-toggle="tooltip" data-placement="top">' . $view->escapeHtml($car['spec']) . '</span>';
            } else {
                $result .= ' <span class="label label-primary">' . $view->escapeHtml($car['spec']) . '</span>';
            }
        }

        if (strlen($car['body']) > 0) {
            $result .= ' ('.$view->escapeHtml($car['body']).')';
        }

        $by = $car['begin_year'];
        $bm = $car['begin_month'];
        $ey = $car['end_year'];
        $em = $car['end_month'];
        $cy = (int)date('Y');
        $cm = (int)date('m');

        $bmy = $car['begin_model_year'];
        $emy = $car['end_model_year'];

        $bs = (int)($by/100);
        $es = (int)($ey/100);

        $bms = (int)($bmy/100);
        $ems = (int)($emy/100);

        $useModelYear = (bool)$bmy;
        /*if ($useModelYear) {
         if ($bmy == $by && $emy == $ey) {
        $useModelYear = false;
        }
        }*/

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {

            $mylabel = '<span title="' . $view->escapeHtmlAttr($view->translate('carlist/model-years')) . '">';
            if ($emy == $bmy) {
                $mylabel .= $bmy;
            } elseif ($bms == $ems) {
                $mylabel .= $bmy.'–'.sprintf('%02d', $emy%100);
            } elseif (!$emy) {
                if ($car['today']) {
                    if ($bmy >= $cy) {
                        $mylabel .= $bmy;
                    } else {
                        $mylabel .= $bmy.'–'.$view->translate('present-time-abbr');
                    }
                } else {
                    $mylabel .= $bmy.'–??';
                }
            } else {
                $mylabel .= $bmy.'–'.$emy;
            }

            $mylabel .= '</span>';

            $result = $mylabel . ' ' . $result;

            if ($by > 0 || $ey > 0) {
                $result .= '<small> \'<span class="realyears" title="'.$this->view->escapeHtmlAttr($this->view->translate('carlist/years')).'">';

                if ($equalM) {
                    $result .= sprintf($this->monthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em)
                            $result .= '<small class="month">'.($bm ? sprintf('%02d', $bm) : '??').'—'.($em ? sprintf('%02d', $em) : '??').'.</small>'.$by;
                        else
                            $result .= $by;
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->monthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->monthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->monthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                ? '–'.($em ? sprintf($this->monthFormat, $em) : '').$ey
                                : (
                                    $car['today']
                                    ? ($by < $cy ? '–'.$view->translate('present-time-abbr') : '')
                                    : ($by < $cy ? '–????' : '')
                                )
                            );
                        }
                    }
                }

                $result .= "</span></small>";
            }
        } else {

            if ($by > 0 || $ey > 0) {
                $result .= " '";

                if ($equalM) {
                    $result .= sprintf($this->monthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em) {
                            $result .= '<small class="month">'.($bm ? sprintf('%02d', $bm) : '??').
                            '–'.
                            ($em ? sprintf('%02d', $em) : '??').'.</small>'.$by;
                        } else {
                            $result .= $by;
                        }
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->monthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->monthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->monthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                ? '–'.($em ? sprintf($this->monthFormat, $em) : '').$ey
                                : (
                                    $car['today']
                                    ? ($by < $cy ? '–'.$view->translate('present-time-abbr') : '')
                                    :($by < $cy ? '–????' : '')
                                )
                            );
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function textTitle(array $car)
    {
        return $this->vehicleNameFormatter->format($car, $this->view->language);
    }

    public function title()
    {
        if (!$this->_car) {
            return false;
        }

        $car = $this->_car;

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
            'name'             => $car['caption'],
            'begin_year'       => $car['begin_year'],
            'end_year'         => $car['end_year'],
            'today'            => $car['today'],
            'begin_month'      => $car['begin_month'],
            'end_month'        => $car['end_month']
        ]);
    }

    public function catalogueLinks()
    {
        if (!$this->_car) {
            return [];
        }

        $result = [];

        foreach ($this->_carPublicUrls($this->_car) as $url) {
            $result[] = [
                'url' => $this->view->url([
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car',
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
        return $this->_carPublicUrls($this->_car);
    }

    /**
     * @return BrandCar
     */
    private function getBrandCarTable()
    {
        return $this->brandCarTable
            ? $this->brandCarTable
            : $this->brandCarTable = new BrandCar();
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
     * @return Car_Parent
     */
    private function _getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new Car_Parent();
    }

    private function _carPublicUrls(Car_Row $car)
    {
        return $this->_walkUpUntilBrand($car->id, []);
    }

    private function _walkUpUntilBrand($id, array $path)
    {
        $urls = [];

        $brandCarRows = $this->getBrandCarTable()->fetchAll([
            'car_id = ?' => $id
        ]);

        foreach ($brandCarRows as $brandCarRow) {

            $brand = $this->getBrandTable()->find($brandCarRow->brand_id)->current();
            if (!$brand) {
                throw new Exception("Broken link `{$brandCarRow->brand_id}`");
            }

            $urls[] = [
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname ? $brandCarRow->catname : 'car' . $brandCarRow->car_id,
                'path'          => $path
            ];
        }

        $carParentTable = $this->_getCarParentTable();

        $parentRows = $this->_getCarParentTable()->fetchAll([
            'car_id = ?' => $id
        ]);
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->_walkUpUntilBrand($parentRow->parent_id, array_merge([$parentRow->catname], $path))
            );
        }

        return $urls;
    }
}