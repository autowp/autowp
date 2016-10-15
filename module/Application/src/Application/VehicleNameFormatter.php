<?php

namespace Application;

use Zend\View\Renderer\PhpRenderer;

class VehicleNameFormatter
{
    private $translator;

    /**
     * @var PhpRenderer
     */
    private $renderer;

    private $monthFormat = '<small class="month">%02d.</small>';

    private $textMonthFormat = '%02d.';

    public function __construct($translator, PhpRenderer $renderer)
    {
        $this->translator = $translator;
        $this->renderer = $renderer;
    }

    private function translate($string, $language)
    {
        return $this->translator->translate($string, 'default', $language);
    }

    public function formatHtml(array $car, $language)
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

        $result = $this->renderer->escapeHtml($car['name']);

        if ($car['spec']) {
            if ($car['spec_full']) {
                $result .= ' <span class="label label-primary" title="'.$this->renderer->escapeHtmlAttr($car['spec_full']).'" data-toggle="tooltip" data-placement="top">' . $this->renderer->escapeHtml($car['spec']) . '</span>';
            } else {
                $result .= ' <span class="label label-primary">' . $this->renderer->escapeHtml($car['spec']) . '</span>';
            }
        }

        if (strlen($car['body']) > 0) {
            $result .= ' ('.$this->renderer->escapeHtml($car['body']).')';
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

            $mylabel = '<span title="' . $this->renderer->escapeHtmlAttr($this->translate('carlist/model-years', $language)) . '">';
            if ($emy == $bmy) {
                $mylabel .= $bmy;
            } elseif ($bms == $ems) {
                $mylabel .= $bmy.'–'.sprintf('%02d', $emy%100);
            } elseif (!$emy) {
                if ($car['today']) {
                    if ($bmy >= $cy) {
                        $mylabel .= $bmy;
                    } else {
                        $mylabel .= $bmy.'–'.$this->translate('present-time-abbr', $language);
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
                $result .=
                    '<small>'.
                        ' \'<span class="realyears" title="'.$this->renderer->escapeHtmlAttr($this->translate('carlist/years', $language)).'">' .
                            $this->renderYearsHtml($car['today'], $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language) .
                        '</span>' .
                    '</small>';
            }
        } else {

            if ($by > 0 || $ey > 0) {
                $result .= " '" . $this->renderYearsHtml($car['today'], $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language);
            }
        }

        return $result;
    }

    public function format(array $car, $language)
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
                        $mylabel .= $bmy . '–' . $this->translate('present-time-abbr', $language);
                    }
                } else {
                    $mylabel .= $bmy.'–??';
                }
            } else {
                $mylabel .= $bmy.'–'.$emy;
            }

            $result = $mylabel . ' ' . $result;
        }

        if ($by > 0 || $ey > 0) {
            $result .= " '" . $this->renderYears($car['today'], $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language);
        }

        return $result;
    }

    private function renderYears($today, $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language)
    {
        if ($equalM) {
            return sprintf($this->textMonthFormat, $bm) . $by;
        }

        if ($equalY) {
            if ($bm && $em) {
                return ($bm ? sprintf('%02d', $bm) : '??').
                       '–'.
                       ($em ? sprintf('%02d', $em) : '??') . '.' . $by;
            }

            return $by;
        }

        if ($equalS) {
            return (($bm ? sprintf($this->textMonthFormat, $bm) : '') . $by).
                   '–'.
                   ($em ? sprintf($this->textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
        }

        $cy = (int)date('Y');

        return  (($bm ? sprintf($this->textMonthFormat, $bm) : '').($by ? $by : '????')).
                (
                    $ey
                        ? '–'.($em ? sprintf($this->textMonthFormat, $em) : '').$ey
                        : (
                            $today
                                ? ($by < $cy ? '–'.$this->translate('present-time-abbr', $language) : '')
                                : ($by < $cy ? '–????' : '')
                        )
                );
    }

    private function renderYearsHtml($today, $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language)
    {
        if ($equalM) {
            return sprintf($this->monthFormat, $bm).$by;
        }

        if ($equalY) {
            if ($bm && $em) {
                return  '<small class="month">'.($bm ? sprintf('%02d', $bm) : '??').
                        '–'.
                        ($em ? sprintf('%02d', $em) : '??').'.</small>'.$by;
            }

            return $by;
        }

        if ($equalS) {
            return  (($bm ? sprintf($this->monthFormat, $bm) : '').$by).
                    '–'.
                    ($em ? sprintf($this->monthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
        }

        $cy = (int)date('Y');

        return  (($bm ? sprintf($this->monthFormat, $bm) : '').($by ? $by : '????')).
                (
                    $ey
                        ? '–'.($em ? sprintf($this->monthFormat, $em) : '').$ey
                        : (
                            $today
                                ? ($by < $cy ? '–'.$this->translate('present-time-abbr', $language) : '')
                                : ($by < $cy ? '–????' : '')
                        )
                );
    }
}