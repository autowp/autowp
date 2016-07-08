<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\View\Model\ViewModel;

class ForbiddenAction extends AbstractPlugin
{
    public function __invoke()
    {
        $event      = $this->getController()->getEvent();
        $routeMatch = $event->getRouteMatch();
        $routeMatch->setParam('action', 'forbidden');
        
        $event->getResponse()->setStatusCode(403);
        
        $model = new ViewModel(['content' => 'Forbidden']);
        $model->setTemplate('error/403');
        
        return $model;
    }
}