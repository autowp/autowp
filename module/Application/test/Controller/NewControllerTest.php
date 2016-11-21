<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\NewController;

class NewControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

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
