<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class UserValue extends Table
{
    protected $_name = 'attrs_user_values';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];
    protected $_primary = ['attribute_id', 'item_id', 'user_id'];
}
