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
     * @param int $itemId
     * @return array
     */
    public function getTopicStat($typeId, $itemId)
    {
        $messages = 0;

        $topic = $this->fetchRow(array(
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ));

        if ($topic) {
            $messages = (int)$topic->messages;
        }

        return array(
            'messages' => $messages,
        );
    }

    /**
     * @param int $typeId
     * @param int $itemId
     * @param int $userId
     * @return array
     */
    public function getTopicStatForUser($typeId, $itemId, $userId)
    {
        $messages = 0;
        $newMessages = 0;

        $topic = $this->fetchRow(array(
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ));

        if ($topic) {
            $messages = (int)$topic->messages;

            $db = $this->getAdapter();

            $viewTime = $db->fetchRow(
                $db->select()
                    ->from('comment_topic_view', 'timestamp')
                    ->where('comment_topic_view.item_id = ?', $itemId)
                    ->where('comment_topic_view.type_id = ?', $typeId)
                    ->where('comment_topic_view.user_id = ?', $userId)
            );

            if (!$viewTime) {
                $newMessages = $messages;
            } else {
                $newMessages = (int)$db->fetchOne(
                    $db->select()
                        ->from('comments_messages', 'count(1)')
                        ->where('comments_messages.item_id = ?', $itemId)
                        ->where('comments_messages.type_id = ?', $typeId)
                        ->where('comments_messages.datetime > ?', $viewTime)
                );
            }
        }

        return array(
            'messages'    => $messages,
            'newMessages' => $newMessages
        );
    }
}