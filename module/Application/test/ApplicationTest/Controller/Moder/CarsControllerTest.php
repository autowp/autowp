<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\CarsController;

class CarsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/cars', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('moder/cars');
        $this->assertActionName('forbidden');
    }
}
