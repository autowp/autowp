<?php

namespace Application\Model;

class Message
{
    /**
     * @var \Project_Db_Table
     */
    private $_table;
    
    const MESSAGES_PER_PAGE = 20;
    
    public function __construct()
    {
        $this->_table = new \Project_Db_Table(array(
            'name'    => 'personal_messages',
            'primary' => 'id'
        ));
    }
    
    public function send($fromId = null, $toId, $message)
    {
        $message = trim($message);
        $msgLength = mb_strlen($message);
        
        if ($msgLength <= 0) {
            throw new \Exception('Сообщение пустое');
        }
        
        if ($msgLength > 2000) {
            throw new \Exception('Сообщение слишком длинное');
        }
        
        $this->_table->insert(array(
            'from_user_id' => $fromId ? (int)$fromId : null,
            'to_user_id'   => (int)$toId,
            'contents'     => $message,
            'add_datetime' => new \Zend_Db_Expr('NOW()'),
            'readen'       => 0
        ));
    }
    
    public function getNewCount($userId)
    {
        $db = $this->_table->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from($this->_table->info('name'), array('COUNT(1)'))
                ->where('to_user_id = ?', (int)$userId)
                ->where('NOT readen')
        );
    }
    
    public function delete($userId, $messageId)
    {
        $this->_table->update(array(
            'deleted_by_from'  => 1
        ), array(
            'from_user_id = ?' => (int)$userId,
            'id = ?'           => (int)$messageId
        ));
        
        $this->_table->update(array(
            'deleted_by_to'  => 1
        ), array(
            'to_user_id = ?' => (int)$userId,
            'id = ?'         => (int)$messageId
        ));
    }
    
    public function deleteAllSystem($userId)
    {
        $this->_table->delete(array(
            'to_user_id = ?' => (int)$userId,
            'from_user_id IS NULL'
        ));
    }
    
    public function deleteAllSent($userId)
    {
        $this->_table->update(array(
            'deleted_by_from' => 1
        ), array(
            'from_user_id = ?' => (int)$userId,
        ));
    }
    
    public function recycle()
    {
        return $this->_table->delete(array(
            'deleted_by_to',
            'deleted_by_from OR from_user_id IS NULL'
        ));
    }
    
    public function recycleSystem()
    {
        return $this->_table->delete(array(
            'from_user_id is null',
            'add_datetime < date_sub(now(), interval 6 month)'
        ));
    }
    
    private function _getSystemSelect($userId)
    {
        return $this->_table->select(true)
            ->where('to_user_id = ?', (int)$userId)
            ->where('from_user_id IS NULL')
            ->where('NOT deleted_by_to')
            ->order('add_datetime DESC');
    }
    
    private function _getInboxSelect($userId)
    {
        return $this->_table->select(true)
            ->where('to_user_id = ?', (int)$userId)
            ->where('from_user_id')
            ->where('NOT deleted_by_to')
            ->order('add_datetime DESC');
    }
    
    private function _getSentSelect($userId)
    {
        return $this->_table->select(true)
            ->where('from_user_id = ?', (int)$userId)
            ->where('NOT deleted_by_from')
            ->order('add_datetime DESC');
    }
    
    private function _getDialogSelect($userId, $withUserId)
    {
        return $this->_table->select(true)
            ->where('from_user_id = :user_id and to_user_id = :with_user_id and NOT deleted_by_from')
            ->orWhere('from_user_id = :with_user_id and to_user_id = :user_id and NOT deleted_by_to')
            ->order('add_datetime DESC')
            ->bind(array(
                'user_id'      => (int)$userId,
                'with_user_id' => (int)$withUserId
            ));
    }
    
    public function getSystemCount($userId)
    {
        $select = $this->_getSystemSelect($userId);
        
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function getNewSystemCount($userId)
    {
        $select = $this->_getSystemSelect($userId)
            ->where('NOT readen');
        
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function getInboxCount($userId)
    {
        $select = $this->_getInboxSelect($userId);
        
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function getInboxNewCount($userId)
    {
        $select = $this->_getInboxSelect($userId)
            ->where('NOT readen');
        
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function getDialogCount($userId, $withUserId)
    {
        $select = $this->_getDialogSelect($userId, $withUserId);
    
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function getSentCount($userId)
    {
        $select = $this->_getSentSelect($userId);
        
        return \Zend_Paginator::factory($select)->getTotalItemCount();
    }
    
    public function markReaden($ids)
    {
        if (count($ids)) {
            $this->_table->update(array(
                'readen' => 1
            ), array(
                'id IN (?)' => $ids
            ));
        }
    }
    
    public function getInbox($userId, $page)
    {   
        $select = $this->_getInboxSelect($userId);
        
        $paginator = \Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);
        
        $rows = $paginator->getCurrentItems();
        
        $markReaden = [];
        foreach ($rows as $message) {
            if (!$message->readen && $message->to_user_id == $userId) {
                $markReaden[] = $message->id;
            }
        }
        
        $this->markReaden($markReaden);
        
        return [
            'messages'  => $this->_prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }
    
    public function getSentbox($userId, $page)
    {
        $select = $this->_getSentSelect($userId);
    
        $paginator = \Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);
    
        $rows = $paginator->getCurrentItems();
    
        return [
            'messages'  => $this->_prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }
    
    public function getSystembox($userId, $page)
    {
        $select = $this->_getSystemSelect($userId);
    
        $paginator = \Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);
    
        $rows = $paginator->getCurrentItems();
        
        $markReaden = [];
        foreach ($rows as $message) {
            if (!$message->readen && $message->to_user_id == $userId) {
                $markReaden[] = $message->id;
            }
        }
        
        $this->markReaden($markReaden);
    
        return [
            'messages'  => $this->_prepareList($userId, $rows),
            'paginator' => $paginator
        ];
    }
    
    public function getDialogbox($userId, $withUserId, $page)
    {
        $select = $this->_getDialogSelect($userId, $withUserId);
    
        $paginator = \Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::MESSAGES_PER_PAGE)
            ->setCurrentPageNumber($page);
    
        $rows = $paginator->getCurrentItems();
    
        $markReaden = [];
        foreach ($rows as $message) {
            if (!$message->readen && $message->to_user_id == $userId) {
                $markReaden[] = $message->id;
            }
        }
    
        $this->markReaden($markReaden);
    
        return [
            'messages'  => $this->_prepareList($userId, $rows, array(
                'allMessagesLink' => false
            )),
            'paginator' => $paginator
        ];
    }
    
    private function _prepareList($userId, $rows, array $options = [])
    {
        $defaults = array(
            'allMessagesLink' => true
        );
        $options = array_replace($defaults, $options);
    
        $db = $this->_table->getAdapter();
    
        $cache = [];
        
        $userTable = new \Users();
    
        $messages = [];
        foreach ($rows as $message) {
            $author = $userTable->find($message->from_user_id)->current();
    
            $isNew = $message->to_user_id == $userId && !$message->readen;
            $canDelete = $message->from_user_id == $userId || $message->to_user_id == $userId;
            $authorIsMe = $author && ($author->id == $userId);
            $canReply = $author && !$author->deleted && !$authorIsMe;
    
            $dialogCount = 0;
    
            if ($canReply) {    
                if ($options['allMessagesLink'] && $author && !$authorIsMe) {
                    if (isset($cache[$author->id])) {
                        $dialogCount = $cache[$author->id];
                    } else {
                        $dialogCount = $this->getDialogCount($userId, $author->id);
                        $cache[$author->id] = $dialogCount;
                    }
                }
            }
    
            $messages[] = array(
                'id'              => $message->id,
                'author'          => $author,
                'authorUrl'       => $author ? $author->getAboutUrl() : null,
                'contents'        => $message->contents,
                'isNew'           => $isNew,
                'canDelete'       => $canDelete,
                'date'            => $message->getDateTime('add_datetime'),
                'canReply'        => $canReply,
                'dialogCount'     => $dialogCount,
                'allMessagesLink' => $options['allMessagesLink']
            );
        }
    
        return $messages;
    }
}