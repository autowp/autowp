<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;
use Application\Service\UsersService;

use User_Remember;
use User_Password_Remind;
use User_Renames;

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

    public function clearHashesAction()
    {
        $urTable = new User_Remember();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 60 DAY)'
        ]);

        printf("%d user remember rows was deleted\n", $count);

        $uprTable = new User_Password_Remind();
        $count = $uprTable->delete([
            'created < DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);

        printf("%d password remind rows was deleted\n", $count);
    }

    public function clearRenamesAction()
    {
        $urTable = new User_Renames();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 3 MONTH)'
        ]);

        printf("%d user rename rows was deleted\n", $count);
    }
}
