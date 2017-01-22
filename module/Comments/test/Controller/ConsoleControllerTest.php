<?php

namespace AutowpTest\Comments\Controller;

use Autowp\Comments\Controller\ConsoleController;
use Application\Test\AbstractConsoleControllerTestCase;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../_files/application.config.php';

    public function testRefreshRepliesCount()
    {
        $this->dispatch('comments refresh-replies-count');

        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('comments');
        $this->assertActionName('refresh-replies-count');
        $this->assertConsoleOutputContains('ok');
    }
}
