<?php

namespace Autowp\Comments;

use ArrayObject;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Exception;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

class CommentsService
{
    private const DELETE_TTL_DAYS = 300;

    public const MAX_MESSAGE_LENGTH = 16 * 1024;

    /**
     * @var TableGateway
     */
    private $voteTable;

    /**
     * @var TableGateway
     */
    private $topicTable;

    /**
     * @var TableGateway
     */
    private $topicViewTable;

    /**
     * @var TableGateway
     */
    private $messageTable;

    /**
     * @var TableGateway
     */
    private $topicSubscribeTable;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        TableGateway $voteTable,
        TableGateway $topicTable,
        TableGateway $messageTable,
        TableGateway $topicViewTable,
        TableGateway $topicSubscribeTable,
        User $userModel
    ) {
        $this->voteTable = $voteTable;
        $this->topicTable = $topicTable;
        $this->messageTable = $messageTable;
        $this->topicViewTable = $topicViewTable;
        $this->topicSubscribeTable = $topicSubscribeTable;
        $this->userModel = $userModel;
    }

    /**
     * @suppress PhanDeprecatedFunction
     *
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        $typeId = (int)$data['typeId'];
        $itemId = (int)$data['itemId'];
        $authorId = (int)$data['authorId'];
        $parentId = isset($data['parentId']) && $data['parentId'] ? (int)$data['parentId'] : null;

        $parentMessage = null;
        if ($parentId) {
            $parentMessage = $this->messageTable->select([
                'type_id = ?' => $typeId,
                'item_id = ?' => $itemId,
                'id = ?'      => $parentId
            ])->current();
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
            'message'             => (string)$data['message'],
            'ip'                  => new Sql\Expression('INET6_ATON(?)', [$data['ip']]),
            'moderator_attention' => $data['moderatorAttention']
                ? Attention::REQUIRED
                : Attention::NONE
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
     * @suppress PhanDeprecatedFunction
     * @param int $messageId
     */
    private function updateMessageRepliesCount(int $messageId): void
    {
        $row = $this->messageTable->select(function (Sql\Select $select) use ($messageId) {
            $select
                ->columns(['count' => new Sql\Expression('count(1)')])
                ->where(['parent_id' => $messageId]);
        })->current();

        $this->messageTable->update([
            'replies_count' => $row['count']
        ], [
            'id' => $messageId
        ]);
    }

    public function getPaginator(int $type, int $item, int $perPage = 0, int $page = 0): Paginator\Paginator
    {
        return $this->getMessagePaginator($type, $item)
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($page);
    }

    /**
     * @param int $type
     * @param int $item
     * @param int $parentId
     * @param int $userId
     * @param int $perPage
     * @param int $page
     * @return array
     * @throws Exception
     */
    private function getRecursive(
        int $type,
        int $item,
        int $parentId,
        int $userId,
        int $perPage = 0,
        int $page = 0
    ): array {
        if ($perPage) {
            $paginator = $this->getPaginator($type, $item, $perPage, $page);

            $rows = $paginator->getCurrentItems();
        } else {
            $filter = [
                'type_id = ?' => $type,
                'item_id = ?' => $item,
            ];
            if ($parentId) {
                $filter['parent_id = ?'] = $parentId;
            } else {
                $filter[] = 'parent_id is null';
            }
            $rows = $this->messageTable->select($filter);
        }

        $comments = [];
        foreach ($rows as $row) {
            $author = $this->userModel->getRow(['id' => (int)$row['author_id']]);

            $vote = null;
            if ($userId) {
                $voteRow = $this->voteTable->select([
                    'comment_id = ?' => $row['id'],
                    'user_id = ?'    => (int)$userId
                ])->current();
                $vote = $voteRow ? $voteRow['vote'] : null;
            }

            $deletedBy = null;
            if ($row['deleted']) {
                $deletedBy = $this->userModel->getRow(['id' => (int)$row['deleted_by']]);
            }

            if ($row['replies_count'] > 0) {
                $submessages = $this->getRecursive($type, $item, $row['id'], $userId);
            } else {
                $submessages = [];
            }

            $comments[] = [
                'id'                  => $row['id'],
                'author'              => $author,
                'message'             => $row['message'],
                'datetime'            => Row::getDateTimeByColumnType('timestamp', $row['datetime']),
                'ip'                  => $row['ip'] ? inet_ntop($row['ip']) : null,
                'vote'                => $row['vote'],
                'moderator_attention' => $row['moderator_attention'],
                'userVote'            => $vote,
                'deleted'             => $row['deleted'],
                'deletedBy'           => $deletedBy,
                'messages'            => $submessages
            ];
        }

        return $comments;
    }

    /**
     * @param int $type
     * @param int $item
     * @param int $userId
     * @param int $perPage
     * @param int $page
     * @return array
     * @throws Exception
     */
    public function get($type, $item, int $userId, int $perPage = 0, int $page = 0)
    {
        return $this->getRecursive($type, $item, 0, $userId, $perPage, $page);
    }

    /**
     * @param int $typeId
     * @param int $itemId
     * @param int $userId
     */
    public function updateTopicView(int $typeId, int $itemId, int $userId): void
    {
        $sql = '
            insert into comment_topic_view (user_id, type_id, item_id, `timestamp`)
            values (?, ?, ?, NOW())
            on duplicate key update `timestamp` = values(`timestamp`)
        ';
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $statement = $this->topicTable->getAdapter()->query($sql);
        $statement->execute([$userId, $typeId, $itemId]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function queueDeleteMessage(int $id, int $userId): bool
    {
        $comment = $this->getMessageRow($id);

        if ($comment['moderator_attention'] == Attention::REQUIRED) {
            return false;
        }

        if (! $comment) {
            return false;
        }

        $this->messageTable->update([
            'deleted'     => 1,
            'deleted_by'  => $userId,
            'delete_date' => new Sql\Expression('NOW()')
        ], [
            'id = ?' => $comment['id']
        ]);

        return true;
    }

    /**
     * @param int $id
     */
    public function restoreMessage(int $id): void
    {
        $comment = $this->getMessageRow($id);
        if ($comment) {
            $this->messageTable->update([
                'deleted'     => 0,
                'delete_date' => null
            ], [
                'id = ?' => $comment['id']
            ]);
        }
    }

    /**
     * @param int $id
     */
    public function completeMessage(int $id): void
    {
        $this->messageTable->update([
            'moderator_attention' => Attention::COMPLETED
        ], [
            'id = ?'                  => $id,
            'moderator_attention = ?' => Attention::REQUIRED
        ]);
    }

    public function voteMessage(int $id, int $userId, int $vote): array
    {
        $message = $this->getMessageRow($id);
        if (! $message) {
            return [
                'success' => false,
                'error'   => 'Message not found'
            ];
        }

        if ($message['author_id'] == $userId) {
            return [
                'success' => false,
                'error'   => 'Self-vote forbidden',
            ];
        }

        $result = $this->voteTable->getAdapter()->query('
            INSERT INTO comment_vote (comment_id, user_id, vote)
            VALUES (:comment_id, :user_id, :vote)
            ON DUPLICATE KEY UPDATE vote = VALUES(vote)
        ', [
            'comment_id' => $message['id'],
            'user_id'    => $userId,
            'vote'       => $vote > 0 ? 1 : -1,
        ]);

        if ($result->getAffectedRows() == 0) {
            return [
                'success' => false,
                'error'   => 'Already voted'
            ];
        }

        $newVote = $this->updateVote((int) $message['id']);

        return [
            'success' => true,
            'vote'    => $newVote
        ];
    }

    /**
     * @suppress PhanDeprecatedFunction
     *
     * @param int $messageId
     * @return int
     */
    private function updateVote(int $messageId): int
    {
        $row = $this->voteTable->select(function (Sql\Select $select) use ($messageId) {
            $select
                ->columns(['count' => new Sql\Expression('sum(vote)')])
                ->where(['comment_id' => $messageId]);
        })->current();

        $this->messageTable->update([
            'vote' => $row['count']
        ], [
            'id = ?' => $messageId
        ]);

        return (int) $row['count'];
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function getVotes(int $id): ?array
    {
        $message = $this->getMessageRow($id);
        if (! $message) {
            return null;
        }

        $voteRows = $this->voteTable->select([
            'comment_id' => $message['id']
        ]);

        $positiveVotes = $negativeVotes = [];
        foreach ($voteRows as $voteRow) {
            $user = $this->userModel->getRow(['id' => (int)$voteRow['user_id']]);
            if ($voteRow['vote'] > 0) {
                $positiveVotes[] = $user;
            } elseif ($voteRow['vote'] < 0) {
                $negativeVotes[] = $user;
            }
        }

        return [
            'positiveVotes' => $positiveVotes,
            'negativeVotes' => $negativeVotes
        ];
    }

    /**
     * @param int $srcTypeId
     * @param int $srcItemId
     * @param int $dstTypeId
     * @param int $dstItemId
     */
    public function moveMessages(int $srcTypeId, int $srcItemId, int $dstTypeId, int $dstItemId): void
    {
        $this->messageTable->update([
            'type_id' => $dstTypeId,
            'item_id' => $dstItemId
        ], [
            'type_id' => $srcTypeId,
            'item_id' => $srcItemId
        ]);

        $this->updateTopicStat($srcTypeId, $srcItemId);
        $this->updateTopicStat($dstTypeId, $dstItemId);
    }

    /**
     * @param int $type
     * @param int $item
     * @return array|ArrayObject|null
     */
    public function getLastMessageRow(int $type, int $item)
    {
        return $this->messageTable->select(function (Sql\Select $select) use ($type, $item) {
            $select
                ->where([
                    'type_id' => $type,
                    'item_id' => $item
                ])
                ->order('datetime DESC')
                ->limit(1);
        })->current();
    }

    /**
     * @param int $type
     * @param int $item
     * @return bool
     */
    public function topicHaveModeratorAttention(int $type, int $item): bool
    {
        return (bool)$this->messageTable->select([
            'item_id'             => $item,
            'type_id'             => $type,
            'moderator_attention' => Attention::REQUIRED
        ])->current();
    }

    /**
     * @param int $id
     * @return array|ArrayObject
     */
    public function getMessageRow(int $id)
    {
        return $this->messageTable->select([
            'id = ?' => (int)$id
        ])->current();
    }

    /**
     * @param array $message
     * @return array|ArrayObject|null
     */
    private function getMessageRoot($message)
    {
        $root = $message;

        while ($root['parent_id']) {
            $root = $this->messageTable->select([
                'item_id = ?' => $root['item_id'],
                'type_id = ?' => $root['type_id'],
                'id = ?'      => $root['parent_id']
            ])->current();
        }

        return $root;
    }

    /**
     * @suppress PhanDeprecatedFunction
     *
     * @param array $message
     * @param int $perPage
     * @return int
     */
    public function getMessagePage($message, int $perPage): int
    {
        $root = $this->getMessageRoot($message);

        $row = $this->messageTable->select(
            /**
             * @suppress PhanPluginMixedKeyNoKey
             */
            function (Sql\Select $select) use ($root) {
                $select
                    ->columns(['count' => new Sql\Expression('COUNT(1)')])
                    ->where([
                        'item_id = ?'  => $root['item_id'],
                        'type_id = ?'  => $root['type_id'],
                        'datetime < ?' => $root['datetime'],
                        'parent_id is null'
                    ]);
            }
        )->current();

        return ceil(($row['count'] + 1) / $perPage);
    }

    /**
     * @param int $messageId
     * @return int|null
     */
    public function getMessageAuthorId(int $messageId): ?int
    {
        $row = $this->getMessageRow($messageId);
        if ($row) {
            return $row['author_id'] ? $row['author_id'] : null;
        }

        return null;
    }

    public function updateRepliesCount(): int
    {
        $db = $this->messageTable->getAdapter();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $db->query('
            create temporary table __cms
            select type_id, item_id, parent_id as id, count(1) as count
            from comment_message
            where parent_id is not null
            group by type_id, item_id, parent_id
        ', $db::QUERY_MODE_EXECUTE);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $statement = $db->query('
            update comment_message
                inner join __cms
                using(type_id, item_id, id)
            set comment_message.replies_count = __cms.count
        ');
        $result = $statement->execute();

        return $result->getAffectedRows();
    }

    private function moveMessageRecursive(int $parentId, int $newTypeId, int $newItemId): void
    {
        $this->messageTable->update([
            'item_id' => $newItemId,
            'type_id' => $newTypeId
        ], [
            'parent_id' => $parentId
        ]);

        $rows = $this->messageTable->select([
            'parent_id' => $parentId
        ]);

        foreach ($rows as $row) {
            $this->moveMessageRecursive($row['id'], $newTypeId, $newItemId);
        }
    }

    public function moveMessage(int $id, int $newTypeId, int $newItemId): bool
    {
        $messageRow = $this->getMessageRow($id);
        if (! $messageRow) {
            return false;
        }

        $oldTypeId = $messageRow['type_id'];
        $oldItemId = $messageRow['item_id'];

        if ($oldItemId == $newItemId && $oldTypeId == $newTypeId) {
            return false;
        }

        $this->messageTable->update([
            'item_id'   => $newItemId,
            'type_id'   => $newTypeId,
            'parent_id' => null
        ], [
            'id = ?' => $messageRow['id']
        ]);

        $this->moveMessageRecursive($messageRow['id'], $newTypeId, $newItemId);

        $this->updateTopicStat($oldTypeId, $oldItemId);
        $this->updateTopicStat($newTypeId, $newItemId);

        return true;
    }

    public function isNewMessage($messageRow, int $userId): bool
    {
        $row = $this->topicViewTable->select(function (Sql\Select $select) use ($messageRow, $userId) {
            $select
                ->columns(['timestamp'])
                ->where([
                    'item_id' => $messageRow['item_id'],
                    'type_id' => $messageRow['type_id'],
                    'user_id' => $userId
                ]);
        })->current();

        return $row ? $messageRow['datetime'] > $row['timestamp'] : true;
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array
     */
    public function getTopicStatForUser(int $typeId, $itemId, int $userId)
    {
        $isArrayRequest = is_array($itemId);

        $itemId = (array)$itemId;

        $rows = [];
        if (count($itemId) > 0) {
            $rows = $this->topicTable->select(
                /**
                 * @suppress PhanPluginMixedKeyNoKey
                 */
                function (Sql\Select $select) use ($typeId, $itemId, $userId) {
                    $select
                        ->columns(['item_id', 'messages'])
                        ->where([
                            'comment_topic.type_id' => $typeId,
                            new Sql\Predicate\In('comment_topic.item_id', $itemId)
                        ])
                        ->join(
                            'comment_topic_view',
                            new Sql\Predicate\PredicateSet([
                                new Sql\Predicate\Expression('comment_topic.type_id = comment_topic_view.type_id'),
                                new Sql\Predicate\Expression('comment_topic.item_id = comment_topic_view.item_id'),
                                new Sql\Predicate\Operator(
                                    'comment_topic_view.user_id',
                                    Sql\Predicate\Operator::OP_EQ,
                                    $userId
                                )
                            ]),
                            ['timestamp'],
                            $select::JOIN_LEFT
                        );
                }
            );
        }

        $result = [];
        foreach ($rows as $row) {
            $id = $row['item_id'];
            $viewTime = $row['timestamp'];
            $messages = (int)$row['messages'];

            if (! $viewTime) {
                $newMessages = $messages;
            } else {
                $newMessages = $this->getMessagesCountFromTimestamp($typeId, $id, $viewTime);
            }

            $result[$id] = [
                'messages'    => $messages,
                'newMessages' => $newMessages
            ];
        }

        if ($isArrayRequest) {
            return $result;
        }

        if (count($result) <= 0) {
            return [
                'messages'    => 0,
                'newMessages' => 0
            ];
        }

        return reset($result);
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @return array
     */
    public function getTopicStat(int $typeId, $itemId)
    {
        $isArrayRequest = is_array($itemId);
        $itemId = (array)$itemId;

        $result = [];

        if (count($itemId) > 0) {
            $rows = $this->topicTable->select(
                /**
                 * @suppress PhanPluginMixedKeyNoKey
                 */
                function (Sql\Select $select) use ($typeId, $itemId) {
                    $select
                        ->columns(['item_id', 'messages'])
                        ->where([
                            'comment_topic.type_id' => $typeId,
                            new Sql\Predicate\In('comment_topic.item_id', $itemId)
                        ]);
                }
            );

            foreach ($rows as $row) {
                $result[$row['item_id']] = [
                    'messages' => (int)$row['messages']
                ];
            }
        }

        if ($isArrayRequest) {
            return $result;
        }

        if (count($result) <= 0) {
            return [
                'messages' => 0
            ];
        }

        return reset($result);
    }

    /**
     * @param int $typeId
     * @param int $itemId
     */
    private function updateTopicStat($typeId, $itemId): void
    {
        $messagesCount = $this->countMessages($typeId, $itemId);

        if ($messagesCount <= 0) {
            $this->topicTable->delete([
                'item_id = ?' => $itemId,
                'type_id = ?' => $typeId
            ]);
            return;
        }

        $lastUpdate = $this->getLastUpdate($typeId, $itemId);

        $sql = '
            INSERT INTO comment_topic (item_id, type_id, last_update, messages)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_update=VALUES(last_update), messages=VALUES(messages)
        ';
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $statement = $this->topicTable->getAdapter()->query($sql);
        $statement->execute([$itemId, $typeId, $lastUpdate, $messagesCount]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param int $typeId
     * @param int $itemId
     * @param string $timestamp
     * @return int
     */
    private function getMessagesCountFromTimestamp(int $typeId, int $itemId, string $timestamp): int
    {
        $countRow = $this->messageTable->select(function (Sql\Select $select) use ($itemId, $typeId, $timestamp) {
            $select
                ->columns(['count' => new Sql\Expression('count(1)')])
                ->where([
                    'item_id = ?'  => $itemId,
                    'type_id = ?'  => $typeId,
                    'datetime > ?' => $timestamp
                ]);
        })->current();

        return (int)$countRow['count'];
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array|int
     */
    public function getNewMessages(int $typeId, $itemId, int $userId)
    {
        $isArrayRequest = is_array($itemId);
        $itemId = (array)$itemId;

        $rows = [];
        if (count($itemId) > 0) {
            $rows = $this->topicViewTable->select(
                /**
                 * @suppress PhanPluginMixedKeyNoKey
                 */
                function (Sql\Select $select) use ($typeId, $userId, $itemId) {
                    $select
                        ->columns(['item_id', 'timestamp'])
                        ->where([
                            'type_id' => $typeId,
                            'user_id' => $userId,
                            new Sql\Predicate\In('item_id', $itemId)
                        ]);
                }
            );
        }

        $result = [];
        foreach ($rows as $row) {
            if ($row['timestamp']) {
                $id = $row['item_id'];
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
     * @suppress PhanDeprecatedFunction
     * @param int $typeId
     * @param int $itemId
     * @return int
     */
    private function countMessages(int $typeId, int $itemId): int
    {
        $countRow = $this->messageTable->select(function (Sql\Select $select) use ($itemId, $typeId) {
            $select
                ->columns(['count' => new Sql\Expression('count(1)')])
                ->where([
                    'item_id' => $itemId,
                    'type_id' => $typeId
                ]);
        })->current();

        return (int) $countRow['count'];
    }

    public function getMessagesCount(int $typeId, int $itemId): int
    {
        $row = $this->topicTable->select(function (Sql\Select $select) use ($typeId, $itemId) {
            $select
                ->columns(['messages'])
                ->where([
                    'type_id' => $typeId,
                    'item_id' => $itemId
                ]);
        })->current();

        return $row ? (int)$row['messages'] : 0;
    }

    public function getMessagesCounts(int $typeId, array $itemIds): array
    {
        $rows = $this->topicTable->select(
            /**
             * @suppress PhanPluginMixedKeyNoKey
             */
            function (Sql\Select $select) use ($typeId, $itemIds) {
                $select
                    ->columns(['item_id', 'messages'])
                    ->where([
                        'type_id' => $typeId,
                        new Sql\Predicate\In('item_id', $itemIds)
                    ]);
            }
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['item_id']] = $row['messages'];
        }

        return $result;
    }

    private function getLastUpdate(int $typeId, int $itemId)
    {
        $row = $this->messageTable->select(function (Sql\Select $select) use ($itemId, $typeId) {
            $select
                ->columns(['datetime'])
                ->where([
                    'item_id' => $itemId,
                    'type_id' => $typeId
                ])
                ->order('datetime desc')
                ->limit(1);
        })->current();

        return $row ? $row['datetime'] : null;
    }

    /**
     * @param array $options
     * @return Paginator\Paginator
     */
    public function getMessagesPaginator(array $options = []): Paginator\Paginator
    {
        $select = $this->getMessagesSelect($options);

        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $select,
                $this->messageTable->getAdapter()
            )
        );
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     *
     * @param int $type
     * @param int $item
     * @return Paginator\Paginator
     */
    public function getMessagePaginator(int $type, int $item): Paginator\Paginator
    {
        $select = new Sql\Select($this->messageTable->getTable());

        $select
            ->where([
                'item_id' => $item,
                'type_id' => $type,
                'parent_id is null'
            ])
            ->order('datetime');

        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $select,
                $this->messageTable->getAdapter()
            )
        );
    }

    /**
     * @param array $options
     * @return Sql\Select
     */
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
        $options = array_replace($defaults, $options);

        $select = new Sql\Select($this->messageTable->getTable());

        if (isset($options['attention'])) {
            $select->where([
                'comment_message.moderator_attention = ?' => $options['attention']
            ]);
        }

        if (isset($options['item_id'])) {
            $select->where([
                'comment_message.item_id = ?' => $options['item_id']
            ]);
        }

        if (isset($options['type'])) {
            $select->where([
                'comment_message.type_id = ?' => $options['type']
            ]);
        }

        if ($options['user']) {
            $select->where([
                'comment_message.author_id = ?' => (int)$options['user']
            ]);
        }

        if (isset($options['exclude_type'])) {
            $select->where([
                'comment_message.type_id <> ?' => $options['exclude_type']
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

    private function deleteRecursive($typeId, $itemId, $parentId): void
    {
        $filter = [
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ];

        if ($parentId) {
            $filter['parent_id = ?'] = $parentId;
        } else {
            $filter[] = 'parent_id is null';
        }

        $select = new Sql\Select($this->messageTable->getTable());
        $select->where($filter);

        foreach ($this->messageTable->selectWith($select) as $row) {
            $this->deleteRecursive($typeId, $itemId, $row['id']);

            $this->messageTable->delete($filter);
        }
    }

    public function deleteTopic($typeId, $itemId): void
    {
        $this->deleteRecursive($typeId, $itemId, null);

        $this->topicTable->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);

        $this->topicViewTable->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);

        $this->topicSubscribeTable->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param int $userId
     * @return int|mixed
     */
    public function getUserAvgVote(int $userId)
    {
        $row = $this->messageTable->select(
            /**
             * @suppress PhanPluginMixedKeyNoKey
             */
            function (Sql\Select $select) use ($userId) {
                $select
                    ->columns(['avg_vote' => new Sql\Expression('avg(vote)')])
                    ->where([
                        'author_id = ?' => (int)$userId,
                        'vote <> 0'
                    ]);
            }
        )->current();

        return $row ? $row['avg_vote'] : 0;
    }

    public function cleanupDeleted(): int
    {
        $subSelect = new Sql\Select($this->messageTable->getTable());
        $subSelect
            ->quantifier(Sql\Select::QUANTIFIER_DISTINCT)
            ->columns(['parent_id'])
            ->where(['parent_id']);

        $select = new Sql\Select($this->messageTable->getTable());
        $select->columns(['id', 'type_id', 'item_id'])
            ->where([
            new Sql\Predicate\NotIn('id', $subSelect),
            'deleted',
            new Sql\Predicate\Expression('delete_date < DATE_SUB(NOW(), INTERVAL ? DAY)', [self::DELETE_TTL_DAYS])
            ]);

        $rows = $this->messageTable->selectWith($select);

        $affected = 0;
        foreach ($rows as $row) {
            $affected += $this->messageTable->delete([
                'id'      => $row['id'],
                'type_id' => $row['type_id'],
                'item_id' => $row['item_id'],
            ]);

            $this->updateTopicStat($row['type_id'], $row['item_id']);
        }

        return $affected;
    }

    public function getList(array $options): array
    {
        $defaults = [
            'type'     => null,
            'callback' => null
        ];
        $options = array_replace($defaults, $options);

        $select = new Sql\Select($this->messageTable->getTable());

        $select->order('datetime');

        if ($options['type']) {
            $select->where(['type_id = ?' => (int)$options['type']]);
        }

        if ($options['callback']) {
            $options['callback']($select);
        }

        $items = [];

        foreach ($this->messageTable->selectWith($select) as $row) {
            $items[] = [
                'id'      => $row['id'],
                'item_id' => $row['item_id'],
                'type_id' => $row['type_id']
            ];
        }

        return $items;
    }

    public function deleteMessage($id): int
    {
        $message = $this->getMessageRow($id);

        $affected = $this->messageTable->delete([
            'id' => $message['id']
        ]);

        $this->updateTopicStat($message['type_id'], $message['item_id']);

        return $affected;
    }

    public function cleanTopics(): int
    {
        $adapter = $this->topicViewTable->getAdapter();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $result = $adapter->query('
            DELETE comment_topic_view
                FROM comment_topic_view
                    LEFT JOIN comment_message on comment_topic_view.item_id=comment_message.item_id
                        and comment_topic_view.type_id=comment_message.type_id
            WHERE comment_message.type_id is null
        ', $adapter::QUERY_MODE_EXECUTE);

        $affected = $result->getAffectedRows();

        $adapter = $this->topicTable->getAdapter();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $result = $adapter->query('
            DELETE comment_topic
                FROM comment_topic
                    LEFT JOIN comment_message on comment_topic.item_id=comment_message.item_id
                        and comment_topic.type_id=comment_message.type_id
            WHERE comment_message.type_id is null
        ', $adapter::QUERY_MODE_EXECUTE);

        $affected += $result->getAffectedRows();

        return $affected;
    }

    public function userSubscribed(int $typeId, int $itemId, int $userId): bool
    {
        return (bool)$this->topicSubscribeTable->select([
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId
        ])->current();
    }

    public function canSubscribe(int $typeId, int $itemId, int $userId): bool
    {
        return ! $this->userSubscribed($typeId, $itemId, $userId);
    }

    public function canUnSubscribe(int $typeId, int $itemId, int $userId): bool
    {
        return $this->userSubscribed($typeId, $itemId, $userId);
    }

    public function subscribe(int $typeId, int $itemId, int $userId): void
    {
        if (! $this->canSubscribe($typeId, $itemId, $userId)) {
            throw new Exception('Already subscribed');
        }

        $this->topicSubscribeTable->insert([
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
            'sent'    => 0
        ]);
    }

    public function unSubscribe(int $typeId, int $itemId, int $userId): void
    {
        if (! $this->canUnSubscribe($typeId, $itemId, $userId)) {
            throw new Exception('User not subscribed');
        }

        $this->topicSubscribeTable->delete([
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
        ]);
    }

    public function getSubscribersIds($typeId, $itemId, $onlyAwaiting = false): array
    {
        $where = [
            'type_id' => (int)$typeId,
            'item_id' => (int)$itemId
        ];

        if ($onlyAwaiting) {
            $where[] = 'NOT sent';
        }

        $select = new Sql\Select($this->topicSubscribeTable->getTable());
        $select
            ->columns(['user_id'])
            ->where($where);

        $ids = [];
        foreach ($this->topicSubscribeTable->selectWith($select) as $row) {
            $ids[] = $row['user_id'];
        }

        return $ids;
    }

    public function markSubscriptionSent(int $typeId, int $itemId, int $userId): void
    {
        $this->topicSubscribeTable->update([
            'sent' => 1
        ], [
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
        ]);
    }

    public function markSubscriptionAwaiting(int $typeId, int $itemId, int $userId): void
    {
        $this->topicSubscribeTable->update([
            'sent' => 0
        ], [
            'type_id' => $typeId,
            'item_id' => $itemId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param int $limit
     * @return array
     */
    public function getTopAuthors(int $limit): array
    {
        $select = $this->messageTable->getSql()->select()
            ->columns(['author_id', 'volume' => new Sql\Expression('sum(vote)')])
            ->group('author_id')
            ->order('volume DESC')
            ->limit($limit);

        $result = [];
        foreach ($this->messageTable->selectWith($select) as $row) {
            $result[(int)$row['author_id']] = (int)$row['volume'];
        }

        return $result;
    }
}
