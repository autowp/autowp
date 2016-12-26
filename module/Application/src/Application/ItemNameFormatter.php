<?php

namespace Application;

use Zend\View\Renderer\PhpRenderer;

class ItemNameFormatter
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
            $attrs = ['class="label label-primary"'];
            if ($car['spec_full']) {
                $attrs = array_merge($attrs, [
                    'title="' . $this->renderer->escapeHtmlAttr($car['spec_full']) . '"',
                    'data-toggle="tooltip"',
                    'data-placement="top"'
                ]);
            }
            $escapedSpec = $this->renderer->escapeHtml($car['spec']);
            $result .= ' <span '.implode(' ', $attrs).'>' . $escapedSpec . '</span>';
        }

        if (strlen($car['body']) > 0) {
            $result .= ' ('.$this->renderer->escapeHtml($car['body']).')';
        }

        $by = (int)$car['begin_year'];
        $bm = (int)$car['begin_month'];
        $ey = (int)$car['end_year'];
        $em = (int)$car['end_month'];
        $cy = (int)date('Y');
        $cm = (int)date('m');

        $bmy = (int)$car['begin_model_year'];
        $emy = (int)$car['end_model_year'];

        $bs = (int)($by / 100);
        $es = (int)($ey / 100);

        $bms = (int)($bmy / 100);
        $ems = (int)($emy / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {
            $title = $this->renderer->escapeHtmlAttr($this->translate('carlist/model-years', $language));
            $result = '<span title="' . $title . '">' .
                          $this->renderer->escapeHtml(
                              $this->getModelYearsPrefix($bmy, $emy, $car['today'], $language)
                          ) .
                      '</span> ' .
                      $result;

            if ($by > 0 || $ey > 0) {
                $title = $this->renderer->escapeHtmlAttr($this->translate('carlist/years', $language));
                $result .=
                    '<small>'.
                        ' \'<span class="realyears" title="'.$title.'">' .
                            $this->renderYearsHtml(
                                $car['today'],
                                $by,
                                $bm,
                                $ey,
                                $em,
                                $equalS,
                                $equalY,
                                $equalM,
                                $language
                            ) .
                        '</span>' .
                    '</small>';
            }
        } else {
            if ($by > 0 || $ey > 0) {
                $result .= " '" . $this->renderYearsHtml(
                    $car['today'],
                    $by,
                    $bm,
                    $ey,
                    $em,
                    $equalS,
                    $equalY,
                    $equalM,
                    $language
                );
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

        $by = (int)$car['begin_year'];
        $bm = (int)$car['begin_month'];
        $ey = (int)$car['end_year'];
        $em = (int)$car['end_month'];
        $cy = (int)date('Y');

        $bmy = (int)$car['begin_model_year'];
        $emy = (int)$car['end_model_year'];

        $bs = (int)($by / 100);
        $es = (int)($ey / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {
            $result = $this->getModelYearsPrefix($bmy, $emy, $car['today'], $language) . ' ' . $result;
        }

        if ($by > 0 || $ey > 0) {
            $result .= " '" . $this->renderYears(
                $car['today'],
                $by,
                $bm,
                $ey,
                $em,
                $equalS,
                $equalY,
                $equalM,
                $language
            );
        }

        return $result;
    }

    private function getModelYearsPrefix($begin, $end, $today, $language)
    {
        $bms = (int)($begin / 100);
        $ems = (int)($end / 100);

        if ($end == $begin) {
            return $begin;
        }

        if ($bms == $ems) {
            return $begin . '–' . sprintf('%02d', $end % 100);
        }

        if (! $begin) {
            return '????–' . $end;
        }

        if ($end) {
            return $begin . '–' . $end;
        }

        if (! $today) {
            return $begin . '–??';
        }

        $currentYear = (int)date('Y');

        if ($begin >= $currentYear) {
            return $begin;
        }

        return $begin . '–' . $this->translate('present-time-abbr', $language);
    }

    private function monthsRange($from, $to)
    {
        return ($from ? sprintf('%02d', $from) : '??') .
               '–'.
               ($to ? sprintf('%02d', $to) : '??');
    }

    private function missedEndYearYearsSuffix($today, $by, $language)
    {
        $cy = (int)date('Y');

        if ($by >= $cy) {
            return '';
        }

        return '–' . ($today ? $this->translate('present-time-abbr', $language) : '????');
    }

    private function renderYears($today, $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language)
    {
        if ($equalM) {
            return sprintf($this->textMonthFormat, $bm) . $by;
        }

        if ($equalY) {
            if ($bm && $em) {
                return $this->monthsRange($bm, $em) . '.' . $by;
            }

            return $by;
        }

        if ($equalS) {
            return (($bm ? sprintf($this->textMonthFormat, $bm) : '') . $by).
                   '–'.
                   ($em ? sprintf($this->textMonthFormat, $em) : '').($em ? $ey : sprintf('%02d', $ey % 100));
        }

        $cy = (int)date('Y');

        return  (($bm ? sprintf($this->textMonthFormat, $bm) : '').($by ? $by : '????')).
                (
                    $ey
                        ? '–'.($em ? sprintf($this->textMonthFormat, $em) : '').$ey
                        : $this->missedEndYearYearsSuffix($today, $by, $language)
                );
    }

    private function renderYearsHtml($today, $by, $bm, $ey, $em, $equalS, $equalY, $equalM, $language)
    {
        if ($equalM) {
            return sprintf($this->monthFormat, $bm) . $by;
        }

        if ($equalY) {
            if ($bm && $em) {
                return '<small class="month">' . $this->monthsRange($bm, $em) . '.</small>' . $by;
            }

            return $by;
        }

        if ($equalS) {
            return (($bm ? sprintf($this->monthFormat, $bm) : '') . $by) .
                   '–'.
                   ($em ? sprintf($this->monthFormat, $em) : '') . ($em ? $ey : sprintf('%02d', $ey % 100));
        }



        return  (($bm ? sprintf($this->monthFormat, $bm) : '') . ($by ? $by : '????')) .
                (
                    $ey
                        ? '–' . ($em ? sprintf($this->monthFormat, $em) : '') . $ey
                        : $this->renderer->escapeHtml(
                            $this->missedEndYearYearsSuffix($today, $by, $language)
                        )
                );
    }
}
