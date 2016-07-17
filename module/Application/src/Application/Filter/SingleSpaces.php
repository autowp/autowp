<?php

namespace Application\Filter;

use Zend\Filter\AbstractFilter;

class SingleSpaces extends AbstractFilter
{
    public function filter($value)
    {
        if (strlen($value) <= 0)
            return '';

        $value = str_replace("\r", "", $value);
        $lines = explode("\n", $value);
        foreach ($lines as &$line) {
            $line = preg_replace('/[[:space:]]+/s', ' ', $line);
        }
        return implode("\n", $lines);
    }
}