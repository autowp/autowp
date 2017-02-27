<?php

namespace Application\Model;

use Autowp\Commons\Db\Table;

use Zend_Db_Expr;

class Contact
{
    /**
     * @var Table
     */
    private $table;

    public function __construct()
    {
        $this->table = new Table([
            'name'    => 'contact',
            'primary' => ['user_id', 'contact_user_id']
        ]);
    }

    public function create($userId, $contactUserId)
    {
        $row = $this->table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        if (! $row) {
            $row = $this->table->createRow([
                'user_id'         => (int)$userId,
                'contact_user_id' => (int)$contactUserId,
                'timestamp'       => new Zend_Db_Expr('now()')
            ]);
            $row->save();
        }
    }

    public function delete($userId, $contactUserId)
    {
        $row = $this->table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        if ($row) {
            $row->delete();
        }
    }

    public function exists($userId, $contactUserId)
    {
        $row = $this->table->fetchRow([
            'user_id = ?'         => (int)$userId,
            'contact_user_id = ?' => (int)$contactUserId
        ]);
        return (bool)$row;
    }
    
    public function deleteUserEverywhere($userId)
    {
        $this->table->delete([
            'user_id = ?' => (int)$userId
        ]);
        
        $this->table->delete([
            'contact_user_id = ?' => (int)$userId
        ]);
    }
}
