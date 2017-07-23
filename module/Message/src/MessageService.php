<?php

namespace Autowp\Message;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

use Autowp\Commons\Db\Table;
use Autowp\User\Model\DbTable\User;

use Application\Service\TelegramService;

/**
 * @todo Unlink from Telegram
 */
class MessageService
{
    /**
     * @var TableGateway
     */
    private $table;

    const MESSAGES_PER_PAGE = 20;

    /**
     * @var TelegramService
     */
    private $telegram;

    public function __construct(TelegramService $telegram, TableGateway $table)
    {
        $this->table = $table;

        $this->telegram = $telegram;
    }

    public function send($fromId, $toId, $message)
    {
        $message = trim($message);
        $msgLength = mb_strlen($message);

        if ($msgLength <= 0) {
            throw new \Exception('Message is empty');
        }

        if ($msgLength > 2000) {
            throw new \Exception('Message is too long');
        }

        $this->table->insert([
            'from_user_id' => $fromId ? (int)$fromId : null,
            'to_user_id'   => (int)$toId,
            'contents'     => $message,
            'add_datetime' => new Sql\Expression('NOW()'),
            'readen'       => 0
        ]);

        if ($this->telegram) {
            $this->telegram->notifyMessage($fromId, $toId, $message);
        }
    }

    public function getNewCount($userId)
    {
        $row = $this->table->select(function (Sql\Select $select) use ($userId) {
            $select
                ->columns(['count' => new Sql\Expression('COUNT(1)')])
                ->where([
                    'to_user_id = ?' => (int)$userId,
                    'NOT readen'
                ]);
        })->current();

        return $row ? $row['count'] : null;
    }

    public function delete($userId, $messageId)
    {
        $this->table->update([
            'deleted_by_from'  => 1
        ], [
            'from_user_id = ?' => (int)$userId,
            'id = ?'           => (int)$messageId
        ]);

        $this->table->update([
            'deleted_by_to'  => 1
        ], [
            'to_user_id = ?' => (int)$userId,
            'id = ?'         => (int)$messageId
        ]);
    }

    public function deleteAllSystem($userId)
    {
        $this->table->delete([
            'to_user_id = ?' => (int)$userId,
            'from_user_id IS NULL'
        ]);
    }

    public function deleteAllSent($userId)
    {
        $this->table->update([
            'deleted_by_from' => 1
        ], [
            'from_user_id = ?' => (int)$userId,
        ]);
    }

    public function recycle()
    {
        return $this->table->delete([
            'deleted_by_to',
            'deleted_by_from OR from_user_id IS NULL'
        ]);
    }

    public function recycleSystem()
    {
        return $this->table->delete([
            'from_user_id is null',
            'add_datetime < date_sub(now(), interval 6 month)'
        ]);
    }

    private function getSystemSelect($userId)
    {
        return $this->table->getSql()->select()
            ->where([
                'to_user_id = ?' => (int)$userId,
                'from_user_id IS NULL',
                'NOT deleted_by_to'
            ])
            ->order('add_datetime DESC');
    }

    private function getInboxSelect($userId)
    {
        return $this->table->getSql()->select()
            ->where([
                'to_user_id = ?' => (int)$userId,
                'from_user_id',
                'NOT deleted_by_to'
            ])
            ->order('add_datetime DESC');
    }

    private function getSentSelect($userId)
    {
        return $this->table->getSql()->select()
            ->where([
                'from_user_id = ?' => (int)$userId,
                'NOT deleted_by_from'
            ])
            ->order('add_datetime DESC');
    }

    private function getDialogSelect($userId, $withUserId)
    {
        $predicate1 = new Sql\Predicate\Predicate();
        $predicate1->expression(
            'from_user_id = ? and to_user_id = ? and NOT deleted_by_from',
            [(int)$userId, (int)$withUserId]
        );

        $predicate2 = new Sql\Predicate\Predicate();
        $predicate2->expression(
            'from_user_id = ? and to_user_id = ? and NOT deleted_by_to',
            [(int)$withUserId, (int)$userId]
        );

        $predicate = new Sql\Predicate\PredicateSet([
            $predicate1,
            $predicate2
        ], Sql\Predicate\PredicateSet::COMBINED_BY_OR);

        return $this->table->getSql()->select()
            ->where($predicate)
            ->order('add_datetime DESC');
    }

    public function getSystemCount($userId)
    {
        $select = $this->getSystemSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getNewSystemCount($userId)
    {
        $select = $this->getSystemSelect($userId)
            ->where('NOT readen');

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getInboxCount($userId)
    {
        $select = $this->getInboxSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getInboxNewCount($userId)
    {
        $select = $this->getInboxSelect($userId)
            ->where('NOT readen');

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getDialogCount($userId, $withUserId)
    {
        $select = $this->getDialogSelect($userId, $withUserId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getSentCount($userId)
    {
        $select = $this->getSentSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function markReaden($ids)
    {
        if (count($ids)) {
            $this->table->update([
                'readen' => 1
            ], [
                new Sql\Predicate\In('id', $ids)
            ]);
        }
    }

    private function markReadenRows($rows, $userId)
    {
        $ids = [];
        foreach ($rows as $message) {
            if ((! $message['readen']) && ($message['to_user_id'] == $userId)) {
                $ids[] = (int)$message['id'];
            }
        }

        $this->markReaden($ids);
    }

    public function getInbox($userId, $page)
    {
        $select = $this->getInboxSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }

    public function getSentbox($userId, $page)
    {
        $select = $this->getSentSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }

    public function getSystembox($userId, $page)
    {
        $select = $this->getSystemSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }

    public function getDialogbox($userId, $withUserId, $page)
    {
        $select = $this->getDialogSelect($userId, $withUserId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows, [
                'allMessagesLink' => false
            ]),
            'paginator' => $paginator
        ];
    }

    private function prepareList($userId, $rows, array $options = [])
    {
        $defaults = [
            'allMessagesLink' => true
        ];
        $options = array_replace($defaults, $options);

        $cache = [];

        $userTable = new User();

        $messages = [];
        foreach ($rows as $message) {
            $author = $userTable->find($message['from_user_id'])->current();

            $isNew = $message['to_user_id'] == $userId && ! $message['readen'];
            $canDelete = $message['from_user_id'] == $userId || $message['to_user_id'] == $userId;
            $authorIsMe = $author && ($author->id == $userId);
            $canReply = $author && ! $author->deleted && ! $authorIsMe;

            $dialogCount = 0;

            if ($canReply) {
                if ($options['allMessagesLink'] && $author && ! $authorIsMe) {
                    if (isset($cache[$author->id])) {
                        $dialogCount = $cache[$author->id];
                    } else {
                        $dialogCount = $this->getDialogCount($userId, $author->id);
                        $cache[$author->id] = $dialogCount;
                    }
                }
            }

            $messages[] = [
                'id'              => $message['id'],
                'author'          => $author,
                'contents'        => $message['contents'],
                'isNew'           => $isNew,
                'canDelete'       => $canDelete,
                'date'            => Table\Row::getDateTimeByColumnType('timestamp', $message['add_datetime']),
                'canReply'        => $canReply,
                'dialogCount'     => $dialogCount,
                'allMessagesLink' => $options['allMessagesLink']
            ];
        }

        return $messages;
    }
}
