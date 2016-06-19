<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Language;

use Zend_Locale_Format;
use Zend_Measure_Binary;

class FileSize extends AbstractHelper
{
    /**
     * Array of units available
     *
     * @var array
     */
    private $units;

    /**
     * @var Language
     */
    private $language;

    public function __construct(Language $language)
    {
        $this->language = $language;

        $m = new Zend_Measure_Binary(0);
        $this->units = $units = $m->getConversionList();
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

        //get localised input value
        $fileSize = Zend_Locale_Format::getFloat((int)$fileSize, ['locale' => $language]);

        $m = new Zend_Measure_Binary($fileSize);

        $m->setType('BYTE');

        if (null === $norm) {
            $norm = 'traditional';
        }

        if (isset($type)) {
            $m->setType($type);
        } elseif ($norm === 'traditional') {
            if ($fileSize >= $this->getUnitSize('TERABYTE')) {
                $m->setType(Zend_Measure_Binary::TERABYTE);
            } else if ($fileSize >= $this->getUnitSize('GIGABYTE')) {
                $m->setType(Zend_Measure_Binary::GIGABYTE);
            } else if ($fileSize >= $this->getUnitSize('MEGABYTE')) {
                $m->setType(Zend_Measure_Binary::MEGABYTE);
            } else if ($fileSize >= $this->getUnitSize('KILOBYTE')) {
                $m->setType(Zend_Measure_Binary::KILOBYTE);
            }
        } elseif ($norm === 'si') {
            if ($fileSize >= $this->getUnitSize('TERABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::TERABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('GIGABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::GIGABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('MEGABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::MEGABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('KILOBYTE_SI')) {
                $m->setType(Zend_Measure_Binary::KILOBYTE_SI);
            }
        }  elseif ($norm === 'iec') {
            if ($fileSize >= $this->getUnitSize('TEBIBYTE')) {
                $m->setType(Zend_Measure_Binary::TEBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('GIBIBYTE')) {
                $m->setType(Zend_Measure_Binary::GIBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('MEBIBYTE')) {
                $m->setType(Zend_Measure_Binary::MEBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('KIBIBYTE')) {
                $m->setType(Zend_Measure_Binary::KIBIBYTE);
            }
        }

        return $m->toString($precision);
    }

    /**
     * Get size of $unit in bytes
     *
     * @param string $unit
     */
    private function getUnitSize($unit)
    {
        if (array_key_exists($unit, $this->units)) {
            return $this->units[$unit][0];
        }
        return 0;
    }
}