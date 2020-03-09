<?php

namespace Application\Test;

use Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase as LaminasTestCase;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractHttpControllerTestCase extends LaminasTestCase
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
