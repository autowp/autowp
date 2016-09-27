<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Language;
use Application\FileSize as AppFileSize;

class FileSize extends AbstractPlugin
{
    /**
     * @var AppFileSize
     */
    private $filesize;

    /**
     * @var Language
     */
    private $language;

    public function __construct(Language $language, AppFileSize $filesize)
    {
        $this->language = $language;
        $this->filesize = $filesize;
    }

    /**
     * Formats filesize with specified precision
     *
     * @param integer $fileSize Filesize in bytes
     * @param integer $precision Precision
     */
    public function __invoke($fileSize, $precision = 0)
    {
        return $this->filesize->__invoke($fileSize, $precision);
    }
}