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

    public function formatHtml($item, $language)
    {
        if (! $item instanceof \ArrayAccess && ! is_array($item)) {
            throw new \Exception("`item` must be array or ArrayAccess");
        }

        if ($item instanceof \ArrayAccess) {
            $item = (array)$item;
        }

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
        $item = array_replace($defaults, $item);

        $result = $this->renderer->escapeHtml($item['name']);

        if ($item['spec']) {
            $attrs = ['class="badge badge-info"'];
            if ($item['spec_full']) {
                $attrs = array_merge($attrs, [
                    'title="' . $this->renderer->escapeHtmlAttr($item['spec_full']) . '"',
                    'data-toggle="tooltip"',
                    'data-placement="top"'
                ]);
            }
            $escapedSpec = $this->renderer->escapeHtml($item['spec']);
            $result .= ' <span '.implode(' ', $attrs).'>' . $escapedSpec . '</span>';
        }

        if (strlen($item['body']) > 0) {
            $result .= ' ('.$this->renderer->escapeHtml($item['body']).')';
        }

        $by = (int)$item['begin_year'];
        $bm = (int)$item['begin_month'];
        $ey = (int)$item['end_year'];
        $em = (int)$item['end_month'];

        $bmy = (int)$item['begin_model_year'];
        $emy = (int)$item['end_model_year'];

        $bs = (int)($by / 100);
        $es = (int)($ey / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {
            $title = $this->renderer->escapeHtmlAttr($this->translate('carlist/model-years', $language));
            $result = '<span title="' . $title . '">' .
                          $this->renderer->escapeHtml(
                              $this->getModelYearsPrefix($bmy, $emy, $item['today'], $language)
                          ) .
                      '</span> ' .
                      $result;

            if ($by > 0 || $ey > 0) {
                $title = $this->renderer->escapeHtmlAttr($this->translate('carlist/years', $language));
                $result .=
                    '<small>'.
                        ' \'<span class="realyears" title="'.$title.'">' .
                            $this->renderYearsHtml(
                                $item['today'],
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
                    $item['today'],
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

    public function format($item, $language)
    {
        if (! $item instanceof \ArrayAccess && ! is_array($item)) {
            throw new \Exception("`item` must be array or ArrayAccess");
        }

        if ($item instanceof \ArrayAccess) {
            $item = (array)$item;
        }

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
        $item = array_replace($defaults, $item);

        $result = $item['name'];

        if ($item['spec']) {
            $result .= ' ' . $item['spec'];
        }

        if (strlen($item['body']) > 0) {
            $result .= ' ('.$item['body'].')';
        }

        $by = (int)$item['begin_year'];
        $bm = (int)$item['begin_month'];
        $ey = (int)$item['end_year'];
        $em = (int)$item['end_month'];

        $bmy = (int)$item['begin_model_year'];
        $emy = (int)$item['end_model_year'];

        $bs = (int)($by / 100);
        $es = (int)($ey / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);

        if ($useModelYear) {
            $result = $this->getModelYearsPrefix($bmy, $emy, $item['today'], $language) . ' ' . $result;
        }

        if ($by > 0 || $ey > 0) {
            $result .= " '" . $this->renderYears(
                $item['today'],
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
