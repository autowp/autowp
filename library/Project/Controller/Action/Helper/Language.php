<?php

class Project_Controller_Action_Helper_Language extends Zend_Controller_Action_Helper_Abstract
{
    protected $_language = null;

    public function direct()
    {
        if (!$this->_language) {
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                $locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
                $this->_language = $locale->getLanguage();
            }
        }

        if (!$this->_language) {
            $this->_language = 'en';
        }

        return $this->_language;
    }
}