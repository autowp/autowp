<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Log_Events;

class LogController extends AbstractActionController
{
    const EVENTS_PER_PAGE = 40;

    public function indexAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->getResponse()->setStatusCode(403);
        }

        $logTable = new Log_Events();

        $select = $logTable->select(true)
            ->order(['add_datetime DESC', 'id DESC']);

        $articleId = (int)$this->params()->fromRoute('article_id');
        if ($articleId) {
            $select->join('log_events_articles', 'log_events.id=log_events_articles.log_event_id', null)
                   ->where('log_events_articles.article_id = ?', $articleId);
        }

        $brandId = (int)$this->params()->fromRoute('brand_id');
        if ($brandId) {
            $select->join('log_events_brands', 'log_events.id=log_events_brands.log_event_id', null)
                   ->where('log_events_brands.brand_id = ?', $brandId);
        }

        $carId = (int)$this->params()->fromRoute('car_id');
        if ($carId) {
            $select->join('log_events_cars', 'log_events.id=log_events_cars.log_event_id', null)
                   ->where('log_events_cars.car_id = ?', $carId);
        }

        $engineId = (int)$this->params()->fromRoute('engine_id');
        if ($engineId) {
            $select->join('log_events_engines', 'log_events.id=log_events_engines.log_event_id', null)
                   ->where('log_events_engines.engine_id = ?', $engineId);
        }

        $pictureId = (int)$this->params()->fromRoute('picture_id');
        if ($pictureId) {
            $select->join('log_events_pictures', 'log_events.id=log_events_pictures.log_event_id', null)
                   ->where('log_events_pictures.picture_id = ?', $pictureId);
        }

        $groupId = (int)$this->params()->fromRoute('twins_group_id');
        if ($groupId) {
            $select->join('log_events_twins_groups', 'log_events.id=log_events_twins_groups.log_event_id', null)
                   ->where('log_events_twins_groups.twins_group_id = ?', $groupId);
        }

        $factoryId = (int)$this->params()->fromRoute('factory_id');
        if ($factoryId) {
            $select->join('log_events_factory', 'log_events.id = log_events_factory.log_event_id', null)
                ->where('log_events_factory.factory_id = ?', $factoryId);
        }

        $userId = (int)$this->params()->fromRoute('user_id');
        if ($userId) {
            $select->where('log_events.user_id = ?', $userId);
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(self::EVENTS_PER_PAGE)
            ->setCurrentPageNumber($this->params()->fromRoute('page'));

        $events = [];
        foreach ($paginator->getCurrentItems() as $event) {
            $events[] = [
                'user' => $event->findParentUsers(),
                'date' => $event->getDateTime('add_datetime'),
                'desc' => $event->description
            ];
        }

        return [
            'paginator' => $paginator,
            'events'    => $events,
            'urlParams' => $this->params()->fromRoute()
        ];
    }
}