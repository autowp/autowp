<?php

namespace ApplicationTest\Controller\Api;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\PulseController;

class PulseControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/pulse', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PulseController::class);
        $this->assertMatchedRouteName('api/pulse');
        $this->assertActionName('index');
    }
}
