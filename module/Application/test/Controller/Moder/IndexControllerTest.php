<?php

namespace ApplicationTest\Controller\Moder;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\IndexController;

class IndexControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(IndexController::class);
        $this->assertMatchedRouteName('moder');
        $this->assertActionName('forbidden');
    }
}
