<?php

namespace Application\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;

class ForbiddenAction extends AbstractPlugin
{
    public function __invoke(): ViewModel
    {
        /** @var MvcEvent $event */
        $event      = $this->getController()->getEvent();
        $routeMatch = $event->getRouteMatch();
        $routeMatch->setParam('action', 'forbidden');

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $event->getResponse()->setStatusCode(403);

        $model = new ViewModel(['content' => 'Forbidden']);
        $model->setTemplate('error/403');

        return $model;
    }
}
