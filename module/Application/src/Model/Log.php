<?php

namespace Application\Model;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Exception\InvalidQueryException;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator;

use function array_replace;
use function strpos;

class Log
{
    private const EVENTS_PER_PAGE = 40;

    private TableGateway $eventTable;

    private Picture $picture;

    private TableGateway $eventArticleTable;

    private TableGateway $eventItemTable;

    private TableGateway $eventPictureTable;

    private TableGateway $eventUserTable;

    private TableGateway $itemTable;

    private User $userModel;

    public function __construct(
        Picture $picture,
        TableGateway $logTable,
        TableGateway $eventArticleTable,
        TableGateway $eventItemTable,
        TableGateway $eventPictureTable,
        TableGateway $eventUserTable,
        TableGateway $itemTable,
        User $userModel
    ) {
        $this->itemTable         = $itemTable;
        $this->eventTable        = $logTable;
        $this->picture           = $picture;
        $this->eventArticleTable = $eventArticleTable;
        $this->eventItemTable    = $eventItemTable;
        $this->eventPictureTable = $eventPictureTable;
        $this->eventUserTable    = $eventUserTable;
        $this->userModel         = $userModel;
    }

    public function addEvent(int $userId, string $message, array $objects): void
    {
        $this->eventTable->insert([
            'description'  => $message,
            'user_id'      => $userId,
            'add_datetime' => new Sql\Expression('NOW()'),
        ]);
        $id = $this->eventTable->getLastInsertValue();

        $this->assign($id, $objects);
    }

    private function assign(int $id, array $items): void
    {
        $defaults = [
            'items'    => [],
            'pictures' => [],
            'users'    => [],
            'articles' => [],
        ];
        $items    = array_replace($defaults, $items);

        foreach ((array) $items['items'] as $item) {
            try {
                $this->eventItemTable->insert([
                    'log_event_id' => $id,
                    'item_id'      => $item,
                ]);
            } catch (InvalidQueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }

        foreach ((array) $items['pictures'] as $item) {
            try {
                $this->eventPictureTable->insert([
                    'log_event_id' => $id,
                    'picture_id'   => $item,
                ]);
            } catch (InvalidQueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }

        foreach ((array) $items['users'] as $item) {
            try {
                $this->eventUserTable->insert([
                    'log_event_id' => $id,
                    'user_id'      => $item,
                ]);
            } catch (InvalidQueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }

        foreach ((array) $items['articles'] as $item) {
            try {
                $this->eventArticleTable->insert([
                    'log_event_id' => $id,
                    'article_id'   => $item,
                ]);
            } catch (InvalidQueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }

    public function getList(array $options): array
    {
        $defaults = [
            'article_id' => null,
            'item_id'    => null,
            'picture_id' => null,
            'user_id'    => null,
            'page'       => null,
            'language'   => 'en',
        ];
        $options  = array_replace($defaults, $options);

        $select = new Sql\Select($this->eventTable->getTable());
        $select->order(['add_datetime DESC', 'id DESC']);

        $articleId = (int) $options['article_id'];
        if ($articleId) {
            $select
                ->join('log_events_articles', 'log_events.id = log_events_articles.log_event_id', [])
                ->where(['log_events_articles.article_id = ?' => $articleId]);
        }

        $itemId = (int) $options['item_id'];
        if ($itemId) {
            $select
                ->join('log_events_item', 'log_events.id = log_events_item.log_event_id', [])
                ->where(['log_events_item.item_id = ?' => $itemId]);
        }

        $pictureId = (int) $options['picture_id'];
        if ($pictureId) {
            $select
                ->join('log_events_pictures', 'log_events.id = log_events_pictures.log_event_id', [])
                ->where(['log_events_pictures.picture_id = ?' => $pictureId]);
        }

        $userId = (int) $options['user_id'];
        if ($userId) {
            $select->where(['log_events.user_id = ?' => $userId]);
        }

        /** @var Adapter $adapter */
        $adapter   = $this->eventTable->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(self::EVENTS_PER_PAGE)
            ->setCurrentPageNumber($options['page']);

        $events = [];
        foreach ($paginator->getCurrentItems() as $event) {
            $select = new Sql\Select($this->itemTable->getTable());
            $select->join('log_events_item', 'item.id = log_events_item.item_id', [])
                ->where(['log_events_item.log_event_id' => $event['id']]);

            $itemRows = [];
            foreach ($this->itemTable->selectWith($select) as $row) {
                $itemRows[] = $row;
            }

            $pictureRows = $this->picture->getRows([
                'log' => $event['id'],
            ]);

            $events[] = [
                'user'     => $this->userModel->getRow((int) $event['user_id']),
                'date'     => Row::getDateTimeByColumnType('timestamp', $event['add_datetime']),
                'desc'     => $event['description'],
                'items'    => $itemRows,
                'pictures' => $pictureRows,
            ];
        }

        return [
            'paginator' => $paginator,
            'events'    => $events,
        ];
    }
}
