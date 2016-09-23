<?php

use Application\Db\Table;

class User_Remember extends Table
{
    protected $_name = 'user_remember';

    protected $_primary = ['token'];

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];

}