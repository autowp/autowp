<?php
/**
 * Zym
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 *
 * @author     Martin Hujer
 * @category   Zym
 * @package    Zym_View
 * @subpackage Helper
 * @copyright  Copyright (c) 2008 Zym. (http://www.zym-project.com/)
 * @license    http://www.zym-project.com/license    New BSD License
 */

/**
 * @see Zend_Locale_Math
 */
require_once 'Zend/Locale/Math.php';

/**
 * @see Zend_Measure_Binary
 */
require_once 'Zend/Measure/Binary.php';

/**
 * @author     Martin Hujer
 * @category   Zym
 * @package    Zym_View
 * @subpackage Helper
 * @copyright  Copyright (c) 2008 Zym. (http://www.zym-project.com/)
 * @license    http://www.zym-project.com/license    New BSD License
 */
class Project_View_Helper_FileSize
{
    /**
     * Array of units available
     * 
     * @var array
     */
    protected $_units;

    /**
     * Construct
     *
     */
    public function __construct()
    {
        $m = new Zend_Measure_Binary(0);
        $this->_units = $units = $m->getConversionList();
    }

    /**
     * Formats filesize with specified precision
     *
     * @param integer $fileSize Filesize in bytes
     * @param integer $precision Precision
     * @param string $norm Which norm use - 'traditional' (1 KB = 2^10 B), 'si' (1 KB = 10^3 B), 'iec' (1 KiB = 2^10 B)
     * @param string $type Defined export type
     */
    public function fileSize($fileSize, $precision = 0, $norm = 'traditional', $type = null)
    {

        try {
            $locale = Zend_Registry::get('Zend_Locale');
        } catch (Zend_Exception $e) {
            require_once 'Zend/View/Exception.php';
            throw new Zend_View_Exception('General Zend_Locale object is not set.');
        }
        if (!$locale instanceof Zend_Locale) {
            require_once 'Zend/Exception.php';
            throw new Zend_View_Exception('Locale is not set corretly.');
        }
        
        //get localised input value 
        $fileSize = Zend_Locale_Format::getFloat((int)$fileSize, array('locale' => $locale));

        $m = new Zend_Measure_Binary($fileSize);
        
        $m->setType('BYTE');
        
        if (null === $norm) {
            $norm = 'traditional';
        }
        
        if (isset($type)) {
            $m->setType($type);
        } elseif ($norm === 'traditional') {
            if ($fileSize >= $this->_getUnitSize('TERABYTE')) {
                $m->setType(Zend_Measure_Binary::TERABYTE);
            } else if ($fileSize >= $this->_getUnitSize('GIGABYTE')) {
                $m->setType(Zend_Measure_Binary::GIGABYTE);
            } else if ($fileSize >= $this->_getUnitSize('MEGABYTE')) {
                $m->setType(Zend_Measure_Binary::MEGABYTE);
            } else if ($fileSize >= $this->_getUnitSize('KILOBYTE')) {
                $m->setType(Zend_Measure_Binary::KILOBYTE);
            }
        } elseif ($norm === 'si') {
            if ($fileSize >= $this->_getUnitSize('TERABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::TERABYTE_SI);
            } else if ($fileSize >= $this->_getUnitSize('GIGABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::GIGABYTE_SI);
            } else if ($fileSize >= $this->_getUnitSize('MEGABYTE_SI')) {
                $m->setType(Zend_Measure_Binary::MEGABYTE_SI);
            } else if ($fileSize >= $this->_getUnitSize('KILOBYTE_SI')) {
                $m->setType(Zend_Measure_Binary::KILOBYTE_SI);
            }
        }  elseif ($norm === 'iec') {
            if ($fileSize >= $this->_getUnitSize('TEBIBYTE')) {
                $m->setType(Zend_Measure_Binary::TEBIBYTE);
            } else if ($fileSize >= $this->_getUnitSize('GIBIBYTE')) {
                $m->setType(Zend_Measure_Binary::GIBIBYTE);
            } else if ($fileSize >= $this->_getUnitSize('MEBIBYTE')) {
                $m->setType(Zend_Measure_Binary::MEBIBYTE);
            } else if ($fileSize >= $this->_getUnitSize('KIBIBYTE')) {
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
    protected function _getUnitSize($unit)
    {
        if (array_key_exists($unit, $this->_units)) {
            return $this->_units[$unit][0];
        }
        return 0;
    }
}