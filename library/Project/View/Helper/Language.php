<?php

class Project_View_Helper_Language extends Zend_View_Helper_Abstract
{
	protected $_language = null;
	
	public function language()
	{
		return $this;
	}
	
	public function is($language)
	{
		return $this->get() == $language;
	}
	
	public function get()
	{
		if (!$this->_language) {
			if (Zend_Registry::isRegistered('Zend_Locale')) {
				$locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
				$this->_language = $locale->getLanguage();
			}
		}
		
		return $this->_language;
	}
}