<?php

namespace Autowp\User\Model\DbTable\User;

use Application\Db\Table;

class Rename extends Table
{
    protected $_name = 'user_renames';

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];
}
