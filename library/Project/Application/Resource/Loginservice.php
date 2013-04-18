<?php

class Project_Application_Resource_Loginservice
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var Project_User_LoginService_Factory
     */
    protected $_factory;

    /**
     * @return Project_User_LoginService_Factory
     */
    public function init()
    {
        return $this->getFactory();
    }

    /**
     * @return Project_User_LoginService_Factory
     */
    public function getFactory()
    {
        if (!$this->_factory) {
            $this->_factory = new Project_User_LoginService_Factory($this->getOptions());
        }
        return $this->_factory;
    }
}