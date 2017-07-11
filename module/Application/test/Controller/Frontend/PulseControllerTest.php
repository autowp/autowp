<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\PulseController;

class PulseControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/pulse', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PulseController::class);
        $this->assertMatchedRouteName('pulse');
        $this->assertActionName('index');

        $this->assertQuery("h1");
    }
}
