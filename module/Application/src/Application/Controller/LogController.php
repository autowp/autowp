<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Twins\Group as TwinsGroup;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Log_Events;
use Cars;
use Engines;
use Factory;
use Picture;

class LogController extends AbstractActionController
{
    const EVENTS_PER_PAGE = 40;

    public function indexAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $logTable = new Log_Events();
        $vehicleTable = new Cars();
        $brandModel = new BrandModel();
        $engineTable = new Engines();
        $picturesTable = new Picture();
        $twinsGroupsTable = new TwinsGroup();
        $factoryTable = new Factory();

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

        $language = $this->language();

        $events = [];
        foreach ($paginator->getCurrentItems() as $event) {
            $vehicleRows = $vehicleTable->fetchAll(
                $vehicleTable->select(true)
                    ->join('log_events_cars', 'cars.id = log_events_cars.car_id', null)
                    ->where('log_events_cars.log_event_id = ?', $event->id)
            );
            $vehicles = [];
            foreach ($vehicleRows as $vehicleRow) {
                $vehicles[] = [
                    'name' => $vehicleRow->getNameData($language),
                    'url'  => $this->url()->fromRoute('moder/cars/params', [
                        'action' => 'car',
                        'car_id' => $vehicleRow->id
                    ])
                ];
            }

            $brands = $brandModel->getList($language, function($select) use ($event) {
                $select
                    ->join('log_events_brands', 'brands.id = log_events_brands.brand_id', null)
                    ->where('log_events_brands.log_event_id = ?', $event->id);
            });

            $engineRows = $engineTable->fetchAll(
                $engineTable->select(true)
                    ->join('log_events_engines', 'engines.id = log_events_engines.engine_id', null)
                    ->where('log_events_engines.log_event_id = ?', $event->id)
            );
            $engines = [];
            foreach ($engineRows as $engineRow) {
                $engines[] = [
                    'name' => $engineRow['caption'],
                    'url'  => $this->url()->fromRoute('moder/engines/params', [
                        'action'    => 'engine',
                        'engine_id' => $engineRow->id
                    ])
                ];
            }

            $picturesRows = $picturesTable->fetchAll(
                $picturesTable->select(true)
                    ->join('log_events_pictures', 'pictures.id = log_events_pictures.picture_id', null)
                    ->where('log_events_pictures.log_event_id = ?', $event->id)
            );
            $pictures = [];

            $names = $picturesTable->getNameData($picturesRows, [
                'language' => $language
            ]);

            foreach ($picturesRows as $picturesRow) {
                $id = $picturesRow->id;
                $pictures[] = [
                    'name' => isset($names[$id]) ? $names[$id] : null,
                    'url'  => $this->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'picture',
                        'picture_id' => $picturesRow->id
                    ])
                ];
            }

            $groupsRows = $twinsGroupsTable->fetchAll(
                $twinsGroupsTable->select(true)
                    ->join('log_events_twins_groups', 'twins_groups.id = log_events_twins_groups.twins_group_id', null)
                    ->where('log_events_twins_groups.log_event_id = ?', $event->id)
            );
            $twinsGroups = [];

            foreach ($groupsRows as $groupsRow) {
                $twinsGroups[] = [
                    'name' => $groupsRow['name'],
                    'url'  => $this->url()->fromRoute('moder/twins/params', [
                        'action'         => 'twins-group',
                        'twins_group_id' => $groupsRow['id']
                    ])
                ];
            }

            $factoryRows = $factoryTable->fetchAll(
                $factoryTable->select(true)
                    ->join('log_events_factory', 'factory.id = log_events_factory.factory_id', null)
                    ->where('log_events_factory.log_event_id = ?', $event->id)
            );
            $factories = [];

            foreach ($factoryRows as $factoryRow) {
                $factories[] = [
                    'name' => $factoryRow['name'],
                    'url'  => $this->url()->fromRoute('moder/factories/params', [
                        'action'     => 'factory',
                        'factory_id' => $factoryRow['id']
                    ])
                ];
            }

            $events[] = [
                'user'        => $event->findParentUsers(),
                'date'        => $event->getDateTime('add_datetime'),
                'desc'        => $event->description,
                'vehicles'    => $vehicles,
                'brands'      => $brands,
                'engines'     => $engines,
                'pictures'    => $pictures,
                'twinsGroups' => $twinsGroups,
                'factories'   => $factories
            ];
        }

        return [
            'paginator' => $paginator,
            'events'    => $events,
            'urlParams' => $this->params()->fromRoute()
        ];
    }
}