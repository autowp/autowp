<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;

class EnvTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    public function testDbTimezone()
    {
        $db = $this->getApplication()->getServiceManager()->get(\Zend\Db\Adapter\AdapterInterface::class);

        $timezone = $db->query('select @@session.time_zone as timezone', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $timezone = $timezone->current();

        $this->assertEquals('UTC', $timezone['timezone']);
    }
}
