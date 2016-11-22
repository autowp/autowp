<?php

namespace AutowpTest\User\Controller;

use Zend\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase;

use Autowp\User\Controller\ConsoleController;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    public function testClearPasswordRemind()
    {
        $this->dispatch('users clear-password-remind');

        $this->assertResponseStatusCode(0);
        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('users');
        $this->assertActionName('clear-password-remind');
        $this->assertConsoleOutputContains("done");
    }

    public function testClearRemember()
    {
        $this->dispatch('users clear-remember');

        $this->assertResponseStatusCode(0);
        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('users');
        $this->assertActionName('clear-remember');
        $this->assertConsoleOutputContains("done");
    }

    public function testClearRenames()
    {
        $this->dispatch('users clear-renames');

        $this->assertResponseStatusCode(0);
        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('users');
        $this->assertActionName('clear-renames');
        $this->assertConsoleOutputContains("done");
    }
}
