<?php

namespace Autowp\User\Model\DbTable\User;

use Application\Db\Table;

class PasswordRemind extends Table
{
    protected $_name = 'user_password_remind';
    protected $_primary = 'hash';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}
