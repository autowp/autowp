<?php

namespace Application\Model;

use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Comment\Topic as CommentTopic;
use Application\Model\DbTable\Comment\Vote as CommentVote;
use Application\Model\DbTable\User;
use Application\Model\DbTable\User\Row as UserRow;

use Zend_Db_Expr;

class Comments
{
    /**
     * @var CommentMessage
     */
    private $messageTable;

    /**
     * @var CommentVote
     */
    private $voteTable;

    /**
     * @return CommentMessage
     */
    private function getMessageTable()
    {
        return $this->messageTable
            ? $this->messageTable
            : $this->messageTable = new CommentMessage();
    }

    /**
     * @return CommentVote
     */
    private function getVoteTable()
    {
        return $this->voteTable
            ? $this->voteTable
            : $this->voteTable = new CommentVote();
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
                ? CommentMessage::MODERATOR_ATTENTION_REQUIRED
                : CommentMessage::MODERATOR_ATTENTION_NONE
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

        $commentTopicTable = new CommentTopic();
        $commentTopicTable->updateTopicStat($typeId, $itemId);
        $commentTopicTable->updateTopicView($typeId, $itemId, $authorId);

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
            $author = $row->findParentRow(User::class, 'Author');

            $vote = null;
            if ($userId) {
                $voteRow = $this->getVoteTable()->fetchRow([
                    'comment_id = ?' => $row->id,
                    'user_id = ?'    => (int)$userId
                ]);
                $vote = $voteRow ? $voteRow->vote : null;
            }

            $deletedBy = null;
            if ($row->deleted) {
                $deletedBy = $row->findParentRow(User::class, 'DeletedBy');
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
    public function updateTopicView($type, $item, $userId)
    {
        $commentTopicTable = new CommentTopic();
        $commentTopicTable->updateTopicView($type, $item, $userId);
    }

    /**
     * @param int $id
     * @return boolean
     */
    public function deleteMessage($id, $userId)
    {
        $comment = $this->getMessageTable()->find($id)->current();

        if ($comment->moderator_attention == CommentMessage::MODERATOR_ATTENTION_REQUIRED) {
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
        $comment = $this->getMessageTable()->find($id)->current();
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
            'moderator_attention = ?' => CommentMessage::MODERATOR_ATTENTION_REQUIRED
        ]);

        if ($comment) {
            $comment->moderator_attention = CommentMessage::MODERATOR_ATTENTION_COMPLETED;
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
        $message = $this->getMessageTable()->find($id)->current();
        if (! $message) {
            return [
                'success' => false,
                'error'   => 'Message not found'
            ];
        }

        if ($message->author_id == $userId) {
            return [
                'success' => false,
                'error'   => 'Self-vote forbidden',
            ];
        }

        $voteTable = $this->getVoteTable();
        $voteRow = $voteTable->fetchRow([
            'comment_id = ?' => $message->id,
            'user_id = ?'    => $userId
        ]);

        $vote = (int)$vote > 0 ? 1 : -1;

        if (! $voteRow) {
            $voteRow = $voteTable->createRow([
                'comment_id' => $message->id,
                'user_id'    => $userId,
                'vote'       => 0
            ]);
        }

        if ($voteRow->vote == $vote) {
            return [
                'success' => false,
                'error'   => 'Alreay voted'
            ];
        }

        $voteRow->vote = $vote;
        $voteRow->save();

        $message->updateVote();

        return [
            'success' => true,
            'vote'    => $message->vote
        ];
    }

    /**
     * @param int $id
     * @return array
     */
    public function getVotes($id)
    {
        $message = $this->getMessageTable()->find($id)->current();
        if (! $message) {
            return false;
        }

        $voteTable = $this->getVoteTable();
        $voteRows = $voteTable->fetchAll([
            'comment_id = ?' => $message->id,
        ]);

        $positiveVotes = $negativeVotes = [];
        foreach ($voteRows as $voteRow) {
            if ($voteRow->vote > 0) {
                $positiveVotes[] = $voteRow->findParentRow(User::class);
            } elseif ($voteRow->vote < 0) {
                $negativeVotes[] = $voteRow->findParentRow(User::class);
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
            'moderator_attention = ?' => CommentMessage::MODERATOR_ATTENTION_REQUIRED
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

        $commentTopicTable = new CommentTopic();
        $commentTopicTable->updateTopicStat($oldTypeId, $oldItemId);
        $commentTopicTable->updateTopicStat($newTypeId, $newItemId);

        return true;
    }
}
