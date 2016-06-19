<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application_Service_Specifications;
use Users;

class SpecsController extends AbstractActionController
{
    public function refreshConflictFlagsAction()
    {
        $service = new Application_Service_Specifications();

        $service->refreshConflictFlags();

        Console::getInstance()->writeLine("done");
    }

    public function refreshUsersStatAction()
    {
        $service = new Application_Service_Specifications();

        $service->refreshUsersConflictsStat();

        Console::getInstance()->writeLine("done");
    }

    public function refreshItemConflictFlagsAction()
    {
        $typeId = $this->params('type_id');
        $itemId = $this->params('item_id');

        $service = new Application_Service_Specifications();

        $service->refreshItemConflictFlags($typeId, $itemId);

        Console::getInstance()->writeLine("done");
    }


    public function refreshUserStatAction()
    {
        $userId = $this->params('user_id');

        $service = new Application_Service_Specifications();

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