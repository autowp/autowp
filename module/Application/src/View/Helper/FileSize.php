<?php

namespace Application\View\Helper;

use Application\FileSize as AppFileSize;
use Application\Language as AppLanguage;
use Laminas\View\Helper\AbstractHelper;

class FileSize extends AbstractHelper
{
    private AppFileSize $filesize;

    private AppLanguage $language;

    public function __construct(AppLanguage $language, AppFileSize $filesize)
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
