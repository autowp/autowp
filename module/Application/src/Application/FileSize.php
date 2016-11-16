<?php

namespace Application;

class FileSize
{
    /**
     * @param integer $fileSize Filesize in bytes
     * @param integer $precision Precision
     */
    public function __invoke($bytes, $precision = 0)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
