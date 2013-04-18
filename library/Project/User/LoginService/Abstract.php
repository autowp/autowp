<?php

abstract class Project_User_LoginService_Abstract
{
    /**
     * @var array
     */
    protected $_options;

    /**
     * @param array $options
     * @return string
     */
    abstract public function getLoginUrl(array $options);

    /**
     * @param array $params
     */
    abstract public function callback(array $params);

    public function __construct(array $options)
    {
        $this->_options = $options;
    }
}