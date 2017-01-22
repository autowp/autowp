<?php

namespace Autowp\Comments\Model\DbTable;

use Application\Db\Table;

class Topic extends Table
{
    protected $_name = 'comment_topic';
    protected $_primary = ['type_id', 'item_id'];

    /**
     * @param int $typeId
     * @param int $itemId
     * @return Topic
     */
    public function getTopic($typeId, $itemId)
    {
        $topic = $this->fetchRow([
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ]);
        if (! $topic) {
            $cmTable = new Message();

            $lastUpdate = $cmTable->getLastUpdate($typeId, $itemId);
            $messagesCount = $cmTable->getMessagesCount($typeId, $itemId);

            $topic = $this->createRow([
                'item_id'     => $itemId,
                'type_id'     => $typeId,
                'last_update' => $lastUpdate,
                'messages'    => $messagesCount
            ]);
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
        $cmTable = new Message();

        $messagesCount = $cmTable->getMessagesCount($typeId, $itemId);

        $topic = $this->fetchRow([
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ]);

        if ($messagesCount > 0) {
            $lastUpdate = $cmTable->getLastUpdate($typeId, $itemId);

            if (! $topic) {
                $topic = $this->createRow([
                    'item_id' => $itemId,
                    'type_id' => $typeId,
                ]);
            }

            $topic->setFromArray([
                'last_update' => $lastUpdate,
                'messages'    => $messagesCount
            ]);
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
        $this->getAdapter()->query($sql, [$userId, $typeId, $itemId]);
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @return array
     */
    public function getTopicStat($typeId, $itemId)
    {
        if (is_array($itemId)) {
            $result = [];

            if ($itemId) {
                $db = $this->getAdapter();

                $pairs = $db->fetchPairs(
                    $db->select()
                        ->from($this->info('name'), ['item_id', 'messages'])
                        ->where('item_id in (?)', $itemId)
                        ->where('type_id = ?', $typeId)
                );

                foreach ($pairs as $itemId => $count) {
                    $result[$itemId] = [
                        'messages' => $count
                    ];
                }
            }
        } else {
            $messages = 0;

            $topic = $this->fetchRow([
                'item_id = ?' => $itemId,
                'type_id = ?' => $typeId
            ]);

            if ($topic) {
                $messages = (int)$topic->messages;
            }

            $result = [
                'messages' => $messages,
            ];
        }

        return $result;
    }

    /**
     * @param int $typeId
     * @param int|array $itemId
     * @param int $userId
     * @return array
     */
    public function getNewMessages($typeId, $itemId, $userId)
    {
        $db = $this->getAdapter();

        $newMessagesSelect = $db->select()
            ->from('comments_messages', 'count(1)')
            ->where('comments_messages.item_id = :item_id')
            ->where('comments_messages.type_id = :type_id')
            ->where('comments_messages.datetime > :datetime');

        if (is_array($itemId)) {
            $result = [];

            if (count($itemId) > 0) {
                $select = $db->select()
                    ->from('comment_topic_view', ['item_id', 'timestamp'])
                    ->where('comment_topic_view.type_id = :type_id')
                    ->where('comment_topic_view.user_id = :user_id')
                    ->where('comment_topic_view.item_id in (?)', $itemId);

                $pairs = $db->fetchPairs($select, [
                    'user_id' => $userId,
                    'type_id' => $typeId
                ]);
            } else {
                $pairs = [];
            }

            foreach ($pairs as $id => $viewTime) {
                if ($viewTime) {
                    $newMessages = (int)$db->fetchOne($newMessagesSelect, [
                        'item_id'  => $id,
                        'type_id'  => $typeId,
                        'datetime' => $viewTime,
                    ]);

                    $result[$id] = $newMessages;
                }
            }

            return $result;
        } else {
            $newMessages = 0;

            $db = $this->getAdapter();

            $viewTime = $db->fetchOne(
                $db->select()
                    ->from('comment_topic_view', 'timestamp')
                    ->where('comment_topic_view.item_id = :item_id')
                    ->where('comment_topic_view.type_id = :type_id')
                    ->where('comment_topic_view.user_id = :user_id'),
                [
                    'item_id' => $itemId,
                    'type_id' => $typeId,
                    'user_id' => $userId,
                ]
            );

            if (! $viewTime) {
                $newMessages = null;
            } else {
                $newMessages = (int)$db->fetchOne($newMessagesSelect, [
                    'item_id'  => $itemId,
                    'type_id'  => $typeId,
                    'datetime' => $viewTime,
                ]);
            }

            return $newMessages;
        }
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
            $result = [];

            if (count($itemId) > 0) {
                $select = $db->select()
                    ->from('comment_topic', ['item_id', 'messages'])
                    ->where('comment_topic.type_id = :type_id')
                    ->where('comment_topic.item_id in (?)', $itemId)
                    ->joinLeft(
                        'comment_topic_view',
                        'comment_topic.type_id = comment_topic_view.type_id ' .
                        'and comment_topic.item_id = comment_topic_view.item_id ' .
                        'and comment_topic_view.user_id = :user_id',
                        'timestamp'
                    );

                $rows = $db->fetchAll($select, [
                    'user_id' => $userId,
                    'type_id' => $typeId
                ]);
            } else {
                $rows = [];
            }

            foreach ($rows as $row) {
                $id = $row['item_id'];
                $viewTime = $row['timestamp'];
                $messages = (int)$row['messages'];

                if (! $viewTime) {
                    $newMessages = $messages;
                } else {
                    $newMessages = (int)$db->fetchOne($newMessagesSelect, [
                        'item_id'  => $id,
                        'type_id'  => $typeId,
                        'datetime' => $viewTime,
                    ]);
                }

                $result[$id] = [
                    'messages'    => $messages,
                    'newMessages' => $newMessages
                ];
            }

            return $result;
        } else {
            $messages = 0;
            $newMessages = 0;

            $topic = $this->fetchRow([
                'item_id = ?' => $itemId,
                'type_id = ?' => $typeId
            ]);

            if ($topic) {
                $messages = (int)$topic->messages;

                $db = $this->getAdapter();

                $viewTime = $db->fetchOne(
                    $db->select()
                        ->from('comment_topic_view', 'timestamp')
                        ->where('comment_topic_view.item_id = :item_id')
                        ->where('comment_topic_view.type_id = :type_id')
                        ->where('comment_topic_view.user_id = :user_id'),
                    [
                        'item_id' => $itemId,
                        'type_id' => $typeId,
                        'user_id' => $userId,
                    ]
                );

                if (! $viewTime) {
                    $newMessages = $messages;
                } else {
                    $newMessages = (int)$db->fetchOne($newMessagesSelect, [
                        'item_id'  => $itemId,
                        'type_id'  => $typeId,
                        'datetime' => $viewTime,
                    ]);
                }
            }

            return [
                'messages'    => $messages,
                'newMessages' => $newMessages
            ];
        }
    }
}
