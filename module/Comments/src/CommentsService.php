<?php

namespace Autowp\Comments;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator;
use Laminas\Paginator\Adapter\LaminasDb\DbSelect;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function is_array;
use function reset;

class CommentsService
{
    public const MAX_MESSAGE_LENGTH = 16 * 1024;

    private TableGateway $topicTable;

    private TableGateway $topicViewTable;

    private TableGateway $messageTable;

    private TableGateway $topicSubscribeTable;

    public function __construct(
        TableGateway $topicTable,
        TableGateway $messageTable,
        TableGateway $topicViewTable,
        TableGateway $topicSubscribeTable
    ) {
        $this->topicTable          = $topicTable;
        $this->messageTable        = $messageTable;
        $this->topicViewTable      = $topicViewTable;
        $this->topicSubscribeTable = $topicSubscribeTable;
    }

    /**
     * @throws Exception
     */
    public function add(array $data): int
    {
        $typeId   = (int) $data['typeId'];
        $itemId   = (int) $data['itemId'];
        $authorId = (int) $data['authorId'];
        $parentId = isset($data['parentId']) && $data['parentId'] ? (int) $data['parentId'] : null;

        $parentMessage = null;
        if ($parentId) {
            $parentMessage = currentFromResultSetInterface($this->messageTable->select([
                'type_id' => $typeId,
                'item_id' => $itemId,
                'id'      => $parentId,
            ]));
            if (! $parentMessage) {
                return 0;
            }

            if ($parentMessage['deleted']) {
                return 0;
            }
        }

        $data = [
            'datetime'            => new Sql\Expression('NOW()'),
            'type_id'             => $typeId,
            'item_id'             => $itemId,
            'parent_id'           => $parentMessage ? $parentMessage['id'] : null,
            'author_id'           => $authorId,
            'message'             => (string) $data['message'],
            'ip'                  => new Sql\Expression('INET6_ATON(?)', [$data['ip']]),
            'moderator_attention' => $data['moderatorAttention']
                ? Attention::REQUIRED
                : Attention::NONE,
        ];

        $this->messageTable->insert($data);
        $messageId = $this->messageTable->getLastInsertValue();

        if ($parentMessage) {
            $this->updateMessageRepliesCount($parentMessage['id']);
        }

        $this->updateTopicStat($typeId, $itemId);
        $this->updateTopicView($typeId, $itemId, $authorId);

        return $messageId;
    }

    /**
     * @throws Exception
     */
    private function updateMessageRepliesCount(int $messageId): void
    {
        $select = $this->messageTable->getSql()->select()
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $messageId]);

        $row = currentFromResultSetInterface($this->messageTable->selectWith($select));

        $this->messageTable->update([
            'replies_count' => $row['count'],
        ], [
            'id' => $messageId,
        ]);
    }

    public function updateTopicView(int $typeId, int $itemId, int $userId): void
    {
        $sql = '
            insert into comment_topic_view (user_id, type_id, item_id, `timestamp`)
            values (?, ?, ?, NOW())
            on duplicate key update `timestamp` = values(`timestamp`)
        ';
        /** @var Adapter $adapter */
        $adapter   = $this->topicTable->getAdapter();
        $statement = $adapter->query($sql);
        $statement->execute([$userId, $typeId, $itemId]);
    }

    /**
     * @throws Exception
     */
    public function moveMessages(int $srcTypeId, int $srcItemId, int $dstTypeId, int $dstItemId): void
    {
        $this->messageTable->update([
            'type_id' => $dstTypeId,
            'item_id' => $dstItemId,
        ], [
            'type_id' => $srcTypeId,
            'item_id' => $srcItemId,
        ]);

        $this->updateTopicStat($srcTypeId, $srcItemId);
        $this->updateTopicStat($dstTypeId, $dstItemId);
    }

    /**
     * @param int|array $itemId
     */
    public function getTopicStat(int $typeId, $itemId): array
    {
        $isArrayRequest = is_array($itemId);
        $itemId         = (array) $itemId;

        $result = [];

        if (count($itemId) > 0) {
            $select = $this->topicTable->getSql()->select()
                ->columns(['item_id', 'messages'])
                ->where([
                    'comment_topic.type_id' => $typeId,
                    new Sql\Predicate\In('comment_topic.item_id', $itemId),
                ]);
            $rows   = $this->topicTable->selectWith($select);

            foreach ($rows as $row) {
                $result[$row['item_id']] = [
                    'messages' => (int) $row['messages'],
                ];
            }
        }

        if ($isArrayRequest) {
            return $result;
        }

        if (count($result) <= 0) {
            return [
                'messages' => 0,
            ];
        }

        return reset($result);
    }

    /**
     * @throws Exception
     */
    private function updateTopicStat(int $typeId, int $itemId): void
    {
        $messagesCount = $this->countMessages($typeId, $itemId);

        if ($messagesCount <= 0) {
            $this->topicTable->delete([
                'item_id' => $itemId,
                'type_id' => $typeId,
            ]);
            return;
        }

        $lastUpdate = $this->getLastUpdate($typeId, $itemId);

        $sql = '
            INSERT INTO comment_topic (item_id, type_id, last_update, messages)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_update=VALUES(last_update), messages=VALUES(messages)
        ';

        /** @var Adapter $adapter */
        $adapter   = $this->topicTable->getAdapter();
        $statement = $adapter->query($sql);
        $statement->execute([$itemId, $typeId, $lastUpdate, $messagesCount]);
    }

    /**
     * @throws Exception
     */
    private function getMessagesCountFromTimestamp(int $typeId, int $itemId, string $timestamp): int
    {
        $select = $this->messageTable->getSql()->select()
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'item_id'      => $itemId,
                'type_id'      => $typeId,
                'datetime > ?' => $timestamp,
            ]);

        $countRow = currentFromResultSetInterface($this->messageTable->selectWith($select));

        return (int) $countRow['count'];
    }

    /**
     * @param int|array $itemId
     * @return array|int
     * @throws Exception
     */
    public function getNewMessages(int $typeId, $itemId, int $userId)
    {
        $isArrayRequest = is_array($itemId);
        $itemId         = (array) $itemId;

        $rows = [];
        if (count($itemId) > 0) {
            $select = $this->topicViewTable->getSql()->select()
                ->columns(['item_id', 'timestamp'])
                ->where([
                    'type_id' => $typeId,
                    'user_id' => $userId,
                    new Sql\Predicate\In('item_id', $itemId),
                ]);
            $rows   = $this->topicViewTable->selectWith($select);
        }

        $result = [];
        foreach ($rows as $row) {
            if ($row['timestamp']) {
                $id          = $row['item_id'];
                $result[$id] = $this->getMessagesCountFromTimestamp($typeId, $id, $row['timestamp']);
            }
        }

        if ($isArrayRequest) {
            return $result;
        }

        if (count($result) <= 0) {
            return 0;
        }

        return reset($result);
    }

    /**
     * @throws Exception
     */
    private function countMessages(int $typeId, int $itemId): int
    {
        $select   = $this->messageTable->getSql()->select()
            ->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'item_id' => $itemId,
                'type_id' => $typeId,
            ]);
        $countRow = currentFromResultSetInterface($this->messageTable->selectWith($select));

        return (int) $countRow['count'];
    }

    /**
     * @throws Exception
     */
    public function getMessagesCount(int $typeId, int $itemId): int
    {
        $select = $this->topicTable->getSql()->select()
            ->columns(['messages'])
            ->where([
                'type_id' => $typeId,
                'item_id' => $itemId,
            ]);

        $row = currentFromResultSetInterface($this->topicTable->selectWith($select));

        return $row ? (int) $row['messages'] : 0;
    }

    /**
     * @throws Exception
     */
    private function getLastUpdate(int $typeId, int $itemId): ?string
    {
        $select = $this->messageTable->getSql()->select()
            ->columns(['datetime'])
            ->where([
                'item_id' => $itemId,
                'type_id' => $typeId,
            ])
            ->order('datetime desc')
            ->limit(1);
        $row    = currentFromResultSetInterface($this->messageTable->selectWith($select));

        return $row ? $row['datetime'] : null;
    }

    public function getMessagesPaginator(array $options = []): Paginator\Paginator
    {
        $select = $this->getMessagesSelect($options);

        /** @var Adapter $adapter */
        $adapter = $this->messageTable->getAdapter();

        return new Paginator\Paginator(
            new DbSelect($select, $adapter)
        );
    }

    public function getMessagesSelect(array $options = []): Sql\Select
    {
        $defaults = [
            'attention'       => null,
            'item_id'         => null,
            'type'            => null,
            'user'            => null,
            'exclude_type'    => null,
            'exclude_deleted' => false,
            'callback'        => null,
            'order'           => null,
            'parent_id'       => null,
            'no_parents'      => null,
        ];
        $options  = array_replace($defaults, $options);

        $select = $this->messageTable->getSql()->select();

        if (isset($options['attention'])) {
            $select->where([
                'comment_message.moderator_attention' => $options['attention'],
            ]);
        }

        if (isset($options['item_id'])) {
            $select->where([
                'comment_message.item_id' => $options['item_id'],
            ]);
        }

        if (isset($options['type'])) {
            $select->where([
                'comment_message.type_id' => $options['type'],
            ]);
        }

        if ($options['user']) {
            $select->where([
                'comment_message.author_id' => (int) $options['user'],
            ]);
        }

        if (isset($options['exclude_type'])) {
            $select->where([
                'comment_message.type_id <> ?' => $options['exclude_type'],
            ]);
        }

        if ($options['parent_id']) {
            $select->where(['comment_message.parent_id' => $options['parent_id']]);
        }

        if ($options['no_parents']) {
            $select->where(['comment_message.parent_id IS NULL']);
        }

        if ($options['exclude_deleted']) {
            $select->where(['not comment_message.deleted']);
        }

        if (isset($options['order'])) {
            $select->order($options['order']);
        }

        if ($options['callback']) {
            $options['callback']($select);
        }

        return $select;
    }

    public function getTotalMessagesCount(array $options = []): int
    {
        $paginator = $this->getMessagesPaginator($options);

        return $paginator->getTotalItemCount();
    }

    private function deleteRecursive(int $typeId, int $itemId, int $parentId): void
    {
        $filter = [
            'type_id' => $typeId,
            'item_id' => $itemId,
        ];

        if ($parentId) {
            $filter['parent_id'] = $parentId;
        } else {
            $filter[] = 'parent_id is null';
        }

        $select = $this->messageTable->getSql()->select()->where($filter);

        foreach ($this->messageTable->selectWith($select) as $row) {
            $this->deleteRecursive($typeId, $itemId, $row['id']);

            $this->messageTable->delete($filter);
        }
    }

    public function deleteTopic(int $typeId, int $itemId): void
    {
        $this->deleteRecursive($typeId, $itemId, 0);

        $this->topicTable->delete([
            'type_id' => $typeId,
            'item_id' => $itemId,
        ]);

        $this->topicViewTable->delete([
            'type_id' => $typeId,
            'item_id' => $itemId,
        ]);

        $this->topicSubscribeTable->delete([
            'type_id' => $typeId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * @throws Exception
     */
    public function userSubscribed(int $typeId, int $itemId, int $userId): bool
    {
        return (bool) currentFromResultSetInterface($this->topicSubscribeTable->select([
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
        ]));
    }

    /**
     * @throws Exception
     */
    public function canSubscribe(int $typeId, int $itemId, int $userId): bool
    {
        return ! $this->userSubscribed($typeId, $itemId, $userId);
    }

    /**
     * @throws Exception
     */
    public function subscribe(int $typeId, int $itemId, int $userId): void
    {
        if (! $this->canSubscribe($typeId, $itemId, $userId)) {
            throw new Exception('Already subscribed');
        }

        $this->topicSubscribeTable->insert([
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
            'sent'    => 0,
        ]);
    }

    public function getTopAuthors(int $limit): array
    {
        $select = $this->messageTable->getSql()->select()
            ->columns(['author_id', 'volume' => new Sql\Expression('sum(vote)')])
            ->group('author_id')
            ->order('volume DESC')
            ->limit($limit);

        $result = [];
        foreach ($this->messageTable->selectWith($select) as $row) {
            $result[(int) $row['author_id']] = (int) $row['volume'];
        }

        return $result;
    }
}
