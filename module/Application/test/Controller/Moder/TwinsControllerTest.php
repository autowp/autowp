<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\TwinsController;

class TwinsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/twins', 'GET');

        $this->assertResponseStatusCode(404);
        $this->assertModuleName('application');
        $this->assertControllerName(TwinsController::class);
        $this->assertMatchedRouteName('moder/twins/params');
        $this->assertActionName('not-found');
    }
}
