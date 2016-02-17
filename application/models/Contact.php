<?php

namespace Application\Model;

use Project_Db_Table;
use Zend_Db_Expr;

class Contact
{
    /**
     * @var Project_Db_Table
     */
    private $_table;
    
    public function __construct()
    {
        $this->_table = new Project_Db_Table([
            'name'    => 'contact',
            'primary' => ['user_id', 'contact_user_id']
        ]);
    }
    
    public function create($userId, $contactUserId)
    {
        $row = $this->_table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        if (!$row) {
            $row = $this->_table->createRow([
                'user_id'         => (int)$userId,
                'contact_user_id' => (int)$contactUserId,
                'timestamp'       => new Zend_Db_Expr('now()')
            ]);
            $row->save();
        }
    }
    
    public function delete($userId, $contactUserId)
    {
        $row = $this->_table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        if ($row) {
            $row->delete();
        }
    }
    
    public function exists($userId, $contactUserId)
    {
        $row = $this->_table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        return (bool)$row;
    }
}