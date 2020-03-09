<?php

namespace Application;

use function array_replace;
use function array_slice;
use function explode;
use function implode;
use function mb_strlen;
use function mb_substr;
use function str_replace;
use function trim;

class StringUtils
{
    public static function getTextPreview($text, array $options)
    {
        $defaults = [
            'maxlength' => null,
            'maxlines'  => null,
        ];
        $options  = array_replace($defaults, $options);

        $maxlength = (int) $options['maxlength'];
        $maxlines  = (int) $options['maxlines'];

        $text = trim($text);
        $text = str_replace("\r", '', $text);

        if ($maxlines) {
            $lines = explode("\n", $text);
            $lines = array_slice($lines, 0, $maxlines);

            $text = implode("\n", $lines);
        }

        if ($maxlength && mb_strlen($text) > $maxlength) {
            $text = mb_substr($text, 0, $maxlength) . '...';
        }

        return $text;
    }
}
