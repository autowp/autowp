<?php

namespace ApplicationTest\Controller\Moder;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\Moder\AttrsController;

class AttrsControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/moder/attrs', 'GET');

        $this->assertResponseStatusCode(403);
        $this->assertModuleName('application');
        $this->assertControllerName(AttrsController::class);
        $this->assertMatchedRouteName('moder/attrs');
        $this->assertActionName('forbidden');
    }
}
