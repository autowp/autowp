<?php

namespace Autowp\User\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
use Autowp\User\Model\DbTable\User\Remember as UserRemember;
use Autowp\User\Model\DbTable\User\Rename as UserRename;

class ConsoleController extends AbstractActionController
{
    public function clearRememberAction()
    {
        $urTable = new UserRemember();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 60 DAY)'
        ]);

        $this->getResponse()->setContent(
            sprintf("%d user remember rows was deleted\ndone\n", $count)
        );
    }

    public function clearPasswordRemindAction()
    {
        $uprTable = new UserPasswordRemind();
        $count = $uprTable->delete([
            'created < DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);

        $this->getResponse()->setContent(
            sprintf("%d password remind rows was deleted\ndone\n", $count)
        );
    }

    public function clearRenamesAction()
    {
        $urTable = new UserRename();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 3 MONTH)'
        ]);

        $this->getResponse()->setContent(
            sprintf("%d user rename rows was deleted\ndone\n", $count)
        );
    }
}
