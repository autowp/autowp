<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class UserValueAbstract extends Table
{
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribut_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'ItemType' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => ItemType::class,
            'refColumns'    => ['id']
        ],
    ];
}