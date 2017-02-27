<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

use Autowp\Commons\Db\Table;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\Brand as BrandModel;

use Zend_Db_Table_Row_Abstract;

use Exception;

class Log
{
    const EVENTS_PER_PAGE = 40;

    /**
     * @var TableGateway
     */
    private $eventTable;

    /**
     * @var Adapter
     */
    private $adapter;

    private $router;

    public function __construct(Adapter $adapter, $router)
    {
        $this->adapter = $adapter;
        $this->eventTable = new TableGateway('log_events', $adapter);

        $this->router = $router;
    }

    public function addEvent($userId, $message, $objects)
    {
        $this->eventTable->insert([
            'description'  => $message,
            'user_id'      => (int)$userId,
            'add_datetime' => new Sql\Expression('NOW()')
        ]);
        $id = $this->eventTable->getLastInsertValue();

        $this->assign($id, $objects);
    }

    private function assign($id, $items)
    {
        $items = is_array($items) ? $items : [$items];

        foreach ($items as $item) {
            if (! ($item instanceof Zend_Db_Table_Row_Abstract)) {
                throw new Exception('Not a table row');
            }

            $table = $item->getTable();

            $col = $tableName = null;
            switch (true) {
                case $table instanceof DbTable\Picture:
                    $col = 'picture_id';
                    $tableName = 'log_events_pictures';
                    break;
                case $table instanceof DbTable\Item:
                    $col = 'item_id';
                    $tableName = 'log_events_item';
                    break;
                case $table instanceof DbTable\Article:
                    $col = 'article_id';
                    $tableName = 'log_events_articles';
                    break;
                case $table instanceof User:
                    $col = 'user_id';
                    $tableName = 'log_events_user';
                    break;
                default:
                    throw new Exception('Unknown data type');
            }

            if ($col && $tableName) {
                $table = new TableGateway($tableName, $this->adapter);
                try {
                    $table->insert([
                        'log_event_id' => $id,
                        $col           => $item['id']
                    ]);
                } catch (\Zend\Db\Adapter\Exception\InvalidQueryException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }
        }
        return $this;
    }

    public function getList(array $options)
    {
        $defaults = [
            'article_id' => null,
            'item_id'    => null,
            'picture_id' => null,
            'user_id'    => null,
            'page'       => null,
            'language'   => 'en'
        ];
        $options = array_replace($defaults, $options);

        $itemTable = new DbTable\Item();
        $brandModel = new BrandModel();
        $picturesTable = new DbTable\Picture();
        $userTable = new User();

        $select = new Sql\Select($this->eventTable->getTable());
        $select->order(['add_datetime DESC', 'id DESC']);

        $articleId = (int)$options['article_id'];
        if ($articleId) {
            $select
                ->join('log_events_articles', 'log_events.id = log_events_articles.log_event_id', [])
                ->where(['log_events_articles.article_id = ?' => $articleId]);
        }

        $itemId = (int)$options['item_id'];
        if ($itemId) {
            $select
                ->join('log_events_item', 'log_events.id = log_events_item.log_event_id', [])
                ->where(['log_events_item.item_id = ?' => $itemId]);
        }

        $pictureId = (int)$options['picture_id'];
        if ($pictureId) {
            $select
                ->join('log_events_pictures', 'log_events.id = log_events_pictures.log_event_id', [])
                ->where(['log_events_pictures.picture_id = ?' => $pictureId]);
        }

        $userId = (int)$options['user_id'];
        if ($userId) {
            $select->where(['log_events.user_id = ?' => $userId]);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->adapter)
        );

        $paginator
            ->setItemCountPerPage(self::EVENTS_PER_PAGE)
            ->setCurrentPageNumber($options['page']);

        $language = $options['language'];

        $events = [];
        foreach ($paginator->getCurrentItems() as $event) {
            $vehicleRows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->join('log_events_item', 'item.id = log_events_item.item_id', null)
                    ->where('log_events_item.log_event_id = ?', $event['id'])
            );
            $vehicles = [];
            foreach ($vehicleRows as $vehicleRow) {
                $vehicles[] = [
                    'name' => $vehicleRow->getNameData($language),
                    'url'  => $this->router->assemble([
                        'action'  => 'car',
                        'item_id' => $vehicleRow->id
                    ], [
                        'name' => 'moder/cars/params'
                    ])
                ];
            }

            $picturesRows = $picturesTable->fetchAll(
                $picturesTable->select(true)
                    ->join('log_events_pictures', 'pictures.id = log_events_pictures.picture_id', null)
                    ->where('log_events_pictures.log_event_id = ?', $event['id'])
            );
            $pictures = [];

            $names = $picturesTable->getNameData($picturesRows, [
                'language' => $language
            ]);

            foreach ($picturesRows as $picturesRow) {
                $id = $picturesRow->id;
                $pictures[] = [
                    'name' => isset($names[$id]) ? $names[$id] : null,
                    'url'  => $this->router->assemble([
                        'action'     => 'picture',
                        'picture_id' => $picturesRow->id
                    ], [
                        'name' => 'moder/pictures/params'
                    ])
                ];
            }

            $events[] = [
                'user'     => $userTable->find($event['user_id'])->current(),
                'date'     => Table\Row::getDateTimeByColumnType('timestamp', $event['add_datetime']),
                'desc'     => $event['description'],
                'vehicles' => $vehicles,
                'pictures' => $pictures
            ];
        }

        return [
            'paginator' => $paginator,
            'events'    => $events
        ];
    }
}
