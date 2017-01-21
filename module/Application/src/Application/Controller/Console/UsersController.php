<?php

namespace Application\Controller\Console;

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

        return "User votes restored\n";
    }

    public function refreshVoteLimitsAction()
    {
        $affected = $this->usersService->updateUsersVoteLimits();

        return sprintf("Updated %s users\n", $affected);
    }

    public function deleteUnusedAction()
    {
        $this->usersService->deleteUnused();

        return "Deleted\n";
    }
}
