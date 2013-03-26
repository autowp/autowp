<?php

class Project_Controller_Action_Helper_Acl extends Zend_Controller_Action_Helper_Abstract
{
	const CACHE_LIFETIME = 3600;
	const CACHE_KEY = 'ACL_CACHE';
	
	protected $_acl = null;
	protected $_cache = null;
	
	/**
	 * @return void
	 */
	public function init()
    {
		$bootstrap = $this->getActionController()->getInvokeArg('bootstrap');
		$this->_cache = $bootstrap->getResource('cachemanager')->getCache('long');
	}

	/**
	 * @return Project_Acl
	 */
	public function direct()
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
