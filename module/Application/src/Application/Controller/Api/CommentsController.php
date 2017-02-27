<?php

namespace Application\Controller\Api;

use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Comments;

class CommentsController extends AbstractRestfulController
{
    /**
     * @var Comments
     */
    private $comments;

    public function __construct(Comments $comments)
    {
        $this->comments = $comments;
    }

    public function subscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        switch ($this->getRequest()->getMethod()) {
            case Request::METHOD_POST:
            case Request::METHOD_PUT:
                $this->comments->service()->subscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;

            case Request::METHOD_DELETE:
                $this->comments->service()->unSubscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;
        }

        return $this->notFoundAction();
    }
}
