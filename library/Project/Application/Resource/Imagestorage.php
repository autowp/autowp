<?php

use Autowp\Image\Storage;

class Project_Application_Resource_Imagestorage
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var Storage
     */
    private $_imageStorage = null;

    /**
     * @return Storage
     */
    public function init()
    {
        return $this->getImageStorage();
    }

    /**
     * @return Storage
     */
    public function getImageStorage()
    {
        if (null === $this->_imageStorage) {
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

            $this->_imageStorage = new Storage($options);
        }
        return $this->_imageStorage;
    }
}