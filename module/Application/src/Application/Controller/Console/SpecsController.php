<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\User;
use Application\Service\SpecificationsService;

class SpecsController extends AbstractActionController
{
    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    public function __construct(SpecificationsService $specsService)
    {
        $this->specsService = $specsService;
    }

    public function refreshConflictFlagsAction()
    {
        $this->specsService->refreshConflictFlags();

        Console::getInstance()->writeLine("done");
    }

    public function refreshUsersStatAction()
    {
        $this->specsService->refreshUsersConflictsStat();

        Console::getInstance()->writeLine("done");
    }

    public function refreshItemConflictFlagsAction()
    {
        $typeId = $this->params('type_id');
        $itemId = $this->params('item_id');

        $this->specsService->refreshItemConflictFlags($typeId, $itemId);

        Console::getInstance()->writeLine("done");
    }


    public function refreshUserStatAction()
    {
        $userId = $this->params('user_id');

        $this->specsService->refreshUserConflicts($userId);

        Console::getInstance()->writeLine("done");
    }

    public function updateSpecsVolumesAction()
    {
        $userTable = new User();

        $userTable->updateSpecsVolumes();

        Console::getInstance()->writeLine("done");
    }
}