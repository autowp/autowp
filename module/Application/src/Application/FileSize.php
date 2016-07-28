<?php

namespace Application;

use Zend_Locale_Format;
use Zend_Measure_Binary;

class FileSize
{
    /**
     * Array of units available
     *
     * @var array
     */
    private $units;

    public function __construct()
    {
        $measure = new Zend_Measure_Binary(0);
        $this->units = $measure->getConversionList();
    }

    /**
     * Formats filesize with specified precision
     *
     * @param string $locale
     * @param integer $fileSize Filesize in bytes
     * @param integer $precision Precision
     * @param string $norm Which norm use - 'traditional' (1 KB = 2^10 B), 'si' (1 KB = 10^3 B), 'iec' (1 KiB = 2^10 B)
     * @param string $type Defined export type
     */
    public function __invoke($locale, $fileSize, $precision = 0, $norm = 'traditional', $type = null)
    {
        //get localised input value
        $fileSize = Zend_Locale_Format::getFloat((int)$fileSize, ['locale' => $locale]);

        $measure = new Zend_Measure_Binary($fileSize);

        $measure->setType('BYTE');

        if (null === $norm) {
            $norm = 'traditional';
        }

        if (isset($type)) {
            $measure->setType($type);
        } elseif ($norm === 'traditional') {
            if ($fileSize >= $this->getUnitSize('TERABYTE')) {
                $measure->setType(Zend_Measure_Binary::TERABYTE);
            } else if ($fileSize >= $this->getUnitSize('GIGABYTE')) {
                $measure->setType(Zend_Measure_Binary::GIGABYTE);
            } else if ($fileSize >= $this->getUnitSize('MEGABYTE')) {
                $measure->setType(Zend_Measure_Binary::MEGABYTE);
            } else if ($fileSize >= $this->getUnitSize('KILOBYTE')) {
                $measure->setType(Zend_Measure_Binary::KILOBYTE);
            }
        } elseif ($norm === 'si') {
            if ($fileSize >= $this->getUnitSize('TERABYTE_SI')) {
                $measure->setType(Zend_Measure_Binary::TERABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('GIGABYTE_SI')) {
                $measure->setType(Zend_Measure_Binary::GIGABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('MEGABYTE_SI')) {
                $measure->setType(Zend_Measure_Binary::MEGABYTE_SI);
            } else if ($fileSize >= $this->getUnitSize('KILOBYTE_SI')) {
                $measure->setType(Zend_Measure_Binary::KILOBYTE_SI);
            }
        }  elseif ($norm === 'iec') {
            if ($fileSize >= $this->getUnitSize('TEBIBYTE')) {
                $measure->setType(Zend_Measure_Binary::TEBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('GIBIBYTE')) {
                $measure->setType(Zend_Measure_Binary::GIBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('MEBIBYTE')) {
                $measure->setType(Zend_Measure_Binary::MEBIBYTE);
            } else if ($fileSize >= $this->getUnitSize('KIBIBYTE')) {
                $measure->setType(Zend_Measure_Binary::KIBIBYTE);
            }
        }

        return $measure->toString($precision);
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