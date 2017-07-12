<?php

namespace Application\Test;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase as ZendTestCase;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 *
 * @author dmitry
 *
 */
abstract class AbstractHttpControllerTestCase extends ZendTestCase
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
