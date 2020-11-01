<?php

namespace ApplicationTest;

use Application\Test\AbstractHttpControllerTestCase;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterInterface;

class EnvTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    public function testDbTimezone(): void
    {
        /** @var Adapter $db */
        $db = $this->getApplication()->getServiceManager()->get(AdapterInterface::class);

        $timezone = $db->query('select @@session.time_zone as timezone', Adapter::QUERY_MODE_EXECUTE);
        $timezone = $timezone->current();

        $this->assertEquals('UTC', $timezone['timezone']);
    }
}
