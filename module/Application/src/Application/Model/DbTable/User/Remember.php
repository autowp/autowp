<?php

namespace Application\Model\DbTable\User;

use Application\Db\Table;

class Remember extends Table
{
    protected $_name = 'user_remember';

    protected $_primary = ['token'];

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];
}
