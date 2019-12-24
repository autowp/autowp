<?php

namespace Application\Test;

use Exception;
use Zend\Db\Adapter\AdapterInterface;
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

    protected function setUp(): void
    {
        if (! $this->applicationConfigPath) {
            throw new Exception("Application config path not provided");
        }

        $this->setApplicationConfig(include $this->applicationConfigPath);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $container = $this->getApplicationServiceLocator();

        $db = $container->get(AdapterInterface::class);
        $db->driver->getConnection()->disconnect();
    }
}
