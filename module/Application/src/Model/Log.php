<?php

namespace Application\Model;

use Laminas\Db\Adapter\Exception\InvalidQueryException;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_replace;
use function strpos;

class Log
{
    private TableGateway $eventTable;

    private TableGateway $eventArticleTable;

    private TableGateway $eventItemTable;

    private TableGateway $eventPictureTable;

    private TableGateway $eventUserTable;

    public function __construct(
        TableGateway $logTable,
        TableGateway $eventArticleTable,
        TableGateway $eventItemTable,
        TableGateway $eventPictureTable,
        TableGateway $eventUserTable
    ) {
        $this->eventTable        = $logTable;
        $this->eventArticleTable = $eventArticleTable;
        $this->eventItemTable    = $eventItemTable;
        $this->eventPictureTable = $eventPictureTable;
        $this->eventUserTable    = $eventUserTable;
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
}
