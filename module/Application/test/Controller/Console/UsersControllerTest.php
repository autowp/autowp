<?php

namespace ApplicationTest\Controller\Frontend;

use Application\Controller\Console\UsersController;
use Application\Test\AbstractConsoleControllerTestCase;

class UsersControllerTest extends AbstractConsoleControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../_files/application.config.php';

    public function testDeleteUnused()
    {
        $this->dispatch('users delete-unused');

        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('app-users');
        $this->assertActionName('delete-unused');
        $this->assertConsoleOutputContains('Deleted');
    }

    public function testRefreshVoteLimits()
    {
        $this->dispatch('users refresh-vote-limits');

        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('app-users');
        $this->assertActionName('refresh-vote-limits');
        $this->assertConsoleOutputContains('Updated');
    }

    public function testRestoreVotes()
    {
        $this->dispatch('users restore-votes');

        $this->assertModuleName('application');
        $this->assertControllerName(UsersController::class);
        $this->assertMatchedRouteName('app-users');
        $this->assertActionName('restore-votes');
        $this->assertConsoleOutputContains('User votes restored');
    }
}
