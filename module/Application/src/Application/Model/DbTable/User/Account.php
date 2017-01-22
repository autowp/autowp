<?php

namespace Application\Model\DbTable\User;

use Autowp\Commons\Db\Table;

class Account extends Table
{
    protected $_name = 'user_account';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}
