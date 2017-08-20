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

        $db = $container->get(\Zend\Db\Adapter\AdapterInterface::class);
        $db->driver->getConnection()->disconnect();
    }
}
