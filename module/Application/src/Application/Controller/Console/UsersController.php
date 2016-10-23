<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
use Application\Model\DbTable\User\Remember as UserRemember;
use Application\Model\DbTable\User\Rename as UserRename;
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

    public function clearHashesAction()
    {
        $console = Console::getInstance();
        
        $urTable = new UserRemember();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 60 DAY)'
        ]);

        $console->writeLine(sprintf("%d user remember rows was deleted\n", $count));

        $uprTable = new UserPasswordRemind();
        $count = $uprTable->delete([
            'created < DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);

        $console->writeLine(sprintf("%d password remind rows was deleted\n", $count));
    }

    public function clearRenamesAction()
    {
        $urTable = new UserRename();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 3 MONTH)'
        ]);

        $console = Console::getInstance();
        $console->writeLine(sprintf("%d user rename rows was deleted\n", $count));
    }
    
    public function deleteUnusedAction()
    {
        $console = Console::getInstance();
        
        $this->usersService->deleteUnused();
    }
}
