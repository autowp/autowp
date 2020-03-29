<?php

namespace Application\Controller\Console;

use Application\Service\SpecificationsService;
use Laminas\Mvc\Controller\AbstractActionController;

class SpecsController extends AbstractActionController
{
    private SpecificationsService $specsService;

    public function __construct(SpecificationsService $specsService)
    {
        $this->specsService = $specsService;
    }

    public function refreshConflictFlagsAction(): string
    {
        $this->specsService->refreshConflictFlags();

        return "done\n";
    }

    public function refreshItemConflictFlagsAction(): string
    {
        $itemId = $this->params('item_id');

        $this->specsService->refreshItemConflictFlags($itemId);

        return "done\n";
    }

    public function refreshUsersStatAction(): string
    {
        $this->specsService->refreshUsersConflictsStat();

        return "done\n";
    }

    public function refreshUserStatAction(): string
    {
        $userId = $this->params('user_id');

        $this->specsService->refreshUserConflicts($userId);

        return "done\n";
    }

    public function refreshActualValuesAction(): string
    {
        $this->specsService->updateAllActualValues();

        return "done\n";
    }
}
