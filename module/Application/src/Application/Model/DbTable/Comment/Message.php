<?php

namespace Application\Model\DbTable\Comment;

use Zend_Db_Table;

class Message extends Zend_Db_Table
{
    const PICTURES_TYPE_ID = 1;
    const TWINS_TYPE_ID = 2;
    const VOTINGS_TYPE_ID = 3;
    const ARTICLES_TYPE_ID = 4;
    const FORUMS_TYPE_ID = 5;
    const MUSEUMS_TYPE_ID = 6;

    const
        MODERATOR_ATTENTION_NONE = 0,
        MODERATOR_ATTENTION_REQUIRED = 1,
        MODERATOR_ATTENTION_COMPLETED = 2;

    protected $_name = 'comments_messages';
    protected $_rowClass = MessageRow::class;
    protected $_referenceMap = [
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
    ];

    public function getMessagesCount($typeId, $itemId)
    {
        return $this->getAdapter()->fetchOne(
            $this->getAdapter()->select()
                ->from($this->info('name'), 'COUNT(1)')
                ->where('item_id = ?', $itemId)
                ->where('type_id = ?', $typeId)
        );
    }

    public function getLastUpdate($typeId, $itemId)
    {
        return $this->getAdapter()->fetchOne(
            $this->getAdapter()->select()
                ->from($this->info('name'), 'datetime')
                ->where('item_id = ?', $itemId)
                ->where('type_id = ?', $typeId)
                ->order('datetime desc')
                ->limit(1)
        );
    }

    public function getLastMessage($typeId, $itemId)
    {
        return $this->fetchRow([
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ], 'datetime DESC');
    }
}
