<?php

use Application\Db\Table;

class User_Account extends Table
{
    protected $_name = 'user_account';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
    ];
}