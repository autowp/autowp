<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Console\SpecsController;
use Application\Test\AbstractConsoleControllerTestCase;

class SpecsControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testRefreshConflictFlags()
    {
        $this->dispatch('specs refresh-conflict-flags');

        $this->assertModuleName('application');
        $this->assertControllerName(SpecsController::class);
        $this->assertMatchedRouteName('specs');
        $this->assertActionName('refresh-conflict-flags');
        $this->assertConsoleOutputContains('done');
    }

    public function testRefreshItemConflictFlags()
    {
        $this->dispatch('specs refresh-item-conflict-flags 1');

        $this->assertModuleName('application');
        $this->assertControllerName(SpecsController::class);
        $this->assertMatchedRouteName('specs-refresh-item-conflict-flags');
        $this->assertActionName('refresh-item-conflict-flags');
        $this->assertConsoleOutputContains('done');
    }

    public function testRefreshUserStat()
    {
        $this->dispatch('specs refresh-user-stat 1');

        $this->assertModuleName('application');
        $this->assertControllerName(SpecsController::class);
        $this->assertMatchedRouteName('specs-refresh-user-stat');
        $this->assertActionName('refresh-user-stat');
        $this->assertConsoleOutputContains('done');
    }

    public function testRefreshUsersStat()
    {
        $this->dispatch('specs refresh-users-stat');

        $this->assertModuleName('application');
        $this->assertControllerName(SpecsController::class);
        $this->assertMatchedRouteName('specs');
        $this->assertActionName('refresh-users-stat');
        $this->assertConsoleOutputContains('done');
    }

    public function testUpdateSpecsVolumes()
    {
        $this->dispatch('specs update-specs-volumes');

        $this->assertModuleName('application');
        $this->assertControllerName(SpecsController::class);
        $this->assertMatchedRouteName('specs');
        $this->assertActionName('update-specs-volumes');
        $this->assertConsoleOutputContains('done');
    }
}
