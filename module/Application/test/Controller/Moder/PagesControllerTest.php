<?php

namespace ApplicationTest\Controller\Moder;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Moder\PagesController;

class PagesControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/pages', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(PagesController::class);
        $this->assertMatchedRouteName('moder/pages');
        $this->assertActionName('forbidden');
    }
}
