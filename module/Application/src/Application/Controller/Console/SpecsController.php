<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

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

        return "done\n";
    }

    public function refreshUsersStatAction()
    {
        $this->specsService->refreshUsersConflictsStat();

        return "done\n";
    }

    public function refreshItemConflictFlagsAction()
    {
        $typeId = $this->params('type_id');
        $itemId = $this->params('item_id');

        $this->specsService->refreshItemConflictFlags($typeId, $itemId);

        return "done\n";
    }


    public function refreshUserStatAction()
    {
        $userId = $this->params('user_id');

        $this->specsService->refreshUserConflicts($userId);

        return "done\n";
    }

    public function updateSpecsVolumesAction()
    {
        $userTable = new User();

        $userTable->updateSpecsVolumes();

        return "done\n";
    }
}
