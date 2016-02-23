<?php

use Autowp\ExternalLoginService\Factory;

class Project_Application_Resource_Externalloginservice
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var Factory
     */
    protected $_factory = null;

    /**
     * @return Factory
     */
    public function init()
    {
        return $this->getFactory();
    }

    /**
     * @return Factory
     */
    public function getFactory()
    {
        if (null === $this->_factory) {
            $this->_factory = new Factory($this->getOptions());
        }
        return $this->_factory;
    }
}