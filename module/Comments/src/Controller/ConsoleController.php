<?php

namespace Autowp\Comments\Controller;

use Autowp\Comments\CommentsService;
use Laminas\Mvc\Controller\AbstractActionController;

class ConsoleController extends AbstractActionController
{
    private CommentsService $service;

    public function __construct(CommentsService $message)
    {
        $this->service = $message;
    }

    public function refreshRepliesCountAction(): string
    {
        $affected = $this->service->updateRepliesCount();

        return "ok. Affected: $affected\n";
    }

    public function cleanupDeletedAction(): string
    {
        $affected = $this->service->cleanupDeleted();

        return "ok. Deleted: $affected\n";
    }
}
