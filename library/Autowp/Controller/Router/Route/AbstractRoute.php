<?php

namespace Autowp\Controller\Router\Route;

use Zend_Config;
use Zend_Controller_Router_Route_Interface;

abstract class AbstractRoute
    implements Zend_Controller_Router_Route_Interface
{
    const DELIMETER = '/';

    protected $_variables = array();

    protected $_defaults = array();

    abstract public function match($path);

    abstract public function assemble($data = array(), $reset = false, $encode = false);

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        return new self();
    }
}
