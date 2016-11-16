<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\Controller\InboxController;

class InboxControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/inbox', 'GET');

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(InboxController::class);
        $this->assertMatchedRouteName('inbox');
        $this->assertActionName('index');
    }
}
