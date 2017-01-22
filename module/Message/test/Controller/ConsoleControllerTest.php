<?php

namespace AutowpTest\Message\Controller;

use Autowp\Message\Controller\ConsoleController;
use Application\Test\AbstractConsoleControllerTestCase;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testClearOldSystemPM()
    {
        $this->dispatch('message clear-old-system-pm');

        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('message');
        $this->assertActionName('clear-old-system-pm');
        $this->assertConsoleOutputContains('messages was deleted');
    }

    public function testClearDeletedPM()
    {
        $this->dispatch('message clear-deleted-pm');

        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('message');
        $this->assertActionName('clear-deleted-pm');
        $this->assertConsoleOutputContains('messages was deleted');
    }
}
