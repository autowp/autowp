<?php

class Project_View_Helper_Acl extends Zend_View_Helper_Abstract
{
	const CACHE_LIFETIME = 3600;
	const CACHE_KEY = 'ACL_CACHE';
	
	private $_acl = null;
	private $_cache = null;
	
	public function __construct()
	{
		$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$this->_cache = $bootstrap->getResource('cachemanager')->getCache('long');
	}

	/**
	 * @return Project_Acl
	 */
	public function acl()
	{
		if (!$this->_acl) {
			$this->_acl = $this->_cache->load(self::CACHE_KEY);
			if (!$this->_acl) {
				$this->_acl = new Project_Acl();
				$this->_cache->save($this->_acl, null, array(), self::CACHE_LIFETIME);
			}
		}
			
		return $this->_acl;
	}
}
