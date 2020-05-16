<?php

namespace Application\Controller\Plugin;

use Application\FileSize as AppFileSize;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class FileSize extends AbstractPlugin
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
