<?php

namespace Autowp\Message;

use Application\Service\TelegramService;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function mb_strlen;
use function trim;

/**
 * @todo Unlink from Telegram
 */
class MessageService
{
    private TableGateway $table;

    private User $userModel;

    private const MESSAGES_PER_PAGE = 20;

    public const MAX_TEXT = 2000;

    private TelegramService $telegram;

    public function __construct(TelegramService $telegram, TableGateway $table, User $userModel)
    {
        $this->table = $table;

        $this->telegram = $telegram;

        $this->userModel = $userModel;
    }

    /**
     * @throws Exception
     */
    public function send(?int $fromId, int $toId, string $message): void
    {
        $message   = trim($message);
        $msgLength = mb_strlen($message);

        if ($msgLength <= 0) {
            throw new Exception('Message is empty');
        }

        if ($msgLength > self::MAX_TEXT) {
            throw new Exception('Message is too long');
        }

        $this->table->insert([
            'from_user_id' => $fromId ? $fromId : null,
            'to_user_id'   => $toId,
            'contents'     => $message,
            'add_datetime' => new Sql\Expression('NOW()'),
            'readen'       => 0,
        ]);

        $this->telegram->notifyMessage($fromId, $toId, $message);
    }

    /**
     * @throws Exception
     */
    public function getNewCount(int $userId): int
    {
        $row = currentFromResultSetInterface($this->table->select(
            function (Sql\Select $select) use ($userId): void {
                $select
                    ->columns(['count' => new Sql\Expression('COUNT(1)')])
                    ->where([
                        'to_user_id = ?' => $userId,
                        'NOT readen',
                    ]);
            }
        ));

        return $row ? (int) $row['count'] : 0;
    }

    public function delete(int $userId, int $messageId): void
    {
        $this->table->update([
            'deleted_by_from' => 1,
        ], [
            'from_user_id = ?' => $userId,
            'id = ?'           => $messageId,
        ]);

        $this->table->update([
            'deleted_by_to' => 1,
        ], [
            'to_user_id = ?' => $userId,
            'id = ?'         => $messageId,
        ]);
    }

    public function deleteAllSystem(int $userId): void
    {
        $this->table->delete([
            'to_user_id = ?' => $userId,
            'from_user_id IS NULL',
        ]);
    }

    public function deleteAllSent(int $userId): void
    {
        $this->table->update([
            'deleted_by_from' => 1,
        ], [
            'from_user_id = ?' => $userId,
        ]);
    }

    public function recycle(): int
    {
        return $this->table->delete([
            'deleted_by_to',
            'deleted_by_from OR from_user_id IS NULL',
        ]);
    }

    public function recycleSystem(): int
    {
        return $this->table->delete([
            'from_user_id is null',
            'add_datetime < date_sub(now(), interval 6 month)',
        ]);
    }

    private function getSystemSelect(int $userId): Sql\Select
    {
        return $this->table->getSql()->select()
            ->where([
                'to_user_id = ?' => (int) $userId,
                'from_user_id IS NULL',
                'NOT deleted_by_to',
            ])
            ->order('add_datetime DESC');
    }

    private function getInboxSelect(int $userId): Sql\Select
    {
        return $this->table->getSql()->select()
            ->where([
                'to_user_id = ?' => (int) $userId,
                'from_user_id',
                'NOT deleted_by_to',
            ])
            ->order('add_datetime DESC');
    }

    private function getSentSelect(int $userId): Sql\Select
    {
        return $this->table->getSql()->select()
            ->where([
                'from_user_id = ?' => (int) $userId,
                'NOT deleted_by_from',
            ])
            ->order('add_datetime DESC');
    }

    private function getDialogSelect(int $userId, int $withUserId): Sql\Select
    {
        $predicate1 = new Sql\Predicate\Predicate();
        $predicate1->expression(
            'from_user_id = ? and to_user_id = ? and NOT deleted_by_from',
            [(int) $userId, (int) $withUserId]
        );

        $predicate2 = new Sql\Predicate\Predicate();
        $predicate2->expression(
            'from_user_id = ? and to_user_id = ? and NOT deleted_by_to',
            [(int) $withUserId, (int) $userId]
        );

        $predicate = new Sql\Predicate\PredicateSet([
            $predicate1,
            $predicate2,
        ], Sql\Predicate\PredicateSet::COMBINED_BY_OR);

        return $this->table->getSql()->select()
            ->where($predicate)
            ->order('add_datetime DESC');
    }

    public function getDialogCount(int $userId, int $withUserId): int
    {
        $select = $this->getDialogSelect($userId, $withUserId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        return $paginator->getTotalItemCount();
    }

    public function getSentCount(int $userId): int
    {
        $select = $this->getSentSelect($userId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        return $paginator->getTotalItemCount();
    }

    public function markReaden(array $ids): void
    {
        if (count($ids)) {
            $this->table->update([
                'readen' => 1,
            ], [
                new Sql\Predicate\In('id', $ids),
            ]);
        }
    }

    private function markReadenRows(iterable $rows, int $userId): void
    {
        $ids = [];
        foreach ($rows as $message) {
            if ((! $message['readen']) && ((int) $message['to_user_id'] === $userId)) {
                $ids[] = (int) $message['id'];
            }
        }

        $this->markReaden($ids);
    }

    /**
     * @throws Exception
     */
    public function getInbox(int $userId, int $page): array
    {
        $select = $this->getInboxSelect($userId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getSentbox(int $userId, int $page): array
    {
        $select = $this->getSentSelect($userId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getSystembox(int $userId, int $page): array
    {
        $select = $this->getSystemSelect($userId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows),
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getDialogbox(int $userId, int $withUserId, int $page): array
    {
        $select = $this->getDialogSelect($userId, $withUserId);

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);

        $rows = $paginator->getCurrentItems();

        $this->markReadenRows($rows, $userId);

        return [
            'messages'  => $this->prepareList($userId, $rows, [
                'allMessagesLink' => false,
            ]),
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    private function prepareList(int $userId, iterable $rows, array $options = []): array
    {
        $defaults = [
            'allMessagesLink' => true,
        ];
        $options  = array_replace($defaults, $options);

        $cache = [];

        $messages = [];
        foreach ($rows as $message) {
            $author = $this->userModel->getRow(['id' => (int) $message['from_user_id']]);

            $isNew      = (int) $message['to_user_id'] === $userId && ! $message['readen'];
            $canDelete  = (int) $message['from_user_id'] === $userId || (int) $message['to_user_id'] === $userId;
            $authorIsMe = $author && ((int) $author['id'] === $userId);
            $canReply   = $author && ! $author['deleted'] && ! $authorIsMe;

            $dialogCount = 0;

            if ($options['allMessagesLink'] && $author) {
                $dialogWith = (int) $message['from_user_id'] === $userId
                    ? $message['to_user_id'] : $message['from_user_id'];

                if (isset($cache[$dialogWith])) {
                    $dialogCount = $cache[$dialogWith];
                } else {
                    $dialogCount        = $this->getDialogCount($userId, $dialogWith);
                    $cache[$dialogWith] = $dialogCount;
                }
            }

            $messages[] = [
                'id'                  => $message['id'],
                'author_id'           => $message['from_user_id'] ? (int) $message['from_user_id'] : null,
                'author'              => $author, //TODO: remove, not need in API
                'contents'            => $message['contents'],
                'isNew'               => $isNew,
                'canDelete'           => $canDelete,
                'date'                => Row::getDateTimeByColumnType('timestamp', $message['add_datetime']),
                'canReply'            => $canReply,
                'dialogCount'         => $dialogCount,
                'allMessagesLink'     => $options['allMessagesLink'],
                'to_user_id'          => (int) $message['to_user_id'],
                'dialog_with_user_id' => $message['from_user_id'] && (int) $message['from_user_id'] === $userId
                    ? (int) $message['to_user_id']
                    : (int) $message['from_user_id'],
            ];
        }

        return $messages;
    }
}
