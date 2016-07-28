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
     * @param string $norm Which norm use - 'traditional' (1 KB = 2^10 B), 'si' (1 KB = 10^3 B), 'iec' (1 KiB = 2^10 B)
     * @param string $type Defined export type
     */
    public function __invoke($fileSize, $precision = 0, $norm = 'traditional', $type = null)
    {
        $language = $this->language->getLanguage();

        return $this->filesize->__invoke($language, $fileSize, $precision, $norm, $type);
    }
}