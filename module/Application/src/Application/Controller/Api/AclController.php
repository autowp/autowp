<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class AclController extends AbstractRestfulController
{
    public function rolesAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }
        
        $user = $this->user()->get();
        $result = [
            $user->role => true
        ];
        
        $roles = $this->params()->fromQuery('roles');
        $roles = explode(',', $roles);

        foreach ($roles as $role) {
            $result[$role] = $this->user()->inheritsRole($role);
        }
        
        return new JsonModel($result);
    }
}
