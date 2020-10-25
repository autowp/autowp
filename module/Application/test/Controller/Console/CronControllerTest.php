<?php

namespace ApplicationTest\Controller\Console;

use Application\Test\AbstractConsoleControllerTestCase;
use Autowp\Cron\CronController;

class CronControllerTest extends AbstractConsoleControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testDailyMaintenance(): void
    {
        $this->dispatch('cron daily-maintenance');

        $this->assertModuleName('autowp');
        $this->assertControllerName(CronController::class);
        $this->assertMatchedRouteName('cron');
        $this->assertActionName('daily-maintenance');
        //$this->assertConsoleOutputContains('Garbage collected');
    }

    public function testMidnight(): void
    {
        $this->dispatch('cron midnight');

        $this->assertModuleName('autowp');
        $this->assertControllerName(CronController::class);
        $this->assertMatchedRouteName('cron');
        $this->assertActionName('midnight');
        //$this->assertConsoleOutputContains('Garbage collected');
    }
}
