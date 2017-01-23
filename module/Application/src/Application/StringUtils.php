<?php

namespace Application;

class StringUtils
{
    public static function getTextPreview($text, array $options)
    {
        $defaults = [
            'maxlength' => null,
            'maxlines'  => null
        ];
        $options = array_replace($defaults, $options);

        $maxlength = (int)$options['maxlength'];
        $maxlines = (int)$options['maxlines'];

        $text = trim($text);
        $text = str_replace("\r", '', $text);

        if ($maxlines) {
            $lines = explode("\n", $text);
            $lines = array_slice($lines , 0, $maxlines);

            $text = implode("\n", $lines);
        }

        if ($maxlength) {
            if (mb_strlen($text) > $maxlength) {
                $text = mb_substr($text, 0, $maxlength) . '...';
            }
        }

        return $text;
    }
}