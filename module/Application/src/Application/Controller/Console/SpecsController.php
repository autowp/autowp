<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\SpecificationsService;

use Users;

class SpecsController extends AbstractActionController
{
    public function refreshConflictFlagsAction()
    {
        $service = new SpecificationsService();

        $service->refreshConflictFlags();

        Console::getInstance()->writeLine("done");
    }

    public function refreshUsersStatAction()
    {
        $service = new SpecificationsService();

        $service->refreshUsersConflictsStat();

        Console::getInstance()->writeLine("done");
    }

    public function refreshItemConflictFlagsAction()
    {
        $typeId = $this->params('type_id');
        $itemId = $this->params('item_id');

        $service = new SpecificationsService();

        $service->refreshItemConflictFlags($typeId, $itemId);

        Console::getInstance()->writeLine("done");
    }


    public function refreshUserStatAction()
    {
        $userId = $this->params('user_id');

        $service = new SpecificationsService();

        $service->refreshUserConflicts($userId);

        Console::getInstance()->writeLine("done");
    }

    public function updateSpecsVolumesAction()
    {
        $userTable = new Users();

        $userTable->updateSpecsVolumes();

        Console::getInstance()->writeLine("done");
    }
}