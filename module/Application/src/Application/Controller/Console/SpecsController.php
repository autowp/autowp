<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

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

    public function refreshItemConflictFlagsAction()
    {
        $itemId = $this->params('item_id');

        $this->specsService->refreshItemConflictFlags($itemId);

        return "done\n";
    }

    public function refreshUsersStatAction()
    {
        $this->specsService->refreshUsersConflictsStat();

        return "done\n";
    }

    public function refreshUserStatAction()
    {
        $userId = $this->params('user_id');

        $this->specsService->refreshUserConflicts($userId);

        return "done\n";
    }

    public function refreshActualValuesAction()
    {
        $this->specsService->updateAllActualValues();

        return "done\n";
    }
}
