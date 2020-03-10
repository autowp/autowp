<?php

namespace Autowp\Message;

use Application\Service\TelegramService;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator;

use function array_replace;
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

        if ($this->telegram) {
            $this->telegram->notifyMessage($fromId, $toId, $message);
        }
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function getNewCount(int $userId): int
    {
        $row = $this->table->select(
            /**
             * @suppress PhanPluginMixedKeyNoKey
             */
            function (Sql\Select $select) use ($userId) {
                $select
                    ->columns(['count' => new Sql\Expression('COUNT(1)')])
                    ->where([
                        'to_user_id = ?' => $userId,
                        'NOT readen',
                    ]);
            }
        )->current();

        return $row ? (int) $row['count'] : 0;
    }

    public function delete(int $userId, int $messageId)
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

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
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

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
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

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
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

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
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

    public function getSystemCount(int $userId): int
    {
        $select = $this->getSystemSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getNewSystemCount(int $userId): int
    {
        $select = $this->getSystemSelect($userId)
            ->where('NOT readen');

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getInboxCount(int $userId): int
    {
        $select = $this->getInboxSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getInboxNewCount(int $userId): int
    {
        $select = $this->getInboxSelect($userId)
            ->where('NOT readen');

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getDialogCount(int $userId, int $withUserId): int
    {
        $select = $this->getDialogSelect($userId, $withUserId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    public function getSentCount(int $userId): int
    {
        $select = $this->getSentSelect($userId);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
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

    /**
     * @param ResultSet|array $rows
     */
    private function markReadenRows($rows, int $userId): void
    {
        $ids = [];
        foreach ($rows as $message) {
            if ((! $message['readen']) && ($message['to_user_id'] === $userId)) {
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
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getSentbox(int $userId, int $page): array
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
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getSystembox(int $userId, int $page): array
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
            'paginator' => $paginator,
        ];
    }

    /**
     * @throws Exception
     */
    public function getDialogbox(int $userId, int $withUserId, int $page): array
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
                'allMessagesLink' => false,
            ]),
            'paginator' => $paginator,
        ];
    }

    /**
     * @param ResultSet|array $rows
     * @throws Exception
     */
    private function prepareList(int $userId, $rows, array $options = []): array
    {
        $defaults = [
            'allMessagesLink' => true,
        ];
        $options  = array_replace($defaults, $options);

        $cache = [];

        $messages = [];
        foreach ($rows as $message) {
            $author = $this->userModel->getRow(['id' => (int) $message['from_user_id']]);

            $isNew      = $message['to_user_id'] === $userId && ! $message['readen'];
            $canDelete  = $message['from_user_id'] === $userId || $message['to_user_id'] === $userId;
            $authorIsMe = $author && ($author['id'] === $userId);
            $canReply   = $author && ! $author['deleted'] && ! $authorIsMe;

            $dialogCount = 0;

            if ($options['allMessagesLink'] && $author) {
                $dialogWith = $message['from_user_id'] === $userId ? $message['to_user_id'] : $message['from_user_id'];

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
                'dialog_with_user_id' => $message['from_user_id'] && $message['from_user_id'] === $userId
                    ? (int) $message['to_user_id']
                    : (int) $message['from_user_id'],
            ];
        }

        return $messages;
    }
}
