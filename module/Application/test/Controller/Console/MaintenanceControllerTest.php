<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Console\MaintenanceController;
use Application\Test\AbstractConsoleControllerTestCase;

class MaintenanceControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testClearSessions()
    {
        $this->dispatch('maintenance clear-sessions');

        $this->assertModuleName('application');
        $this->assertControllerName(MaintenanceController::class);
        $this->assertMatchedRouteName('maintenance');
        $this->assertActionName('clear-sessions');
        $this->assertConsoleOutputContains('Garabage collected');
    }

    public function testDump()
    {
        $this->dispatch('maintenance dump');

        $this->assertModuleName('application');
        $this->assertControllerName(MaintenanceController::class);
        $this->assertMatchedRouteName('maintenance');
        $this->assertActionName('dump');
        $this->assertConsoleOutputContains('ok');
    }
}
