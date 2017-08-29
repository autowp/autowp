<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Autowp\Forums\Forums;

class ForumController extends AbstractActionController
{
    /**
     * @var Forums
     */
    private $forums;

    public function __construct(
        Forums $forums
    ) {
        $this->forums = $forums;
    }

    public function userSummaryAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'subscriptionsCount' => $this->forums->getSubscribedTopicsCount($user['id'])
        ]);
    }
}
