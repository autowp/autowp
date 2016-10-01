<?php

namespace Application;

class VehicleNameFormatter
{
    private $translator;

    private $textMonthFormat = '%02d.';

    public function __construct($translator)
    {
        $this->translator = $translator;
    }

    private function translate($string, $language)
    {
        return $this->translator->translate($string, 'default', $language);
    }

    public function format($car, $language)
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
                        $mylabel .= $bmy . '–' . $this->translate('present-time-abbr', $language);
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
                    $result .= sprintf($this->textMonthFormat, $bm).$by;
                } else {
                    if ($equalY) {
                        if ($bm && $em)
                            $result .= ($bm ? sprintf('%02d', $bm) : '??').'—'.($em ? sprintf('%02d', $em) : '??').'.'.$by;
                            else
                                $result .= $by;
                    } else {
                        if ($equalS) {
                            $result .=  (($bm ? sprintf($this->textMonthFormat, $bm) : '').$by).
                                        '–'.
                                        ($em ? sprintf($this->textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->textMonthFormat, $bm) : '').($by ? $by : '????')).
                                        (
                                            $ey
                                                ? '–'.($em ? sprintf($this->textMonthFormat, $em) : '').$ey
                                                : (
                                                    $car['today']
                                                        ? ($by < $cy ? '–'.$this->translate('present-time-abbr', $language) : '')
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
                    $result .= sprintf($this->textMonthFormat, $bm).$by;
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
                            $result .=  (($bm ? sprintf($this->textMonthFormat, $bm) : '').$by).
                            '–'.
                            ($em ? sprintf($this->textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
                        } else {
                            $result .=  (($bm ? sprintf($this->textMonthFormat, $bm) : '').($by ? $by : '????')).
                            (
                                $ey
                                    ? '–'.($em ? sprintf($this->textMonthFormat, $em) : '').$ey
                                    : (
                                        $car['today']
                                            ? ($by < $cy ? '–'.$this->translate('present-time-abbr', $language) : '')
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
}