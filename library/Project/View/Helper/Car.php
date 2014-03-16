<?php

class Project_View_Helper_Car extends Zend_View_Helper_HtmlElement
{
    /**
     * @var Cars_Row
     */
    protected $_car = null;

    protected $_monthFormat = '<small class="month">%02d.</small>';

    /**
     * @var Car_Language
     */
    protected $_carLangTable;

    protected function _getCarLanguageTable()
    {
        return $this->_carLangTable
            ? $this->_carLangTable
            : $this->_carLangTable = new Car_Language();
    }

    public function car(Cars_Row $car = null)
    {
        $this->_car = $car;

        return $this;
    }

    public function title()
    {
        if (!$this->_car) {
            return false;
        }

        $car = $this->_car;
        $view = $this->view;

        $carLangRow = $this->_getCarLanguageTable()->fetchRow(array(
            'car_id = ?'   => $this->_car->id,
            'language = ?' => $view->language()->get()
        ));

        $result = $view->escape($carLangRow ? $carLangRow->name : $car->caption);

        if (strlen($car->body) > 0) {
            $result .= ' ('.$view->escape($car->body).')';
        }

        $by = $car->begin_year;
        $bm = $car->begin_month;
        $ey = $car->end_year;
        $em = $car->end_month;
        $cy = (int)date('Y');
        $cm = (int)date('m');

        $bmy = $car->begin_model_year;
        $emy = $car->end_model_year;

        $bs = (int)($by/100);
        $es = (int)($ey/100);

        $bms = (int)($bmy/100);
        $ems = (int)($emy/100);

        $useModelYear = (bool)$bmy;
        if ($useModelYear) {
            if ($bmy == $by && $emy == $ey) {
                $useModelYear = false;
            }
        }

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {

            $mylabel = "<span title=\"модельный год\">";
            if ($emy == $bmy) {
                $mylabel .= $bmy;
            } elseif ($bms == $ems) {
                $mylabel .= $bmy.'–'.sprintf('%02d', $emy%100);
            } else {
                $mylabel .= $bmy.'–'.$emy;
            }

            $mylabel .= '</span>';

            $result = $mylabel . ' ' . $result;

            if ($by > 0 || $ey > 0) {
                $result .= ' \'<span class="realyears" title="года выпуска">';

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
                                    ?
                                    '–'.($em ? sprintf($this->_monthFormat, $em) : '').$ey
                                    :
                                    (
                                            $car->today
                                            ?
                                            ($by < $cy ? '–'.$view->translate('present-time-abbr') : '')
                                            :
                                            ($by < $cy ? '–????' : '')
                                    )
                            );
                        }
                    }
                }

                $result .= "</span>";
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
                                            $car->today
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

    public function catalogueLinks()
    {
        $result = array();

        if (!$this->_car) {
            return $result;
        }

        $car = $this->_car;
        $view = $this->view;



        foreach ($car->findBrandsViaBrands_Cars() as $brand) {
            $result[] = array(
                'url' => $view->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'car',
                    'brand_catname' => $brand->folder,
                    'car_id'        => $car->id
                ), 'catalogue', true)
            );
        }

        foreach ($car->findModels_Cars() as $modelCars) {
            $model = $modelCars->findParentModels();
            $brand = $model->findParentBrands();

            $result[] = array(
                'url' => $view->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'model-car',
                    'brand_catname' => $brand->folder,
                    'model_catname' => $model->folder,
                    'car_id'        => $car->id
                ), 'catalogue', true)
            );
        }

        return $result;
    }
}