<?php

use Application\Db\Table;

class User_Renames extends Table
{
    protected $_name = 'user_renames';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];
}