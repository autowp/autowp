<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class Value extends Table
{
    protected $_name = 'attrs_values';
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
        ],
    ];
}