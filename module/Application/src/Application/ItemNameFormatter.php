<?php

namespace Application;

use ArrayAccess;
use Exception;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Renderer\PhpRenderer;

use function array_merge;
use function array_replace;
use function date;
use function implode;
use function is_array;
use function sprintf;
use function strlen;

class ItemNameFormatter
{
    private TranslatorInterface $translator;

    private PhpRenderer $renderer;

    private string $monthFormat = '<small class="month">%02d.</small>';

    private string $textMonthFormat = '%02d.';

    public function __construct(TranslatorInterface $translator, PhpRenderer $renderer)
    {
        $this->translator = $translator;
        $this->renderer   = $renderer;
    }

    private function translate(string $string, string $language)
    {
        return $this->translator->translate($string, 'default', $language);
    }

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    public function formatHtml($item, string $language)
    {
        if (! $item instanceof ArrayAccess && ! is_array($item)) {
            throw new Exception("`item` must be array or ArrayAccess");
        }

        if ($item instanceof ArrayAccess) {
            $item = (array) $item;
        }

        $defaults = [
            'begin_model_year'          => null,
            'end_model_year'            => null,
            'begin_model_year_fraction' => null,
            'end_model_year_fraction'   => null,
            'spec'                      => null,
            'spec_full'                 => null,
            'body'                      => null,
            'name'                      => null,
            'begin_year'                => null,
            'end_year'                  => null,
            'today'                     => null,
            'begin_month'               => null,
            'end_month'                 => null,
        ];
        $item     = array_replace($defaults, $item);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $result = $this->renderer->escapeHtml($item['name']);

        if ($item['spec']) {
            $attrs = ['class="badge badge-info"'];
            if ($item['spec_full']) {
                $attrs = array_merge($attrs, [
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    'title="' . $this->renderer->escapeHtmlAttr($item['spec_full']) . '"',
                    'data-toggle="tooltip"',
                    'data-placement="top"',
                ]);
            }
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $escapedSpec = $this->renderer->escapeHtml($item['spec']);
            $result     .= ' <span ' . implode(' ', $attrs) . '>' . $escapedSpec . '</span>';
        }

        if (strlen($item['body']) > 0) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $result .= ' (' . $this->renderer->escapeHtml($item['body']) . ')';
        }

        $by = (int) $item['begin_year'];
        $bm = (int) $item['begin_month'];
        $ey = (int) $item['end_year'];
        $em = (int) $item['end_month'];

        $bmy = (int) $item['begin_model_year'];
        $emy = (int) $item['end_model_year'];

        $bmyf = $item['begin_model_year_fraction'];
        $emyf = $item['end_model_year_fraction'];

        $bs = (int) ($by / 100);
        $es = (int) ($ey / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs === $es);
        $equalY = $equalS && $by && $ey && ($by === $ey);
        $equalM = $equalY && $bm && $em && ($bm === $em);

        if ($useModelYear) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $title  = $this->renderer->escapeHtmlAttr($this->translate('carlist/model-years', $language));
            $result = '<span title="' . $title . '">'
                          . $this->renderer->escapeHtml( // @phan-suppress-current-line PhanUndeclaredMethod
                              $this->getModelYearsPrefix($bmy, $bmyf, $emy, $emyf, $item['today'], $language)
                          )
                      . '</span> '
                      . $result;

            if ($by > 0 || $ey > 0) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $title   = $this->renderer->escapeHtmlAttr($this->translate('carlist/years', $language));
                $result .=
                    '<small>'
                        . ' \'<span class="realyears" title="' . $title . '">'
                            . $this->renderYearsHtml(
                                $item['today'],
                                $by,
                                $bm,
                                $ey,
                                $em,
                                $equalS,
                                $equalY,
                                $equalM,
                                $language
                            )
                        . '</span>'
                    . '</small>';
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

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    public function format($item, string $language): string
    {
        if (! $item instanceof ArrayAccess && ! is_array($item)) {
            throw new Exception("`item` must be array or ArrayAccess");
        }

        if ($item instanceof ArrayAccess) {
            $item = (array) $item;
        }

        $defaults = [
            'begin_model_year'          => null,
            'end_model_year'            => null,
            'begin_model_year_fraction' => null,
            'end_model_year_fraction'   => null,
            'spec'                      => null,
            'spec_full'                 => null,
            'body'                      => null,
            'name'                      => null,
            'begin_year'                => null,
            'end_year'                  => null,
            'today'                     => null,
            'begin_month'               => null,
            'end_month'                 => null,
        ];
        $item     = array_replace($defaults, $item);

        $result = $item['name'];

        if ($item['spec']) {
            $result .= ' [' . $item['spec'] . ']';
        }

        if (strlen($item['body']) > 0) {
            $result .= ' (' . $item['body'] . ')';
        }

        $by = (int) $item['begin_year'];
        $bm = (int) $item['begin_month'];
        $ey = (int) $item['end_year'];
        $em = (int) $item['end_month'];

        $bmy = (int) $item['begin_model_year'];
        $emy = (int) $item['end_model_year'];

        $bmyf = $item['begin_model_year_fraction'];
        $emyf = $item['end_model_year_fraction'];

        $bs = (int) ($by / 100);
        $es = (int) ($ey / 100);

        $useModelYear = $bmy || $emy;

        $equalS = $bs && $es && ($bs === $es);
        $equalY = $equalS && $by && $ey && ($by === $ey);
        $equalM = $equalY && $bm && $em && ($bm === $em);

        if ($useModelYear) {
            $result = $this->getModelYearsPrefix($bmy, $bmyf, $emy, $emyf, $item['today'], $language) . ' ' . $result;
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

    private function getModelYearsPrefix($begin, $beginFraction, $end, $endFraction, $today, $language)
    {
        $bms = (int) ($begin / 100);
        $ems = (int) ($end / 100);

        if ($end === $begin && $beginFraction === $endFraction) {
            return $begin . $endFraction;
        }

        if ($bms === $ems) {
            return $begin . $beginFraction . '–' . sprintf('%02d', $end % 100) . $endFraction;
        }

        if (! $begin) {
            return '????–' . $end . $endFraction;
        }

        if ($end) {
            return $begin . $beginFraction . '–' . $end . $endFraction;
        }

        if (! $today) {
            return $begin . $beginFraction . '–??';
        }

        $currentYear = (int) date('Y');

        if ($begin >= $currentYear) {
            return $begin . $beginFraction;
        }

        return $begin . $beginFraction . '–' . $this->translate('present-time-abbr', $language);
    }

    private function monthsRange($from, $to)
    {
        return ($from ? sprintf('%02d', $from) : '??')
               . '–'
               . ($to ? sprintf('%02d', $to) : '??');
    }

    private function missedEndYearYearsSuffix($today, $by, $language)
    {
        $cy = (int) date('Y');

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
            return (($bm ? sprintf($this->textMonthFormat, $bm) : '') . $by)
                   . '–'
                   . ($em ? sprintf($this->textMonthFormat, $em) : '') . ($em ? $ey : sprintf('%02d', $ey % 100));
        }

        return (($bm ? sprintf($this->textMonthFormat, $bm) : '') . ($by ? $by : '????'))
                . (
                    $ey
                        ? '–' . ($em ? sprintf($this->textMonthFormat, $em) : '') . $ey
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
            return (($bm ? sprintf($this->monthFormat, $bm) : '') . $by)
                   . '–'
                   . ($em ? sprintf($this->monthFormat, $em) : '') . ($em ? $ey : sprintf('%02d', $ey % 100));
        }

        return (($bm ? sprintf($this->monthFormat, $bm) : '') . ($by ? $by : '????'))
                . (
                    $ey
                        ? '–' . ($em ? sprintf($this->monthFormat, $em) : '') . $ey
                        : $this->renderer->escapeHtml( // @phan-suppress-currenet-line PhanUndeclaredMethod
                            $this->missedEndYearYearsSuffix($today, $by, $language)
                        )
                );
    }
}
