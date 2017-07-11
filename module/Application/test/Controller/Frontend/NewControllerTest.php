<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\NewController;

class NewControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/new', 'GET');

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(NewController::class);
        $this->assertMatchedRouteName('new');
        $this->assertActionName('index');
    }
}
