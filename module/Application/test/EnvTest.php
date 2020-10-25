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
        $db = $this->getApplication()->getServiceManager()->get(AdapterInterface::class);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $timezone = $db->query('select @@session.time_zone as timezone', Adapter::QUERY_MODE_EXECUTE);
        $timezone = $timezone->current();

        $this->assertEquals('UTC', $timezone['timezone']);
    }
}
