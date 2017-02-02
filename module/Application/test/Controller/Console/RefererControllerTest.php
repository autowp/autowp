<?php

namespace ApplicationTest\Controller\Console;

use Application\Controller\Console\RefererController;
use Application\Test\AbstractConsoleControllerTestCase;

class RefererControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testClearRefererMonitoring()
    {
        $this->dispatch('traffic clear-referer-monitoring');

        $this->assertModuleName('application');
        $this->assertControllerName(RefererController::class);
        $this->assertMatchedRouteName('referer');
        $this->assertActionName('clear-referer-monitoring');
        $this->assertConsoleOutputContains('referer monitoring rows was deleted');
    }
}
