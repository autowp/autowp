<?php

class Comments
{
    /**
     * @var Comment_Message
     */
    private $_messageTable;

    /**
     * @var Comment_Vote
     */
    private $_voteTable;

    /**
     * @var Application_Form_Comment
     */
    private $_addForm;

    /**
     * @param array $options
     * @return Application_Form_Comment
     */
    public function getAddForm($options)
    {
        if (!$this->_addForm) {
            $this->_addForm = new Application_Form_Comment($options);
        }
        return $this->_addForm;
    }

    /**
     * @return Comment_Message
     */
    protected function _getMessageTable()
    {
        return $this->_messageTable
            ? $this->_messageTable
            : $this->_messageTable = new Comment_Message();
    }

    /**
     * @return Comment_Vote
     */
    protected function _getVoteTable()
    {
        return $this->_voteTable
            ? $this->_voteTable
            : $this->_voteTable = new Comment_Vote();
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
            $parentMessage = $this->_getMessageTable()->fetchRow(array(
                'type_id = ?' => $typeId,
                'item_id = ?' => $itemId,
                'id = ?'      => $parentId
            ));
            if (!$parentMessage) {
                return false;
            }

            if ($parentMessage->deleted) {
                return false;
            }
        }

        $data = array(
            'datetime'            => new Zend_Db_Expr('NOW()'),
            'type_id'             => $typeId,
            'item_id'             => $itemId,
            'parent_id'           => $parentMessage ? $parentMessage->id : null,
            'author_id'           => $authorId,
            'message'             => (string)$data['message'],
            'ip'                  => inet_pton($data['ip']),
            'moderator_attention' => $data['moderatorAttention']
                ? Comment_Message::MODERATOR_ATTENTION_REQUIRED
                : Comment_Message::MODERATOR_ATTENTION_NONE
        );

        $messageTable = $this->_getMessageTable();
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

        $commentTopicTable = new Comment_Topic();
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
    private function _get($type, $item, $parentId, $user, $perPage = 0, $page = 0)
    {
        if ($perPage) {

            $paginator = $this->getPaginator($type, $item, $perPage, $page);

            $rows = $paginator->getCurrentItems();
        } else {
            $filter = array(
                'type_id = ?' => $type,
                'item_id = ?' => $item,
            );
            if ($parentId) {
                $filter['parent_id = ?'] = $parentId;
            } else {
                $filter[] = 'parent_id is null';
            }
            $rows = $this->_getMessageTable()->fetchAll($filter, 'datetime');
        }

        $comments = array();
        foreach ($rows as $row) {

            $author = $row->findParentUsersByAuthor();

            $vote = null;
            if ($user) {
                $voteRow = $this->_getVoteTable()->fetchRow(array(
                    'comment_id = ?' => $row->id,
                    'user_id = ?'    => $user->id
                ));
                $vote = $voteRow ? $voteRow->vote : null;
            }

            $deletedBy = null;
            if ($row->deleted) {
                $deletedBy = $row->findParentUsersByDeletedBy();
            }

            if ($row->replies_count > 0) {
                $submessages = $this->_get($type, $item, $row->id, $user);
            } else {
                $submessages = array();
            }

            $comments[] = array(
                'id'                  => $row->id,
                'author'              => $author,
                'message'             => $row->message,
                'datetime'            => $row->getDate('datetime'),
                'ip'                  => $row->ip ? inet_ntop($row->ip) : null,
                'vote'                => $row->vote,
                'moderator_attention' => $row->moderator_attention,
                'userVote'            => $vote,
                'deleted'             => $row->deleted,
                'deletedBy'           => $deletedBy,
                'messages'            => $submessages
            );
        }

        return $comments;
    }

    /**
     * @param int $type
     * @param int $item
     * @return array
     */
    public function get($type, $item, $user, $perPage = 0, $page = 0)
    {
        return $this->_get($type, $item, null, $user, $perPage, $page);
    }

    /**
     * @param int $type
     * @param int $item
     * @param int $userId
     */
    public function updateTopicView($type, $item, $userId)
    {
        $commentTopicTable = new Comment_Topic();
        $commentTopicTable->updateTopicView($type, $item, $userId);
    }

    /**
     * @param int $id
     * @return boolean
     */
    public function deleteMessage($id, $userId)
    {
        $comment = $this->_getMessageTable()->find($id)->current();

        if ($comment->moderator_attention == Comment_Message::MODERATOR_ATTENTION_REQUIRED) {
            return false;
        }

        if (!$comment) {
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
        $comment = $this->_getMessageTable()->find($id)->current();
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
        $comment = $this->_getMessageTable()->fetchRow(array(
            'id = ?'                  => (int)$id,
            'moderator_attention = ?' => Comment_Message::MODERATOR_ATTENTION_REQUIRED
        ));

        if ($comment) {
            $comment->moderator_attention = Comment_Message::MODERATOR_ATTENTION_COMPLETED;
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
        $message = $this->_getMessageTable()->find($id)->current();
        if (!$message) {
            return array(
                'success' => false,
                'error'   => 'Сообщение не найдено'
            );
        }

        if ($message->author_id == $userId) {
            return array(
                'success' => false,
                'error'   => 'Нельзя оценивать собственные сообщения',
            );
        }

        $voteTable = $this->_getVoteTable();
        $voteRow = $voteTable->fetchRow(array(
            'comment_id = ?' => $message->id,
            'user_id = ?'    => $userId
        ));

        $vote = (int)$vote > 0 ? 1 : -1;

        if (!$voteRow) {
            $voteRow = $voteTable->createRow(array(
                'comment_id' => $message->id,
                'user_id'    => $userId,
                'vote'       => 0
            ));
        }

        if ($voteRow->vote == $vote) {
            return array(
                'success' => false,
                'error'   => 'Вы уже поставили свою оценку'
            );
        }

        $voteRow->vote = $vote;
        $voteRow->save();

        $message->updateVote();

        return array(
            'success' => true,
            'vote'    => $message->vote
        );
    }

    /**
     * @param int $id
     * @return array
     */
    public function getVotes($id)
    {
        $message = $this->_getMessageTable()->find($id)->current();
        if (!$message) {
            return false;
        }

        $voteTable = $this->_getVoteTable();
        $voteRows = $voteTable->fetchAll(array(
            'comment_id = ?' => $message->id,
        ));

        $positiveVotes = $negativeVotes = array();
        foreach ($voteRows as $voteRow) {
            if ($voteRow->vote > 0) {
                $positiveVotes[] = $voteRow->findParentUsers();
            } elseif ($voteRow->vote < 0) {
                $negativeVotes[] = $voteRow->findParentUsers();
            }
        }

        return array(
            'positiveVotes' => $positiveVotes,
            'negativeVotes' => $negativeVotes
        );
    }

    /**
     * @param int $srcTypeId
     * @param int $srcItemId
     * @param int $dstTypeId
     * @param int $dstItemId
     */
    public function moveMessages($srcTypeId, $srcItemId, $dstTypeId, $dstItemId)
    {
        $this->_getMessageTable()->update(array(
            'type_id' => $dstTypeId,
            'item_id' => $dstItemId
        ), array(
            'type_id = ?' => $srcTypeId,
            'item_id = ?' => $srcItemId
        ));
    }

    /**
     * @param int $type
     * @param int $item
     */
    public function getLastMessageRow($type, $item)
    {
        return $this->_getMessageTable()->fetchRow(array(
            'type_id = ?' => (int)$type,
            'item_id = ?' => (int)$item
        ), 'datetime DESC');
    }

    /**
     * @param int $type
     * @param int $item
     * @return Zend_Paginator
     */
    public function getMessagePaginator($type, $item)
    {
        return Zend_Paginator::factory(
            $this->_getMessageTable()->select(true)
                ->where('item_id = ?', (int)$item)
                ->where('type_id = ?', (int)$type)
                ->where('parent_id is null')
                ->order('datetime')
        );
    }

    /**
     * @param int $type
     * @param int $item
     * @return boolean
     */
    public function topicHaveModeratorAttention($type, $item)
    {
        return (bool)$this->_getMessageTable()->fetchRow(array(
            'item_id = ?'             => (int)$item,
            'type_id = ?'             => (int)$type,
            'moderator_attention = ?' => Comment_Message::MODERATOR_ATTENTION_REQUIRED
        ));
    }

    /**
     * @param int $id
     * @return Zend_Db_Table_Row
     */
    public function getMessageRow($id)
    {
        return $this->_getMessageTable()->fetchRow(array(
            'id = ?' => (int)$id
        ));
    }

    /**
     * @param Zend_Db_Table_Row $message
     * @return int
     */
    private function _getMessageRoot($message)
    {
        $root = $message;

        $table = $this->_getMessageTable();

        while ($root->parent_id) {
            $root = $table->fetchRow(array(
                'item_id = ?' => $root->item_id,
                'type_id = ?' => $root->type_id,
                'id = ?'      => $root->parent_id
            ));
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
        $root = $this->_getMessageRoot($message);

        $table = $this->_getMessageTable();
        $db = $table->getAdapter();

        $count = $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'COUNT(1)')
                ->where('item_id = ?', $root->item_id)
                ->where('type_id = ?', $root->type_id)
                ->where('datetime < ?', $root->datetime)
                ->where('parent_id is null')
        );
        return ceil(($count+1) / $perPage);
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
        $db = $this->_getMessageTable()->getAdapter();

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
    }

    private function _moveMessageRecursive($parentId, $newTypeId, $newItemId)
    {
        $newTypeId = (int)$newTypeId;
        $newItemId = (int)$newItemId;
        $parentId = (int)$parentId;

        $rows = $this->_getMessageTable()->fetchAll(array(
            'parent_id = ?' => $parentId
        ));

        foreach ($rows as $row) {
            $row->setFromArray(array(
                'item_id' => $newItemId,
                'type_id' => $newTypeId
            ));
            $row->save();

            $this->_moveMessageRecursive($row->id, $newTypeId, $newItemId);
        }
    }

    public function moveMessage($id, $newTypeId, $newItemId)
    {
        $messageRow = $this->getMessageRow($id);
        if (!$messageRow) {
            return false;
        }

        $newTypeId = (int)$newTypeId;
        $newItemId = (int)$newItemId;

        if ($messageRow->item_id == $newItemId && $messageRow->type_id == $newTypeId) {
            return false;
        }

        $oldTypeId = $messageRow->type_id;
        $oldItemId = $messageRow->item_id;

        $messageRow->setFromArray(array(
            'item_id'   => $newItemId,
            'type_id'   => $newTypeId,
            'parent_id' => null
        ));
        $messageRow->save();

        $this->_moveMessageRecursive($messageRow->id, $newTypeId, $newItemId);

        $commentTopicTable = new Comment_Topic();
        $commentTopicTable->updateTopicStat($oldTypeId, $oldItemId);
        $commentTopicTable->updateTopicStat($newTypeId, $newItemId);

        return true;
    }
}