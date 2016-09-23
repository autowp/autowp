<?php

class Comment_Message extends Zend_Db_Table
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
    protected $_rowClass = 'Comment_Message_Row';
    protected $_referenceMap = array(
        'Author' => array(
            'columns'       => array('author_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'DeletedBy' => array(
            'columns'       => array('deleted_by'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );

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
        return $this->fetchRow(array(
            'item_id = ?' => $itemId,
            'type_id = ?' => $typeId
        ), 'datetime DESC');
    }


}