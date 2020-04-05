<?php

namespace Application\View\Helper;

use Application\FileSize as AppFileSize;
use Laminas\View\Helper\AbstractHelper;

class FileSize extends AbstractHelper
{
    private AppFileSize $filesize;

    public function __construct(AppFileSize $filesize)
    {
        $this->filesize = $filesize;
    }

    /**
     * Formats filesize with specified precision
     */
    public function __invoke(int $fileSize, int $precision = 0): string
    {
        return $this->filesize->__invoke($fileSize, $precision);
    }
}
