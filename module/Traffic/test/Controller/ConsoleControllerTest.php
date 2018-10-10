<?php

namespace AutowpTest\Traffic\Controller;

use Zend\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase;

use Autowp\Traffic\Controller\ConsoleController;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../../../../config/application.config.php');

        parent::setUp();
    }

    public function testAutoban()
    {
        $this->dispatch('traffic autoban');

        $this->assertResponseStatusCode(0);
        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('traffic');
        $this->assertActionName('autoban');
        $this->assertConsoleOutputContains("done");
    }
}
