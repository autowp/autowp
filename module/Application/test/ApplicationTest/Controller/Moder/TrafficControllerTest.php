<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\TrafficController;

class TrafficControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/traffic', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(TrafficController::class);
        $this->assertMatchedRouteName('moder/traffic');
        $this->assertActionName('forbidden');
    }
}
