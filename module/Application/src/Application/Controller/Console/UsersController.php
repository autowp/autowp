<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\UsersService;

class UsersController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $usersService;

    public function __construct(UsersService $usersService)
    {
        $this->usersService = $usersService;
    }

    public function restoreVotesAction()
    {
        $this->usersService->restoreVotes();

        $console = Console::getInstance();
        $console->writeLine("User votes restored");
    }

    public function refreshVoteLimitsAction()
    {
        $affected = $this->usersService->updateUsersVoteLimits();

        $console = Console::getInstance();
        $console->writeLine(sprintf("Updated %s users\n", $affected));
    }

    public function deleteUnusedAction()
    {
        $console = Console::getInstance();

        $this->usersService->deleteUnused();
    }
}
