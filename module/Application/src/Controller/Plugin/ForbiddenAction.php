<?php

namespace Application\Controller\Plugin;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\View\Model\ViewModel;

class ForbiddenAction extends AbstractPlugin
{
    public function __invoke(): ViewModel
    {
        /** @var AbstractController $controller */
        $controller = $this->getController();
        $event      = $controller->getEvent();
        $routeMatch = $event->getRouteMatch();
        $routeMatch->setParam('action', 'forbidden');

        $response = $event->getResponse();
        if ($response instanceof Response) {
            $response->setStatusCode(Response::STATUS_CODE_403);
        }

        $model = new ViewModel(['content' => 'Forbidden']);
        $model->setTemplate('error/403');

        return $model;
    }
}
