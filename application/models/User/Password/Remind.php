<?php

use Application\Db\Table;

class User_Password_Remind extends Table
{
    protected $_name = 'user_password_remind';
    protected $_primary = 'hash';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
    ];
}