<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class ValueAbstract extends Table
{
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribut_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ],
        'ItemType' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => ItemType::class,
            'refColumns'    => ['id']
        ]
    ];
}