<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Application\Language as AppLanguage;
use Application\FileSize as AppFileSize;

class FileSize extends AbstractHelper
{
    /**
     * @var AppFileSize
     */
    private $filesize;

    /**
     * @var AppLanguage
     */
    private $language;

    public function __construct(AppLanguage $language, AppFileSize $filesize)
    {
        $this->language = $language;
        $this->filesize = $filesize;
    }

    /**
     * Formats filesize with specified precision
     *
     * @param integer $fileSize Filesize in bytes
     * @param integer $precision Precision
     * @return string
     */
    public function __invoke($fileSize, $precision = 0)
    {
        return $this->filesize->__invoke($fileSize, $precision);
    }
}
