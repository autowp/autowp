<?php

namespace Application\Model\DbTable\User;

use Application\Db\Table;

class Account extends Table
{
    protected $_name = 'user_account';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}
