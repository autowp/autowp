<?php

namespace Autowp\Comments\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Comments\CommentsService;

class ConsoleController extends AbstractActionController
{
    /**
     * @var CommentsService
     */
    private $service;

    public function __construct(CommentsService $message)
    {
        $this->service = $message;
    }

    public function refreshRepliesCountAction()
    {
        $affected = $this->service->updateRepliesCount();

        return "ok. Affected: $affected\n";
    }
}
