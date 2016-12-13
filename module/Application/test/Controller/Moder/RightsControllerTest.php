<?php

namespace ApplicationTest\Controller\Moder;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\RightsController;

class RightsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/rights', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(RightsController::class);
        $this->assertMatchedRouteName('moder/rights');
        $this->assertActionName('forbidden');
    }
}
