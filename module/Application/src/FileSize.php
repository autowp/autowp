<?php

namespace Application;

use function count;
use function floor;
use function log;
use function max;
use function min;
use function pow;
use function round;

class FileSize
{
    public function __invoke(int $bytes, int $precision = 0): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = (int) min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
