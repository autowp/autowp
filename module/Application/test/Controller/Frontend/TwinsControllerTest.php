<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\TwinsController;

class TwinsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('http://www.autowp.ru/twins', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(TwinsController::class);
        $this->assertMatchedRouteName('twins');
    }
}
