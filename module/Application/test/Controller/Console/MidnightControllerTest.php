<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Console\MidnightController;
use Application\Test\AbstractConsoleControllerTestCase;

class MidnightControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testClearRefererMonitoring()
    {
        $this->dispatch('midnight car-of-day');

        $this->assertModuleName('application');
        $this->assertControllerName(MidnightController::class);
        $this->assertMatchedRouteName('midnight');
        $this->assertActionName('car-of-day');
        $this->assertConsoleOutputContains('done');
    }
}
