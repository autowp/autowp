<?php

use Autowp\TextStorage\Service;

class Project_Application_Resource_Textstorage
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var Service
     */
    protected $_textStorage = null;

    /**
     * @return Service
     */
    public function init()
    {
        return $this->getTextStorage();
    }

    /**
     * @return Service
     */
    public function getTextStorage()
    {
        if (null === $this->_textStorage) {
            $options = $this->getOptions();
            foreach($options as $key => $option) {
                $options[strtolower($key)] = $option;
            }

            $bootstrap = $this->getBootstrap();
            if ($bootstrap instanceof Zend_Application_Bootstrap_ResourceBootstrapper
                && $bootstrap->hasPluginResource('Db')
            ) {
                $db = $bootstrap->bootstrap('Db')
                    ->getResource('Db');
                if (null !== $db) {
                    $options['dbAdapter'] = $db;
                }
            }

            $this->_textStorage = new Service($options);
        }
        return $this->_textStorage;
    }
}