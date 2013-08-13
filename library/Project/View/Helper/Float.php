<?php
/** Zend_View_Helper_Abstract.php */
require_once 'Zend/View/Helper/Abstract.php';

/**
 * @package    My_View
 * @subpackage Helper
 */
class Project_View_Helper_Float extends Zend_View_Helper_Abstract
{
    private $_options = array();

    /**
     * Set default formating options
     *
     * @param array $options  Options: locale, precision.
     *
     * @return void
     */
    public static function setDefaultOptions(array $options)
    {
        $this->_options = $options;
    }

    /**
     * Returns a locale formatted float number
     *
     * @access public
     *
     * @param  float $value    Number to normalize
     * @param  array $options  Options: locale, precision.
     * @return  string  Locale formatted number
     */
    public function float($value, array $options = array())
    {
        $options = array_merge($this->_options, $options);
        if (!isset($options['locale'])) {
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                $options['locale'] = Zend_Registry::get('Zend_Locale');
            }
        }

        require_once 'Zend/Locale/Format.php';
        return $this->view->escape(
            Zend_Locale_Format::toFloat($value, $options)
        );
    }
}
