<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\DonateController;

class DonateControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/donate', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(DonateController::class);
        $this->assertMatchedRouteName('donate');
        $this->assertActionName('index');

        $this->assertQuery("h1");
    }
}
