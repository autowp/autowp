<?php

class Project_User_LoginService_Factory
{
    /**
     * @var array
     */
    protected $_options;

    public function __construct(array $options)
    {
        $this->_options = $options;
    }

    /**
     * @param string $service
     * @return Project_User_LoginService_Abstract
     * @throws Exception
     */
    public function getService($service)
    {
        $service = trim($service);

        if (!isset($this->_options[$service])) {
            throw new Exception("Service '$service' not found");
        }

        $className = 'Project_User_LoginService_' . $service;

        $serviceObj = new $className($this->_options[$service]);

        if (!$serviceObj instanceof Project_User_LoginService_Abstract) {
            throw new Exception("'$className' is not Project_User_LoginService_Abstract ");
        }

        return $serviceObj;
    }
}