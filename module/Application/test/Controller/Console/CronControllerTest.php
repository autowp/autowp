<?php

namespace ApplicationTest\Controller\Console;

use Autowp\Cron\CronController;

use Application\Test\AbstractConsoleControllerTestCase;

class CronControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testDailyMaintenance()
    {
        $this->dispatch('cron daily-maintenance');

        $this->assertModuleName('autowp');
        $this->assertControllerName(CronController::class);
        $this->assertMatchedRouteName('cron');
        $this->assertActionName('daily-maintenance');
        //$this->assertConsoleOutputContains('Garabage collected');
    }

    public function testMidnight()
    {
        $this->dispatch('cron midnight');

        $this->assertModuleName('autowp');
        $this->assertControllerName(CronController::class);
        $this->assertMatchedRouteName('cron');
        $this->assertActionName('midnight');
        //$this->assertConsoleOutputContains('Garabage collected');
    }
}
