<?php

class Project_Application_Resource_Acl
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var int
     */
    protected $_cacheLifetime = 3600;

    /**
     * @var Zend_Cache_Core
     */
    protected $_cache = null;

    /**
     * @var Project_Acl
     */
    protected $_acl = null;

    public function init()
    {
        return $this->getAcl();
    }

    public function getAcl()
    {
        if (!$this->_acl) {

            if (!$this->_cache instanceof Zend_Cache_Core) {
                throw new Exception('Cache not initialized');
            }

            $this->_acl = $this->_cache->load(__CLASS__);
            if (!$this->_acl instanceof Project_Acl) {
                $this->_acl = new Project_Acl();
                $this->_cache->save($this->_acl, null, array(), $this->_cacheLifetime);
            }
        }

        return $this->_acl;
    }

    /**
     * Set the cache
     *
     * @param string|Zend_Cache_Core $cache
     * @return Project_Application_Resource_Acl
     */
    public function setCache($cache)
    {
        $cacheCore = null;

        if (is_string($cache)) {
            $bootstrap = $this->getBootstrap();
            if ($bootstrap instanceof Zend_Application_Bootstrap_ResourceBootstrapper
                && $bootstrap->hasPluginResource('CacheManager')
            ) {
                $cacheManager = $bootstrap->bootstrap('CacheManager')
                ->getResource('CacheManager');
                if (null !== $cacheManager && $cacheManager->hasCache($cache)) {
                    $cacheCore = $cacheManager->getCache($cache);
                }
            }
        } else if ($cache instanceof Zend_Cache_Core) {
            $cacheCore = $cache;
        }

        if ($cacheCore instanceof Zend_Cache_Core) {
            $this->_cache = $cacheCore;
        }

        return $this;
    }
}