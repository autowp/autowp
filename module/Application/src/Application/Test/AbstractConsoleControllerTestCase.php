<?php

namespace Application\Test;

use Zend\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase as ZendTestCase;

abstract class AbstractConsoleControllerTestCase extends ZendTestCase
{
    protected $applicationConfigPath;

    protected function setUp()
    {
        if (! $this->applicationConfigPath) {
            throw new \Exception("Application config path not provided");
        }

        $this->setApplicationConfig(include $this->applicationConfigPath);

        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $container = $this->getApplicationServiceLocator();

        $zdb1 = $container->get(\Zend_Db_Adapter_Abstract::class);
        $zdb1->closeConnection();

        $db = $container->get(\Zend\Db\Adapter\AdapterInterface::class);
        $db->driver->getConnection()->disconnect();
    }
}
