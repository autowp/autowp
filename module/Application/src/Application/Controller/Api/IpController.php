<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class IpController extends AbstractRestfulController
{
    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        return new JsonModel([
            'host' => gethostbyaddr($this->params('ip'))
        ]);
    }
}
