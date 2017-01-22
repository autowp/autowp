<?php

namespace AutowpTest\Message\Controller;

use Application\Controller\Console\MessageController;
use Application\Test\AbstractConsoleControllerTestCase;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testClearOldSystemPM()
    {
        $this->dispatch('message clear-old-system-pm');

        $this->assertModuleName('application');
        $this->assertControllerName(MessageController::class);
        $this->assertMatchedRouteName('message');
        $this->assertActionName('clear-old-system-pm');
        $this->assertConsoleOutputContains('messages was deleted');
    }

    public function testClearDeletedPM()
    {
        $this->dispatch('message clear-deleted-pm');

        $this->assertModuleName('application');
        $this->assertControllerName(MessageController::class);
        $this->assertMatchedRouteName('message');
        $this->assertActionName('clear-deleted-pm');
        $this->assertConsoleOutputContains('messages was deleted');
    }
}
