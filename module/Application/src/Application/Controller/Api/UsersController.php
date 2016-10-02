<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Application\Model\DbTable\User;

class UsersController extends AbstractRestfulController
{
    public function get($id)
    {
        if ($id == 'me') {
            $id = $this->oauth2();
            if (!$id) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not authorized'));
            }
        }

        $userTable = new User();

        $userRow = $userTable->find($id)->current();
        if (!$userRow) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        return new JsonModel([
            'id'   => (int)$userRow->id,
            'name' => $userRow->name
        ]);
    }
}