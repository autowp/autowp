<?php

namespace Application\Controller\Plugin;

use Application\FileSize as AppFileSize;
use Application\Language;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class FileSize extends AbstractPlugin
{
    private AppFileSize $filesize;

    private Language $language;

    public function __construct(Language $language, AppFileSize $filesize)
    {
        $this->language = $language;
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
