<?php

namespace Autowp\Comments;

use Autowp\Commons\Db\Table;
use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;
use Autowp\User\Model\DbTable\User\Row as UserRow;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator\Paginator;

use Zend_Db_Expr;

class CommentsService
{
    /**
     * @var Adapter
     */
    private $adapter;
    
    /**
     * @var Model\DbTable\Message
     */
    private $messageTable;

    /**
     * @var TableGateway
     */
    private $voteTable;
    
    /**
     * @var TableGateway
     */
    private $topicTable;

    /**
     * @var User
     */
    private $userTable;
    
    /**
     * @var TableGateway
     */
    private $topicViewTable;
    
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        
        $this->voteTable = new TableGateway('comment_vote', $this->adapter);
        $this->topicTable = new TableGateway('comment_topic', $this->adapter);
        $this->messageTable2 = new TableGateway('comments_messages', $this->adapter);
        $this->topicViewTable = new TableGateway('comment_topic_view', $this->adapter);
    }

    /**
     * @return User
     */
    private function getUserTable()
    {
        return $this->userTable
            ? $this->userTable
            : $this->userTable = new User();
    }

    /**
     * @return Model\DbTable\Message
     */
    private function getMessageTable()
    {
        if (! $this->messageTable) {
            $this->messageTable = new Table([
                'name'         => 'comments_messages',
                'primary'      => ['id'],
                'referenceMap' => [
                    'Author' => [
                        'columns'       => ['author_id'],
                        'refTableClass' => \Autowp\User\Model\DbTable\User::class,
                        'refColumns'    => ['id']
                    ],
                    'DeletedBy' => [
                        'columns'       => ['deleted_by'],
                        'refTableClass' => \Autowp\User\Model\DbTable\User::class,
                        'refColumns'    => ['id']
                    ]
                ]
            ]);
        }

        return $this->messageTable;
    }

    /**
     * @param array $data
     * @return int
     */
    public function add(array $data)
    {
        $typeId = (int)$data['typeId'];
        $itemId = (int)$data['itemId'];
        $authorId = (int)$data['authorId'];
        $parentId = isset($data['parentId']) && $data['parentId'] ? (int)$data['parentId'] : null;

        $parentMessage = null;
        if ($parentId) {
            $parentMessage = $this->getMessageTable()->fetchRow([
                'type_id = ?' => $typeId,
                'item_id = ?' => $itemId,
                'id = ?'      => $parentId
            ]);
            if (! $parentMessage) {
                return false;
            }

            if ($parentMessage->deleted) {
                return false;
            }
        }

        $messageTable = $this->getMessageTable();
        $db = $messageTable->getAdapter();

        $data = [
            'datetime'            => new Zend_Db_Expr('NOW()'),
            'type_id'             => $typeId,
            'item_id'             => $itemId,
            'parent_id'           => $parentMessage ? $parentMessage->id : null,
            'author_id'           => $authorId,
            'message'             => (string)$data['message'],
            'ip'                  => new Zend_Db_Expr($db->quoteInto('INET6_ATON(?)', $data['ip'])),
            'moderator_attention' => $data['moderatorAttention']
                ? Attention::REQUIRED
                : Attention::NONE
        ];

        $messageId = $messageTable->insert($data);

        if ($parentMessage) {
            $db = $messageTable->getAdapter();
            $count = $db->fetchOne(
                $db->select()
                    ->from($messageTable->info('name'), 'count(1)')
                    ->where('parent_id = ?', $parentMessage->id)
                    ->where('type_id = ?', $parentMessage->type_id)
                    ->where('item_id = ?', $parentMessage->item_id)
            );
            $parentMessage->replies_count = $count;
            $parentMessage->save();
        }

        $this->updateTopicStat($typeId, $itemId);
        $this->updateTopicView($typeId, $itemId, $authorId);

        return $messageId;
    }

    public function getPaginator($type, $item, $perPage = 0, $page = 0)
    {
        return $this->getMessagePaginator($type, $item)
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($page);
    }

    /**
     * @param int $type
     * @param int $item
     * @param int $parentId
     * @param int $user
     * @param int $perPage
     * @param int $page
     * @return array
     */
    private function getRecursive($type, $item, $parentId, $userId, $perPage = 0, $page = 0)
    {
        if ($userId instanceof UserRow) {
            $userId = $userId->id;
        }

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
            $rows = $this->getMessageTable()->fetchAll($filter, 'datetime');
        }

        $comments = [];
        foreach ($rows as $row) {
            $author = $this->getUserTable()->find($row['author_id'])->current();

            $vote = null;
            if ($userId) {
                
                $voteRow = $this->voteTable->select([
                    'comment_id = ?' => $row->id,
                    'user_id = ?'    => (int)$userId
                ])->current();
                $vote = $voteRow ? $voteRow['vote'] : null;
            }

            $deletedBy = null;
            if ($row->deleted) {
                $deletedBy = $this->getUserTable()->find($row['deleted_by'])->current();
            }

            if ($row->replies_count > 0) {
                $submessages = $this->getRecursive($type, $item, $row->id, $userId);
            } else {
                $submessages = [];
            }

            $comments[] = [
                'id'                  => $row->id,
                'author'              => $author,
                'message'             => $row->message,
                'datetime'            => $row->getDateTime('datetime'),
                'ip'                  => $row->ip ? inet_ntop($row->ip) : null,
                'vote'                => $row->vote,
                'moderator_attention' => $row->moderator_attention,
                'userVote'            => $vote,
                'deleted'             => $row->deleted,
                'deletedBy'           => $deletedBy,
                'messages'            => $submessages
            ];
        }

        return $comments;
    }

    /**
     * @param int $type
     * @param int $item
     * @return array
     */
    public function get($type, $item, $userId, $perPage = 0, $page = 0)
    {
        return $this->getRecursive($type, $item, null, $userId, $perPage, $page);
    }

    /**
     * @param int $type
     * @param int $item
     * @param int $userId
     */
    public function updateTopicView($typeId, $itemId, $userId)
    {
        $sql = '
            insert into comment_topic_view (user_id, type_id, item_id, `timestamp`)
            values (?, ?, ?, NOW())
            on duplicate key update `timestamp` = values(`timestamp`)
        ';
        $statement = $this->topicTable->getAdapter()->query($sql);
        $statement->execute([$userId, $typeId, $itemId]);
    }

    /**
     * @param int $id
     * @return boolean
     */
    public function deleteMessage($id, $userId)
    {
        $comment = $this->getMessageRow($id);

        if ($comment->moderator_attention == Attention::REQUIRED) {
            return false;
        }

        if (! $comment) {
            return false;
        }

        $comment->deleted = 1;
        $comment->deleted_by = $userId;
        $comment->save();

        return true;
    }

    /**
     * @param int $id
     */
    public function restoreMessage($id)
    {
        $comment = $this->getMessageRow($id);
        if ($comment) {
            $comment->deleted = 0;
            $comment->save();
        }
    }

    /**
     * @param int $id
     */
    public function completeMessage($id)
    {
        $comment = $this->getMessageTable()->fetchRow([
            'id = ?'                  => (int)$id,
            'moderator_attention = ?' => Attention::REQUIRED
        ]);

        if ($comment) {
            $comment->moderator_attention = Attention::COMPLETED;
            $comment->save();
        }
    }

    /**
     * @param int $id
     * @param int $userId
     * @param int $vote
     * @return array
     */
    public function voteMessage($id, $userId, $vote)
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

        $voteRow = $this->voteTable->select([
            'comment_id' => $message['id'],
            'user_id'    => $userId
        ])->current();

        $vote = (int)$vote > 0 ? 1 : -1;

        if (! $voteRow) {
            $voteRow = $this->voteTable->insert([
                'comment_id' => $message['id'],
                'user_id'    => $userId,
                'vote'       => $vote
            ]);
        } else {
            if ($voteRow['vote'] == $vote) {
                return [
                    'success' => false,
                    'error'   => 'Alreay voted'
                ];
            }
            
            $this->voteTable->update([
                'vote' => $vote
            ], [
                'comment_id' => $message['id'],
                'user_id'    => $userId
            ]);
        }

        $this->updateVote($message);

        return [
            'success' => true,
            'vote'    => $message['vote']
        ];
    }

    /**
     * @todo Change $message to $messageId
     */
    private function updateVote($message)
    {
        $row = $this->voteTable->select(function(Sql\Select $select) use ($message) {
            $select
                ->columns(['count' => new Sql\Expression('sum(vote)')])
                ->where(['comment_id = ?' => $message['id']]);
        })->current();

        $message->vote = $row['count'];
        $message->save();
    }

    /**
     * @param int $id
     * @return array
     */
    public function getVotes($id)
    {
        $message = $this->getMessageRow($id);
        if (! $message) {
            return false;
        }

        $voteRows = $this->voteTable->select([
            'comment_id' => $message['id']
        ]);

        $positiveVotes = $negativeVotes = [];
        foreach ($voteRows as $voteRow) {
            $user = $this->getUserTable()->find($voteRow['user_id'])->current();
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
    public function moveMessages($srcTypeId, $srcItemId, $dstTypeId, $dstItemId)
    {
        $this->getMessageTable()->update([
            'type_id' => $dstTypeId,
            'item_id' => $dstItemId
        ], [
            'type_id = ?' => $srcTypeId,
            'item_id = ?' => $srcItemId
        ]);

        $this->updateTopicStat($srcTypeId, $srcItemId);
        $this->updateTopicStat($dstTypeId, $dstItemId);
    }

    /**
     * @param int $type
     * @param int $item
     */
    public function getLastMessageRow($type, $item)
    {
        return $this->getMessageTable()->fetchRow([
            'type_id = ?' => (int)$type,
            'item_id = ?' => (int)$item
        ], 'datetime DESC');
    }

    public function getSelectByUser($userId, $order)
    {
        $select = $this->getMessageTable()->select(true)
            ->where('author_id = ?', (int)$userId);

        switch ($order) {
            case 'positive':
                $select->order('vote desc');
                break;
            case 'negative':
                $select->order('vote asc');
                break;
            case 'old':
                $select->order('datetime asc');
                break;
            case 'new':
            default:
                $select->order('datetime desc');
                break;
        }

        return $select;
    }

    /**
     * @param int $type
     * @param int $item
     * @return \Zend\Paginator\Paginator
     */
    public function getMessagePaginator($type, $item)
    {
        $select = $this->getMessageTable()->select(true)
            ->where('item_id = ?', (int)$item)
            ->where('type_id = ?', (int)$type)
            ->where('parent_id is null')
            ->order('datetime');

        return new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
    }

    /**
     * @param int $type
     * @param int $item
     * @return boolean
     */
    public function topicHaveModeratorAttention($type, $item)
    {
        return (bool)$this->getMessageTable()->fetchRow([
            'item_id = ?'             => (int)$item,
            'type_id = ?'             => (int)$type,
            'moderator_attention = ?' => Attention::REQUIRED
        ]);
    }

    /**
     * @param int $id
     * @return Zend_Db_Table_Row
     */
    public function getMessageRow($id)
    {
        return $this->getMessageTable()->fetchRow([
            'id = ?' => (int)$id
        ]);
    }

    /**
     * @param Zend_Db_Table_Row $message
     * @return int
     */
    private function getMessageRoot($message)
    {
        $root = $message;

        $table = $this->getMessageTable();

        while ($root->parent_id) {
            $root = $table->fetchRow([
                'item_id = ?' => $root->item_id,
                'type_id = ?' => $root->type_id,
                'id = ?'      => $root->parent_id
            ]);
        }

        return $root;
    }

    /**
     * @param Zend_Db_Table_Row $message
     * @param int $perPage
     * @return int
     */
    public function getMessagePage($message, $perPage)
    {
        $root = $this->getMessageRoot($message);

        $table = $this->getMessageTable();
        $db = $table->getAdapter();

        $count = $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'COUNT(1)')
                ->where('item_id = ?', $root->item_id)
                ->where('type_id = ?', $root->type_id)
                ->where('datetime < ?', $root->datetime)
                ->where('parent_id is null')
        );
        return ceil(($count + 1) / $perPage);
    }

    /**
     * @param int $messageId
     * @return int|null
     */
    public function getMessageAuthorId($messageId)
    {
        $row = $this->getMessageRow($messageId);
        if ($row) {
            return $row->author_id ? $row->author_id : null;
        }

        return null;
    }

    public function updateRepliesCount()
    {
        $db = $this->getMessageTable()->getAdapter();

        $db->query('
            create temporary table __cms
            select type_id, item_id, parent_id as id, count(1) as count
            from comments_messages
            where parent_id is not null
            group by type_id, item_id, parent_id
        ');

        $affected = $db->query('
            update comments_messages
                inner join __cms
                using(type_id, item_id, id)
            set comments_messages.replies_count = __cms.count
        ');

        return $affected->rowCount();
    }

    private function moveMessageRecursive($parentId, $newTypeId, $newItemId)
    {
        $newTypeId = (int)$newTypeId;
        $newItemId = (int)$newItemId;
        $parentId = (int)$parentId;

        $rows = $this->getMessageTable()->fetchAll([
            'parent_id = ?' => $parentId
        ]);

        foreach ($rows as $row) {
            $row->setFromArray([
                'item_id' => $newItemId,
                'type_id' => $newTypeId
            ]);
            $row->save();

            $this->moveMessageRecursive($row->id, $newTypeId, $newItemId);
        }
    }

    public function moveMessage($id, $newTypeId, $newItemId)
    {
        $messageRow = $this->getMessageRow($id);
        if (! $messageRow) {
            return false;
        }

        $newTypeId = (int)$newTypeId;
        $newItemId = (int)$newItemId;

        if ($messageRow->item_id == $newItemId && $messageRow->type_id == $newTypeId) {
            return false;
        }

        $oldTypeId = $messageRow->type_id;
        $oldItemId = $messageRow->item_id;

        $messageRow->setFromArray([
            'item_id'   => $newItemId,
            'type_id'   => $newTypeId,
            'parent_id' => null
        ]);
        $messageRow->save();

        $this->moveMessageRecursive($messageRow->id, $newTypeId, $newItemId);

        $this->updateTopicStat($oldTypeId, $oldItemId);
        $this->updateTopicStat($newTypeId, $newItemId);

        return true;
    }

    public function isNewMessage($messageRow, $userId)
    {
        $db = $this->getMessageTable()->getAdapter();

        $viewTime = $db->fetchRow(
            $db->select()
                ->from('comment_topic_view', 'timestamp')
                ->where('comment_topic_view.item_id = ?', $messageRow['item_id'])
                ->where('comment_topic_view.type_id = ?', $messageRow['type_id'])
                ->where('comment_topic_view.user_id = ?', $userId)
        );

        return $viewTime ? $messageRow['datetime'] > $viewTime : true;
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array
     */
    public function getTopicStatForUser($typeId, $itemId, $userId)
    {
        $isArrayRequest = is_array($itemId);
        
        $itemId = (array)$itemId;
        
        $rows = [];
        if (count($itemId) > 0) {
            
            $rows = $this->topicTable->select(function(Sql\Select $select) use ($typeId, $itemId, $userId) {
                $select
                    ->columns(['item_id', 'messages'])
                    ->where([
                        'comment_topic.type_id = ?' => $typeId,
                        new Sql\Predicate\In('comment_topic.item_id', $itemId)
                    ])
                    ->join(
                        'comment_topic_view',
                        new Sql\Predicate\PredicateSet([
                            new Sql\Predicate\Expression('comment_topic.type_id = comment_topic_view.type_id'),
                            new Sql\Predicate\Expression('comment_topic.item_id = comment_topic_view.item_id'),
                            new Sql\Predicate\Operator('comment_topic_view.user_id', Sql\Predicate\Operator::OP_EQ, $userId)
                        ]),
                        ['timestamp'],
                        $select::JOIN_LEFT
                    );
            });

        }

        $result = [];
        foreach ($rows as $row) {
            $id = $row['item_id'];
            $viewTime = $row['timestamp'];
            $messages = (int)$row['messages'];

            if (! $viewTime) {
                $newMessages = $messages;
            } else {
                
                $row = $this->messageTable2->select(function(Sql\Select $select) use ($id, $typeId, $viewTime) {
                    $select
                        ->columns(['count' => new Sql\Expression('count(1)')])
                        ->where([
                            'comments_messages.item_id'      => $id,
                            'comments_messages.type_id'      => $typeId,
                            'comments_messages.datetime > ?' => $viewTime
                        ]);
                })->current();
                
                $newMessages = $row['count'];
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
    public function getTopicStat($typeId, $itemId)
    {
        $isArrayRequest = is_array($itemId);
        $itemId = (array)$itemId;
        
        $result = [];

        if (count($itemId) > 0) {
            
            $rows = $this->topicTable->select(function(Sql\Select $select) use ($typeId, $itemId) {
                $select
                    ->columns(['item_id', 'messages'])
                    ->where([
                        'comment_topic.type_id = ?' => $typeId,
                        new Sql\Predicate\In('comment_topic.item_id', $itemId)
                    ]);
            });

            foreach ($rows as $row) {
                $result[$row['item_id']] = [
                    'messages' => $row['messages']
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
    private function updateTopicStat($typeId, $itemId)
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
        $statement = $this->topicTable->getAdapter()->query($sql);
        $statement->execute([$itemId, $typeId, $lastUpdate, $messagesCount]);
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array
     */
    public function getNewMessages($typeId, $itemId, $userId)
    {
        $isArrayRequest = is_array($itemId);
        $itemId = (array)$itemId;

        $rows = [];
        if (count($itemId) > 0) {
            $rows = $this->topicViewTable->select(function(Sql\Select $select) use ($typeId, $userId, $itemId) {
                $select
                    ->columns(['item_id', 'timestamp'])
                    ->where([
                        'comment_topic_view.type_id = ?' => $typeId,
                        'comment_topic_view.user_id = ?' => $userId,
                        new Sql\Predicate\In('comment_topic_view.item_id', $itemId)
                    ]);
            });
        }
        
        $db = $this->getMessageTable()->getAdapter();

        $result = [];
        foreach ($rows as $row) {
            if ($row['timestamp']) {
                $id = $row['item_id'];
                
                $result[$id] = (int)$db->fetchOne(
                    $db->select()
                        ->from('comments_messages', 'count(1)')
                        ->where('comments_messages.item_id = ?', $id)
                        ->where('comments_messages.type_id = ?', $typeId)
                        ->where('comments_messages.datetime > ?', $row['timestamp'])
                );
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

    private function countMessages($typeId, $itemId)
    {
        $table = $this->getMessageTable();
        $db = $table->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'COUNT(1)')
                ->where('item_id = ?', $itemId)
                ->where('type_id = ?', $typeId)
        );
    }

    public function getMessagesCounts($typeId, array $itemIds)
    {
        $rows = $this->topicTable->select(function(Sql\Select $select) use ($typeId, $itemIds) {
            $select
                ->columns(['item_id', 'messages'])
                ->where([
                    'type_id = ?' => $typeId,
                    new Sql\Predicate\In('item_id', $itemIds)
                ]);
        });
        
        $result = [];
        foreach ($rows as $row) {
            $result[$row['item_id']] = $row['messages'];
        }
        
        return $result;
    }

    private function getLastUpdate($typeId, $itemId)
    {
        $table = $this->getMessageTable();
        $db = $table->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'datetime')
                ->where('item_id = ?', $itemId)
                ->where('type_id = ?', $typeId)
                ->order('datetime desc')
                ->limit(1)
        );
    }

    /**
     * @param array $options
     * @return \Zend_Db_Select
     */
    public function getMessagesSelect(array $options = [])
    {
        $defaults = [
            'attention'       => null,
            'type'            => null,
            'user'            => null,
            'exclude_type'    => null,
            'exclude_deleted' => false,
            'callback'        => null,
            'order'           => null
        ];
        $options = array_replace($defaults, $options);

        $table = $this->getMessageTable();
        $db = $table->getAdapter();

        $select = $db->select()
            ->from($table->info('name'));

        if (isset($options['attention'])) {
            $select->where('comments_messages.moderator_attention = ?', $options['attention']);
        }

        if (isset($options['type'])) {
            $select->where('comments_messages.type_id = ?', $options['type']);
        }

        if ($options['user']) {
            $select->where('comments_messages.author_id = ?', (int)$options['user']);
        }

        if (isset($options['exclude_type'])) {
            $select->where('comments_messages.type_id <> ?', $options['exclude_type']);
        }

        if ($options['exclude_deleted']) {
            $select->where('not comments_messages.deleted');
        }

        if (isset($options['order'])) {
            $select->order($options['order']);
        }

        if ($options['callback']) {
            $options['callback']($select);
        }

        return $select;
    }

    public function getTotalMessagesCount(array $options = [])
    {
        $select = $this->getMessagesSelect($options);

        $paginator = new Paginator(
            new Zend1DbSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    public function deleteItemComments($typeId, $itemId)
    {
        $this->getMessageTable()->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);

        $this->topicTable->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);

        $this->topicViewTable->delete([
            'type_id = ?' => (int)$typeId,
            'item_id = ?' => (int)$itemId
        ]);
    }

    public function getUserAvgVote($userId)
    {
        $commentTable = $this->getMessageTable();
        $db = $commentTable->getAdapter();

        return $db->fetchOne(
            $db->select()
                ->from($commentTable->info('name'), new Zend_Db_Expr('avg(vote)'))
                ->where('author_id = ?', (int)$userId)
                ->where('vote <> 0')
        );
    }
}
