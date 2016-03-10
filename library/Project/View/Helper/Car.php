<?php

class Project_View_Helper_Car extends Zend_View_Helper_HtmlElement
{
    /**
     * @var Cars_Row
     */
    private $_car = null;

    private $_monthFormat = '<small class="month">%02d.</small>';
    
    private $_textMonthFormat = '%02d.';

    /**
     * @var Brands_Cars
     */
    private $_brandCarTable;

    /**
     * @var Car_Parent
     */
    private $_carParentTable;

    /**
     * @var Brands
     */
    private $_brandTable;

    /**
     * @var Spec
     */
    private $_specTable;

    /**
     * @return Spec
     */
    private function _getSpecTable()
    {
        return $this->_specTable
            ? $this->_specTable
            : $this->_specTable = new Spec();
        }

    public function car(Cars_Row $car = null)
    {
        $this->_car = $car;

        return $this;
    }

    public function htmlTitle(array $car)
    {
        $defaults = array(
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
        );
        $car = array_replace($defaults, $car);

        $view = $this->view;

        $result = $view->escape($car['name']);

        if ($car['spec']) {
            if ($car['spec_full']) {
                $result .= ' <span class="label label-primary" title="'.$view->escape($car['spec_full']).'" data-toggle="tooltip" data-placement="top">' . $view->escape($car['spec']) . '</span>';
            } else {
                $result .= ' <span class="label label-primary">' . $view->escape($car['spec']) . '</span>';
            }
        }

        if (strlen($car['body']) > 0) {
            $result .= ' ('.$view->escape($car['body']).')';
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

            $mylabel = '<span title="модельный год">';
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
                $result .= '<small> \'<span class="realyears" title="года выпуска">';

                if ($equalM) {
                    $result .= sprintf($this->_monthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em)
                            $result .= '<small class="month">'.($bm ? sprintf('%02d', $bm) : '??').'—'.($em ? sprintf('%02d', $em) : '??').'.</small>'.$by;
                        else
                            $result .= $by;
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->_monthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->_monthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->_monthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                ? '–'.($em ? sprintf($this->_monthFormat, $em) : '').$ey
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
                    $result .= sprintf($this->_monthFormat, $bm).$by;
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
                            $result .=  (($bm ? sprintf($this->_monthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->_monthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->_monthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                ? '–'.($em ? sprintf($this->_monthFormat, $em) : '').$ey
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

    public function textTitle($car)
    {
        $defaults = array(
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
        );
        $car = array_replace($defaults, $car);

        $view = $this->view;

        $result = $car['name'];

        if ($car['spec']) {
            $result .= ' ' . $car['spec'];
        }

        if (strlen($car['body']) > 0) {
            $result .= ' ('.$car['body'].')';
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

            $mylabel = '';

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

            $result = $mylabel . ' ' . $result;

            if ($by > 0 || $ey > 0) {
                $result .= ' \'';

                if ($equalM) {
                    $result .= sprintf($this->_textMonthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em)
                            $result .= ($bm ? sprintf('%02d', $bm) : '??').'—'.($em ? sprintf('%02d', $em) : '??').'.'.$by;
                            else
                                $result .= $by;
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->_textMonthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->_textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->_textMonthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                    $ey
                                    ? '–'.($em ? sprintf($this->_textMonthFormat, $em) : '').$ey
                                    : (
                                            $car['today']
                                            ? ($by < $cy ? '–'.$view->translate('present-time-abbr') : '')
                                            : ($by < $cy ? '–????' : '')
                                            )
                                    );
                        }
                    }
                }
            }
        } else {

            if ($by > 0 || $ey > 0) {
                $result .= " '";

                if ($equalM) {
                    $result .= sprintf($this->_textMonthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em) {
                            $result .= ($bm ? sprintf('%02d', $bm) : '??').
                            '–'.
                            ($em ? sprintf('%02d', $em) : '??').'.'.$by;
                        } else {
                            $result .= $by;
                        }
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->_textMonthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->_textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->_textMonthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                    ? '–'.($em ? sprintf($this->_textMonthFormat, $em) : '').$ey
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

    public function title()
    {
        if (!$this->_car) {
            return false;
        }

        $car = $this->_car;

        $spec = null;
        $specFull = null;
        if ($car->spec_id) {
            $specRow = $this->_getSpecTable()->find($car->spec_id)->current();
            if ($specRow) {
                $spec = $specRow->short_name;
                $specFull = $specRow->name;
            }
        }

        return $this->htmlTitle(array(
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
        ));
    }

    public function catalogueLinks()
    {
        if (!$this->_car) {
            return array();
        }

        $result = array();

        foreach ($this->_carPublicUrls($this->_car) as $url) {
            $result[] = array(
                'url' => $this->view->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car',
                    'brand_catname' => $url['brand_catname'],
                    'car_catname'   => $url['car_catname'],
                    'path'          => $url['path']
                ), 'catalogue', true)
            );
        }

        return $result;
    }

    public function cataloguePaths()
    {
        return $this->_carPublicUrls($this->_car);
    }

    /**
     * @return Brands_Cars
     */
    private function _getBrandCarTable()
    {
        return $this->_brandCarTable
            ? $this->_brandCarTable
            : $this->_brandCarTable = new Brands_Cars();
    }

    /**
     * @return Brands
     */
    private function _getBrandTable()
    {
        return $this->_brandTable
            ? $this->_brandTable
            : $this->_brandTable = new Brands();
    }

    /**
     * @return Car_Parent
     */
    private function _getCarParentTable()
    {
        return $this->_carParentTable
            ? $this->_carParentTable
            : $this->_carParentTable = new Car_Parent();
    }

    private function _carPublicUrls(Cars_Row $car)
    {
        return $this->_walkUpUntilBrand($car->id, array());
    }

    private function _walkUpUntilBrand($id, array $path)
    {
        $urls = array();

        $brandCarRows = $this->_getBrandCarTable()->fetchAll(array(
            'car_id = ?' => $id
        ));

        foreach ($brandCarRows as $brandCarRow) {

            $brand = $this->_getBrandTable()->find($brandCarRow->brand_id)->current();
            if (!$brand) {
                throw new Exception("Broken link `{$brandCarRow->brand_id}`");
            }

            $urls[] = array(
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname ? $brandCarRow->catname : 'car' . $brandCarRow->car_id,
                'path'          => $path
            );
        }

        $carParentTable = $this->_getCarParentTable();

        $parentRows = $this->_getCarParentTable()->fetchAll(array(
            'car_id = ?' => $id
        ));
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->_walkUpUntilBrand($parentRow->parent_id, array_merge(array($parentRow->catname), $path))
            );
        }

        return $urls;
    }
}