<?php

class Comment_Topic extends Project_Db_Table
{
    protected $_name = 'comment_topic';
    protected $_primary = array('type_id', 'item_id');

    /**
     * @param int $typeId
     * @param int $itemId
     * @return Comment_Topic_Row
     */
    public function getTopic($typeId, $itemId)
    {
        $topic = $this->fetchRow(array(
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ));
        if (!$topic) {

            $cmTable = new Comment_Message();

            $lastUpdate = $cmTable->getLastUpdate($typeId, $itemId);
            $messagesCount = $cmTable->getMessagesCount($typeId, $itemId);

            $topic = $this->createRow(array(
                'item_id'     => $itemId,
                'type_id'     => $typeId,
                'last_update' => $lastUpdate,
                'messages'    => $messagesCount
            ));
            $topic->save();
        }

        return $topic;
    }

    /**
     * @param int $typeId
     * @param int $itemId
     */
    public function updateTopicStat($typeId, $itemId)
    {
        $cmTable = new Comment_Message();

        $messagesCount = $cmTable->getMessagesCount($typeId, $itemId);

        $topic = $this->fetchRow(array(
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ));

        if ($messagesCount > 0) {
            $lastUpdate = $cmTable->getLastUpdate($typeId, $itemId);

            if (!$topic) {
                $topic = $this->createRow(array(
                    'item_id' => $itemId,
                    'type_id' => $typeId,
                ));
            }

            $topic->setFromArray(array(
                'last_update' => $lastUpdate,
                'messages'    => $messagesCount
            ));
            $topic->save();
        } else {
            if ($topic) {
                $topic->delete();
            }
        }
    }

    /**
     * @param int $typeId
     * @param int $itemId
     * @param int $userId
     */
    public function updateTopicView($typeId, $itemId, $userId)
    {
        $sql = '
            insert into comment_topic_view (user_id, type_id, item_id, `timestamp`)
            values (?, ?, ?, NOW())
            on duplicate key update `timestamp` = values(`timestamp`)
        ';
        $this->getAdapter()->query($sql, array($userId, $typeId, $itemId));
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @return array
     */
    public function getTopicStat($typeId, $itemId)
    {
        if (is_array($itemId)) {

            $result = array();

            if ($itemId) {

                $db = $this->getAdapter();

                $pairs = $db->fetchPairs(
                    $db->select()
                        ->from($this->info('name'), array('item_id', 'messages'))
                        ->where('item_id in (?)', $itemId)
                        ->where('type_id = ?', $typeId)
                );

                foreach ($pairs as $itemId => $count) {
                    $result[$itemId] = array(
                        'messages' => $count
                    );
                }

            }

        } else {

            $messages = 0;

            $topic = $this->fetchRow(array(
                'item_id = ?' => $itemId,
                'type_id = ?' => $typeId
            ));

            if ($topic) {
                $messages = (int)$topic->messages;
            }

            $result = array(
                'messages' => $messages,
            );
        }

        return $result;
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array
     */
    public function getTopicStatForUser($typeId, $itemId, $userId)
    {
        $db = $this->getAdapter();

        $newMessagesSelect = $db->select()
            ->from('comments_messages', 'count(1)')
            ->where('comments_messages.item_id = :item_id')
            ->where('comments_messages.type_id = :type_id')
            ->where('comments_messages.datetime > :datetime');

        if (is_array($itemId)) {

            $result = array();

            if (count($itemId) > 0) {
                $select = $db->select()
                    ->from('comment_topic', array('item_id', 'messages'))
                    ->where('comment_topic.type_id = :type_id')
                    ->where('comment_topic.item_id in (?)', $itemId)
                    ->joinLeft(
                        'comment_topic_view',
                        'comment_topic.type_id = comment_topic_view.type_id ' .
                        'and comment_topic.item_id = comment_topic_view.item_id ' .
                        'and comment_topic_view.user_id = :user_id',
                        'timestamp'
                    );

                $rows = $db->fetchAll($select, array(
                    'user_id' => $userId,
                    'type_id' => $typeId
                ));
            } else {
                $rows = array();
            }

            foreach ($rows as $row) {
                $id = $row['item_id'];
                $viewTime = $row['timestamp'];
                $messages = (int)$row['messages'];

                if (!$viewTime) {
                    $newMessages = $messages;
                } else {
                    $newMessages = (int)$db->fetchOne($newMessagesSelect, array(
                        'item_id'  => $id,
                        'type_id'  => $typeId,
                        'datetime' => $viewTime,
                    ));
                }

                $result[$id] = array(
                    'messages'    => $messages,
                    'newMessages' => $newMessages
                );
            }

            return $result;

        } else {
            $messages = 0;
            $newMessages = 0;

            $topic = $this->fetchRow(array(
                'item_id = ?' => $itemId,
                'type_id = ?' => $typeId
            ));

            if ($topic) {
                $messages = (int)$topic->messages;

                $db = $this->getAdapter();

                $viewTime = $db->fetchOne(
                    $db->select()
                        ->from('comment_topic_view', 'timestamp')
                        ->where('comment_topic_view.item_id = :item_id')
                        ->where('comment_topic_view.type_id = :type_id')
                        ->where('comment_topic_view.user_id = :user_id'),
                    array(
                        'item_id' => $itemId,
                        'type_id' => $typeId,
                        'user_id' => $userId,
                    )
                );

                if (!$viewTime) {
                    $newMessages = $messages;
                } else {
                    $newMessages = (int)$db->fetchOne($newMessagesSelect, array(
                        'item_id'  => $itemId,
                        'type_id'  => $typeId,
                        'datetime' => $viewTime,
                    ));
                }
            }

            return array(
                'messages'    => $messages,
                'newMessages' => $newMessages
            );
        }
    }
}