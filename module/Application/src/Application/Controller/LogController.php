<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Log\Event as LogEvent;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Item;
use Application\Paginator\Adapter\Zend1DbTableSelect;

class LogController extends AbstractActionController
{
    const EVENTS_PER_PAGE = 40;

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $logTable = new LogEvent();
        $vehicleTable = new Item();
        $brandModel = new BrandModel();
        $picturesTable = new Picture();
        $factoryTable = new Factory();

        $select = $logTable->select(true)
            ->order(['add_datetime DESC', 'id DESC']);

        $articleId = (int)$this->params()->fromRoute('article_id');
        if ($articleId) {
            $select->join('log_events_articles', 'log_events.id=log_events_articles.log_event_id', null)
                   ->where('log_events_articles.article_id = ?', $articleId);
        }

        $carId = (int)$this->params()->fromRoute('item_id');
        if ($carId) {
            $select->join('log_events_item', 'log_events.id=log_events_item.log_event_id', null)
                   ->where('log_events_item.item_id = ?', $carId);
        }

        $pictureId = (int)$this->params()->fromRoute('picture_id');
        if ($pictureId) {
            $select->join('log_events_pictures', 'log_events.id=log_events_pictures.log_event_id', null)
                   ->where('log_events_pictures.picture_id = ?', $pictureId);
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
                    ->join('log_events_item', 'item.id = log_events_item.item_id', null)
                    ->where('log_events_item.log_event_id = ?', $event->id)
            );
            $vehicles = [];
            foreach ($vehicleRows as $vehicleRow) {
                $vehicles[] = [
                    'name' => $vehicleRow->getNameData($language),
                    'url'  => $this->url()->fromRoute('moder/cars/params', [
                        'action'  => 'car',
                        'item_id' => $vehicleRow->id
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
                'user'        => $event->findParentRow(User::class),
                'date'        => $event->getDateTime('add_datetime'),
                'desc'        => $event->description,
                'vehicles'    => $vehicles,
                'pictures'    => $pictures,
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
